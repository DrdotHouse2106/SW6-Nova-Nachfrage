<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

use Doctrine\DBAL\Connection;
use Nova\Nachfrage\Installer\MailTemplateInstaller;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Versand der Nachfrage-Mails (geplante Aufgabe stündlich, Konsole nova-nachfrage:send).
 *
 * Je Bestellung höchstens eine Mail (Versandprotokoll). Vor dem Versand geprüft: Fälligkeit (DuePolicy),
 * Widerspruch (eigene Tabelle, optional Newsletter-Abmeldung), Mindestabstand je Empfänger.
 * Testmodus: Mails gehen an die Testadresse; der Abmeldelink gilt dann für die Testadresse, nicht für den Kunden.
 */
class NachfrageSender
{
    public const ACTION_SENT = 'sent';
    public const ACTION_WOULD_SEND = 'would_send';
    public const ACTION_TEST = 'test';
    public const ACTION_FAILED = 'failed';
    public const ACTION_SKIPPED = 'skipped';
    public const ACTION_WAIT = 'wait';

    public const REASON_OPTOUT = 'optout';
    public const REASON_NEWSLETTER = 'newsletter_optout';
    public const REASON_RECENT = 'recently_mailed';
    public const REASON_EMAIL = 'invalid_email';

    /** Höchstens so viele Mails je Lauf (stündlich), damit der Mailserver nicht überlastet wird */
    public const DEFAULT_LIMIT = 100;

    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $orderRepository,
        private readonly AbstractMailService $mailService,
        private readonly SystemConfigService $systemConfig,
        private readonly Settings $settings,
        private readonly CandidateFinder $finder,
        private readonly OptOutStore $optOuts,
        private readonly SendLog $log,
        private readonly ProductCollector $products,
        private readonly UrlBuilder $urls,
        private readonly LoggerInterface $logger,
        private readonly string $secret,
    ) {
    }

    /**
     * @param bool $dryRun nichts senden, nichts speichern – nur berichten
     * @param bool $includePending auch noch nicht fällige Bestellungen berichten (Vorschau)
     * @param bool $light nur mit $dryRun: Mails nicht zusammenstellen (schnelle Übersicht für die Administration)
     *
     * @return list<array{orderNumber: string, orderId: string, salesChannelId: string, email: string, action: string, reason: string|null, dueAt: string|null, products: list<string>, recommendations: list<string>}>
     */
    public function run(bool $dryRun = false, ?string $salesChannelId = null, ?string $orderNumber = null, int $limit = self::DEFAULT_LIMIT, bool $includePending = false, bool $light = false): array
    {
        $report = [];
        $mailed = 0;
        /** @var array<string, true> $mailedInRun E-Mail-Hashes, die in diesem Lauf (auch im Probelauf) schon eine Mail bekommen hätten */
        $mailedInRun = [];
        $now = new \DateTimeImmutable();

        foreach ($this->finder->salesChannelIds() as $scId) {
            if (($salesChannelId !== null && $scId !== strtolower($salesChannelId)) || !$this->settings->active($scId)) {
                continue;
            }
            $policy = $this->settings->policy($scId);
            $testMode = $this->settings->testMode($scId);

            foreach ($this->finder->find([$scId], $policy->maxAge(), $testMode, $orderNumber, $this->settings->notBefore($scId), $this->settings->onlyStorefrontOrders($scId)) as $candidate) {
                $verdict = $policy->evaluate(
                    $candidate['orderState'],
                    $candidate['transactionState'],
                    $candidate['deliveries'],
                    $candidate['shipped'],
                    $candidate['productLines'],
                    $candidate['shippedAt'],
                    $now
                );
                $dueAt = $candidate['shippedAt'] !== null ? $policy->dueAt($candidate['shippedAt'])->format('Y-m-d H:i') : null;
                $row = [
                    'orderNumber' => $candidate['orderNumber'],
                    'orderId' => $candidate['orderId'],
                    'salesChannelId' => $scId,
                    'email' => $candidate['email'],
                    'action' => self::ACTION_SKIPPED,
                    'reason' => null,
                    'dueAt' => $dueAt,
                    'products' => [],
                    'recommendations' => [],
                ];
                $hash = EmailHash::hash($candidate['email']);

                if ($verdict === DuePolicy::WAIT) {
                    if ($includePending) {
                        $report[] = ['action' => self::ACTION_WAIT] + $row;
                    }
                    continue;
                }
                if ($verdict !== DuePolicy::DUE) {
                    if (\in_array($verdict, DuePolicy::PERMANENT, true)) {
                        $report[] = $this->skip($row, $hash, $verdict, $dryRun);
                    }
                    continue;
                }

                $reason = $this->recipientBlock($candidate['email'], $hash, $scId, $testMode, $now, isset($mailedInRun[$hash]));
                if ($reason !== null) {
                    $report[] = $this->skip($row, $hash, $reason, $dryRun);
                    continue;
                }
                if ($mailed >= $limit) {
                    continue;
                }

                $recipient = $candidate['email'];
                if ($testMode) {
                    $recipient = $this->settings->testRecipient($scId);
                    if (!EmailHash::isValidEmail($recipient)) {
                        $report[] = ['action' => self::ACTION_FAILED, 'reason' => 'no_test_recipient'] + $row;
                        continue;
                    }
                }

                if ($dryRun && $light) {
                    $mailedInRun[$hash] = true;
                    ++$mailed;
                    $report[] = ['action' => self::ACTION_WOULD_SEND] + $row;
                    continue;
                }

                try {
                    $mail = $this->buildMail($candidate['orderId'], $scId, $testMode ? EmailHash::hash($recipient) : $hash);
                } catch (\Throwable $e) {
                    $this->logger->warning('Nova Nachfrage: Mail nicht zusammenstellbar', ['order' => $candidate['orderNumber'], 'error' => $e->getMessage()]);
                    $mail = null;
                }
                if ($mail === null) {
                    $report[] = ['action' => self::ACTION_FAILED, 'reason' => 'order_not_loadable'] + $row;
                    continue;
                }
                $row['products'] = array_map(static fn (array $p): string => $p['name'], $mail['data']['products']);
                $row['recommendations'] = array_map(static fn (array $p): string => $p['name'], $mail['data']['recommendations']);
                $mailedInRun[$hash] = true;
                ++$mailed;

                if ($dryRun) {
                    $report[] = ['action' => self::ACTION_WOULD_SEND, 'email' => $recipient] + $row;
                    continue;
                }
                if (!$this->log->claim($candidate['orderId'], $scId, $hash, $testMode)) {
                    --$mailed;
                    continue;
                }
                $ok = $this->send($mail['order'], $recipient, $mail['data'], $testMode);
                $status = $ok ? ($testMode ? SendLog::STATUS_TEST : SendLog::STATUS_SENT) : SendLog::STATUS_FAILED;
                $this->log->finish($candidate['orderId'], $status, $ok ? null : 'mail_failed');
                $report[] = ['action' => $ok ? ($testMode ? self::ACTION_TEST : self::ACTION_SENT) : self::ACTION_FAILED, 'email' => $recipient, 'reason' => $ok ? null : 'mail_failed'] + $row;
            }
        }

        if (!$dryRun) {
            $this->log->cleanup();
        }

        return $report;
    }

    /**
     * Grund, warum dieser Empfänger keine Mail bekommt, oder null.
     */
    private function recipientBlock(string $email, string $hash, string $scId, bool $testMode, \DateTimeImmutable $now, bool $mailedInRun): ?string
    {
        if (!EmailHash::isValidEmail($email)) {
            return self::REASON_EMAIL;
        }
        if ($this->optOuts->isOptedOut($hash, $scId)) {
            return self::REASON_OPTOUT;
        }
        if ($this->settings->respectNewsletterOptOut($scId) && $this->optOuts->newsletterOptedOut($email, $scId, $this->settings->optOutAllChannels($scId))) {
            return self::REASON_NEWSLETTER;
        }
        $minDays = $this->settings->minDaysBetweenMails($scId);
        if ($mailedInRun && $minDays > 0) {
            return self::REASON_RECENT;
        }
        if (DuePolicy::recentlyMailed($this->log->lastSentTo($hash, $testMode), $minDays, $now)) {
            return self::REASON_RECENT;
        }

        return null;
    }

    /**
     * @param array{orderNumber: string, orderId: string, salesChannelId: string, email: string, action: string, reason: string|null, dueAt: string|null, products: list<string>, recommendations: list<string>} $row
     *
     * @return array{orderNumber: string, orderId: string, salesChannelId: string, email: string, action: string, reason: string|null, dueAt: string|null, products: list<string>, recommendations: list<string>}
     */
    private function skip(array $row, string $hash, string $reason, bool $dryRun): array
    {
        if (!$dryRun) {
            $this->log->skip($row['orderId'], $row['salesChannelId'], $hash, $reason);
        }
        $row['action'] = self::ACTION_SKIPPED;
        $row['reason'] = $reason;

        return $row;
    }

    /**
     * Bestellung laden und Vorlagenvariablen zusammenstellen.
     *
     * @return array{order: OrderEntity, data: array{products: list<array<string, mixed>>, recommendations: list<array<string, mixed>>, extraText: string, unsubscribeUrl: string, googleReviewUrl: string, googleReviewText: string, reviewsEnabled: bool, canReview: bool, shopUrl: string, currencyIsoCode: string, pricesNet: bool, optOutAllChannels: bool}}|null
     */
    public function buildMail(string $orderId, string $scId, string $unsubscribeHash): ?array
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociations(['lineItems.cover', 'orderCustomer.salutation', 'orderCustomer.customer', 'currency', 'language.locale']);
        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        if (!$order instanceof OrderEntity) {
            return null;
        }
        $context = $this->products->context($order);
        $reviewsEnabled = $this->systemConfig->getBool('core.listing.showReview', $scId);
        $customer = $order->getOrderCustomer()?->getCustomer();
        $canReview = $reviewsEnabled && $customer !== null && !$customer->getGuest() && $customer->getActive();
        $languageId = $order->getLanguageId();

        return [
            'order' => $order,
            'data' => [
                'products' => $this->products->purchased($order, $context, $reviewsEnabled),
                'recommendations' => $this->products->recommendations($order, $context, $this->settings->maxRecommendations($scId), $this->settings->ignoredCategories($scId), $this->settings->vehicleGroups($scId)),
                'extraText' => $this->settings->extraText($scId),
                'unsubscribeUrl' => $this->urls->base($scId, $languageId) . OptOutToken::path($unsubscribeHash, $scId, $this->secret),
                'googleReviewUrl' => $this->settings->googleReviewUrl($scId),
                'googleReviewText' => $this->settings->googleReviewText($scId),
                'reviewsEnabled' => $reviewsEnabled,
                'canReview' => $canReview,
                'shopUrl' => $this->urls->base($scId, $languageId) . '/',
                'currencyIsoCode' => $order->getCurrency()?->getIsoCode() ?? 'EUR',
                'pricesNet' => $context->getTaxState() === CartPrice::TAX_STATE_NET,
                'optOutAllChannels' => $this->settings->optOutAllChannels($scId),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function send(OrderEntity $order, string $recipient, array $data, bool $testMode): bool
    {
        $languageId = $order->getLanguageId();
        $template = $this->template($languageId);
        if ($template === null) {
            $this->logger->warning('Nova Nachfrage: E-Mail-Vorlage „Nova Nachfrage“ fehlt');

            return false;
        }
        $customer = $order->getOrderCustomer();
        $name = $customer !== null ? trim($customer->getFirstName() . ' ' . $customer->getLastName()) : '';
        $context = new Context(new SystemSource(), [], $order->getCurrencyId(), array_values(array_unique([$languageId, Defaults::LANGUAGE_SYSTEM])));
        try {
            $mail = $this->mailService->send([
                'recipients' => [$recipient => $name !== '' ? $name : $recipient],
                'salesChannelId' => $order->getSalesChannelId(),
                'senderName' => $template['sender_name'],
                'subject' => ($testMode ? '[Test] ' : '') . $template['subject'],
                'contentHtml' => $template['content_html'],
                'contentPlain' => $template['content_plain'],
                'templateId' => $template['id'],
                // ausgewertet von MailHeaderSubscriber (List-Unsubscribe)
                'novaNachfrageUnsubscribeUrl' => $this->settings->listUnsubscribeHeader($order->getSalesChannelId()) ? $data['unsubscribeUrl'] : null,
            ], $context, ['order' => $order, 'customer' => $customer, 'eventName' => 'nova_nachfrage.mail'] + $data);

            return $mail !== null;
        } catch (\Throwable $e) {
            $this->logger->warning('Nova Nachfrage: Mail fehlgeschlagen', ['order' => $order->getOrderNumber(), 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array{id: string, sender_name: string|null, subject: string, content_html: string, content_plain: string}|null
     */
    private function template(string $languageId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT LOWER(HEX(mt.id)) AS id, tr.sender_name, tr.subject, tr.content_html, tr.content_plain
               FROM mail_template mt
               INNER JOIN mail_template_type ty ON ty.id = mt.mail_template_type_id
               INNER JOIN mail_template_translation tr ON tr.mail_template_id = mt.id
              WHERE ty.technical_name = :type AND tr.subject IS NOT NULL AND tr.content_html IS NOT NULL
              ORDER BY mt.system_default DESC, (tr.language_id = :lang) DESC, (tr.language_id = :system) DESC
              LIMIT 1',
            ['type' => MailTemplateInstaller::TYPE, 'lang' => Uuid::fromHexToBytes($languageId), 'system' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
        if ($row === false) {
            return null;
        }
        $html = (string) $row['content_html'];
        $plain = \is_string($row['content_plain'] ?? null) && trim($row['content_plain']) !== '' ? $row['content_plain'] : strip_tags($html);

        return [
            'id' => (string) $row['id'],
            'sender_name' => \is_string($row['sender_name'] ?? null) && $row['sender_name'] !== '' ? $row['sender_name'] : null,
            'subject' => (string) $row['subject'],
            'content_html' => $html,
            'content_plain' => $plain,
        ];
    }
}
