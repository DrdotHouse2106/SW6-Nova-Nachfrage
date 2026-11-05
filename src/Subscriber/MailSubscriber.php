<?php declare(strict_types=1);

namespace Nova\Nachfrage\Subscriber;

use Nova\Nachfrage\Service\Settings;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailTemplateRenderContextEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * - List-Unsubscribe (RFC 2369) und List-Unsubscribe-Post (RFC 8058, „One-Click“) für die Nachfrage-Mails:
 *   Mailprogramme zeigen dann einen eigenen „Abmelden“-Knopf. Der One-Click-POST landet auf derselben URL
 *   wie der Link in der Mail und wird dort ohne Zwischenseite ausgeführt.
 * - Variable novaNachfrageNotice in allen Mail-Vorlagen (z. B. Bestellbestätigung): der Hinweis auf die
 *   Nachfrage-Mail und das Widerspruchsrecht, wenn das Plugin im Verkaufskanal aktiv ist, sonst ''.
 */
class MailSubscriber implements EventSubscriberInterface
{
    public const NOTICE_SNIPPET = 'novaNachfrage.checkoutNotice';

    private const NOTICE_FALLBACK = 'Wir nutzen Ihre E-Mail-Adresse, um Sie nach dem Versand nach Ihrer Meinung zu fragen und auf eigene ähnliche Produkte hinzuweisen. Dem können Sie jederzeit widersprechen, z. B. per Link in jeder dieser E-Mails, ohne dass andere als die Übermittlungskosten nach den Basistarifen entstehen.';

    public function __construct(
        private readonly Settings $settings,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeSentEvent::class => 'addHeaders',
            MailTemplateRenderContextEvent::class => 'addNotice',
        ];
    }

    public function addHeaders(MailBeforeSentEvent $event): void
    {
        $url = $event->getData()['novaNachfrageUnsubscribeUrl'] ?? null;
        if (!\is_string($url) || !str_starts_with($url, 'https://')) {
            return;
        }
        $headers = $event->getMessage()->getHeaders();
        $headers->remove('List-Unsubscribe');
        $headers->remove('List-Unsubscribe-Post');
        $headers->addTextHeader('List-Unsubscribe', '<' . $url . '>');
        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
    }

    public function addNotice(MailTemplateRenderContextEvent $event): void
    {
        $salesChannel = $event->getSalesChannel();
        if ($salesChannel === null || !$this->settings->active($salesChannel->getId())) {
            $event->addTemplateData('novaNachfrageNotice', '');

            return;
        }
        $text = $this->settings->checkoutNotice($salesChannel->getId());
        if ($text === '') {
            $translated = $this->translator->trans(self::NOTICE_SNIPPET);
            $text = $translated !== self::NOTICE_SNIPPET ? $translated : self::NOTICE_FALLBACK;
        }
        $event->addTemplateData('novaNachfrageNotice', $text);
    }
}
