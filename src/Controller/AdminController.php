<?php declare(strict_types=1);

namespace Nova\Nachfrage\Controller;

use Nova\Nachfrage\Service\EmailHash;
use Nova\Nachfrage\Service\NachfrageSender;
use Nova\Nachfrage\Service\OptOutStore;
use Nova\Nachfrage\Service\SendLog;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Admin-API für die Übersicht „Nova Nachfrage“ (Einstellungen → Erweiterungen):
 * Kennzahlen, zuletzt verarbeitete und demnächst fällige Bestellungen, Widersprüche (nur gekürzter Hash)
 * und manuelles Eintragen eines Widerspruchs. E-Mail-Adressen werden nicht ausgegeben.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => ['api']])]
class AdminController
{
    public function __construct(
        private readonly NachfrageSender $sender,
        private readonly SendLog $log,
        private readonly OptOutStore $optOuts,
    ) {
    }

    #[Route(path: '/api/_action/nova-nachfrage/overview', name: 'api.action.nova_nachfrage.overview', defaults: ['_acl' => ['system_config:read']], methods: ['GET'])]
    public function overview(): JsonResponse
    {
        $upcoming = [];
        foreach ($this->sender->run(dryRun: true, limit: 1000, includePending: true, light: true) as $row) {
            $upcoming[] = [
                'orderId' => $row['orderId'],
                'orderNumber' => $row['orderNumber'],
                'action' => $row['action'],
                'reason' => $row['reason'],
                'dueAt' => $row['dueAt'],
            ];
        }
        usort($upcoming, static fn (array $a, array $b): int => strcmp((string) $a['dueAt'], (string) $b['dueAt']));

        return new JsonResponse([
            'stats' => $this->log->stats() + ['optOuts' => $this->optOuts->count()],
            'recent' => $this->log->recent(25),
            'upcoming' => \array_slice($upcoming, 0, 25),
            'optOuts' => $this->optOuts->list(100),
        ]);
    }

    #[Route(path: '/api/_action/nova-nachfrage/optout', name: 'api.action.nova_nachfrage.optout', defaults: ['_acl' => ['system_config:update']], methods: ['POST'])]
    public function addOptOut(Request $request): JsonResponse
    {
        $email = (string) $request->request->get('email', '');
        if (!EmailHash::isValidEmail($email)) {
            return new JsonResponse(['ok' => false, 'error' => 'email'], 400);
        }
        $salesChannelId = (string) $request->request->get('salesChannelId', '');
        if ($salesChannelId !== '' && !Uuid::isValid($salesChannelId)) {
            return new JsonResponse(['ok' => false, 'error' => 'salesChannel'], 400);
        }
        $new = $this->optOuts->addEmail($email, OptOutStore::SOURCE_ADMIN, $salesChannelId === '' ? null : strtolower($salesChannelId));

        return new JsonResponse(['ok' => true, 'new' => $new, 'hash' => EmailHash::short(EmailHash::hash($email))]);
    }
}
