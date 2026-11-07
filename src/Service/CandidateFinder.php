<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Sucht Bestellungen, deren Lieferungen alle auf „versendet“ stehen und deren Versand im Zeitfenster
 * (Höchstalter) liegt. Ob sie schon fällig sind, entscheidet DuePolicy.
 *
 * Versandzeitpunkt je Lieferung: letzter Eintrag in state_machine_history mit Ziel „versendet“.
 * Fehlt die Historie (Status ohne Übergang direkt gesetzt, z. B. per Import), zählt ersatzweise
 * updated_at der Lieferung. Bei mehreren Lieferungen gilt der späteste Versand.
 * Nur Bestellungen ab dem Stichtag (Hinweis bei Erhebung, § 7 Abs. 3 UWG) und – einstellbar – nur aus dem Shop.
 * Vorfilter: Bestelldatum höchstens Höchstalter + 365 Tage zurück, mindestens eine Lieferung im
 * Zeitfenster geändert (der Wechsel auf „versendet“ setzt updated_at).
 */
class CandidateFinder
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $salesChannelIds
     * @param bool $testMode im Testmodus gelten Test-Einträge als erledigt, sonst nicht
     *
     * @return list<array{orderId: string, orderNumber: string, salesChannelId: string, email: string, orderState: string, transactionState: string|null, deliveries: int, shipped: int, productLines: int, shippedAt: \DateTimeImmutable|null}>
     */
    /**
     * @param string|null $notBefore Stichtag (UTC, „Y-m-d H:i:s“); null = Installationsdatum des Plugins
     * @param bool $onlyStorefront im Admin angelegte Bestellungen (created_by_id gesetzt) überspringen
     */
    public function find(array $salesChannelIds, int $maxAgeDays, bool $testMode, ?string $orderNumber = null, ?string $notBefore = null, bool $onlyStorefront = true): array
    {
        if ($salesChannelIds === []) {
            return [];
        }
        $now = new \DateTimeImmutable();
        $oldest = $now->modify('-' . ($maxAgeDays + 1) . ' days');

        $sql = 'SELECT LOWER(HEX(o.id)) AS order_id, MAX(o.order_number) AS order_number, LOWER(HEX(MAX(o.sales_channel_id))) AS sales_channel_id,
                       MAX(oc.email) AS email, MAX(os.technical_name) AS order_state,
                       (SELECT ts.technical_name FROM order_transaction t
                          INNER JOIN state_machine_state ts ON ts.id = t.state_id
                         WHERE t.order_id = o.id AND t.order_version_id = o.version_id
                         ORDER BY t.created_at DESC LIMIT 1) AS transaction_state,
                       (SELECT COUNT(*) FROM order_line_item li
                         WHERE li.order_id = o.id AND li.order_version_id = o.version_id AND li.type = :product) AS product_lines,
                       SUM(ds.technical_name <> :cancelled) AS deliveries,
                       SUM(ds.technical_name = :shipped) AS shipped,
                       MAX(CASE WHEN ds.technical_name = :shipped THEN COALESCE(
                           (SELECT MAX(h.created_at) FROM state_machine_history h
                             WHERE h.entity_name = :entity AND h.referenced_id = d.id AND h.referenced_version_id = d.version_id
                               AND h.to_state_id = d.state_id),
                           d.updated_at, d.created_at) END) AS shipped_at
                  FROM `order` o
                  INNER JOIN order_customer oc ON oc.order_id = o.id AND oc.order_version_id = o.version_id
                  INNER JOIN state_machine_state os ON os.id = o.state_id
                  INNER JOIN order_delivery d ON d.order_id = o.id AND d.order_version_id = o.version_id
                  INNER JOIN state_machine_state ds ON ds.id = d.state_id
                  LEFT JOIN nova_nachfrage_log l ON l.order_id = o.id
                 WHERE o.version_id = :live
                   AND o.sales_channel_id IN (:channels)
                   AND o.order_date >= :orderOldest
                   AND o.order_date_time >= :notBefore
                   AND EXISTS (SELECT 1 FROM order_delivery d2
                                WHERE d2.order_id = o.id AND d2.order_version_id = o.version_id
                                  AND COALESCE(d2.updated_at, d2.created_at) >= :oldest)
                   AND (l.order_id IS NULL
                        OR (l.status = :failed AND l.attempts < :maxAttempts)
                        OR (:live_mode = 1 AND l.status = :test))';
        $params = [
            'product' => 'product',
            'cancelled' => 'cancelled',
            'shipped' => 'shipped',
            'entity' => 'order_delivery',
            'live' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'channels' => array_map(static fn (string $id): string => Uuid::fromHexToBytes($id), $salesChannelIds),
            'orderOldest' => $oldest->modify('-365 days')->format('Y-m-d'),
            'oldest' => $oldest->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'failed' => SendLog::STATUS_FAILED,
            'maxAttempts' => SendLog::MAX_ATTEMPTS,
            'live_mode' => $testMode ? 0 : 1,
            'test' => SendLog::STATUS_TEST,
            'notBefore' => $notBefore ?? $this->installedAt(),
        ];
        if ($onlyStorefront) {
            $sql .= ' AND o.created_by_id IS NULL';
        }
        if ($orderNumber !== null) {
            $sql .= ' AND o.order_number = :orderNumber';
            $params['orderNumber'] = $orderNumber;
        }
        $sql .= ' GROUP BY o.id, o.version_id ORDER BY shipped_at ASC';

        $rows = $this->connection->fetchAllAssociative($sql, $params, ['channels' => ArrayParameterType::BINARY]);

        $out = [];
        foreach ($rows as $row) {
            $shippedAt = $row['shipped_at'] ?? null;
            $out[] = [
                'orderId' => (string) $row['order_id'],
                'orderNumber' => (string) $row['order_number'],
                'salesChannelId' => (string) $row['sales_channel_id'],
                'email' => (string) $row['email'],
                'orderState' => (string) $row['order_state'],
                'transactionState' => \is_string($row['transaction_state'] ?? null) ? $row['transaction_state'] : null,
                'deliveries' => (int) $row['deliveries'],
                'shipped' => (int) $row['shipped'],
                'productLines' => (int) $row['product_lines'],
                'shippedAt' => \is_string($shippedAt) && $shippedAt !== '' ? new \DateTimeImmutable($shippedAt, new \DateTimeZone('UTC')) : null,
            ];
        }

        return $out;
    }

    /** Installationsdatum des Plugins (Stichtag, wenn keiner eingestellt ist) */
    private function installedAt(): string
    {
        $value = $this->connection->fetchOne('SELECT installed_at FROM plugin WHERE name = :name', ['name' => 'NovaNachfrage']);

        return \is_string($value) && $value !== '' ? $value : (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }

    /**
     * Verkaufskanäle, in denen das Plugin eingeschaltet ist.
     *
     * @return list<string>
     */
    public function salesChannelIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->connection->fetchFirstColumn('SELECT LOWER(HEX(id)) FROM sales_channel WHERE active = 1');

        return $ids;
    }
}
