<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Versandprotokoll – Tabelle nova_nachfrage_log, eine Zeile je Bestellung (Primärschlüssel order_id).
 *
 * Status: sending (reserviert), sent, test (im Testmodus an die Testadresse), failed (Versand gescheitert,
 * bis zu MAX_ATTEMPTS neue Versuche), skipped (dauerhaft übersprungen, Grund in reason).
 * Der E-Mail-Hash dient nur dem Mindestabstand je Empfänger. Zeilen älter als RETENTION_DAYS werden
 * gelöscht – danach ist die Bestellung ohnehin älter als das Höchstalter und wird nie mehr angeschrieben.
 */
class SendLog
{
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_TEST = 'test';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const MAX_ATTEMPTS = 3;
    public const RETENTION_DAYS = 400;

    /** Reservierungen, die so lange auf „sending“ stehen, gelten als abgebrochen (Absturz während des Versands) */
    private const STALE_SENDING_MINUTES = 60;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Bestellung für den Versand reservieren. true = dieser Lauf darf senden.
     * Neue Bestellung: INSERT IGNORE. Gescheiterte (unter MAX_ATTEMPTS) oder im Live-Betrieb Test-Einträge:
     * per UPDATE übernehmen. So schreiben zwei gleichzeitige Läufe (Aufgabe und Konsole) nie doppelt.
     */
    public function claim(string $orderId, string $salesChannelId, string $emailHash, bool $testMode): bool
    {
        $now = self::now();
        $inserted = (int) $this->connection->executeStatement(
            'INSERT IGNORE INTO nova_nachfrage_log (order_id, sales_channel_id, email_hash, status, attempts, created_at)
             VALUES (:order, :sc, :hash, :status, 0, :now)',
            ['order' => Uuid::fromHexToBytes($orderId), 'sc' => Uuid::fromHexToBytes($salesChannelId), 'hash' => $emailHash, 'status' => self::STATUS_SENDING, 'now' => $now]
        );
        if ($inserted === 1) {
            return true;
        }
        $retry = [self::STATUS_FAILED];
        if (!$testMode) {
            $retry[] = self::STATUS_TEST;
        }

        return (int) $this->connection->executeStatement(
            'UPDATE nova_nachfrage_log SET attempts = (CASE WHEN status = :test THEN 0 ELSE attempts END), status = :sending, email_hash = :hash, updated_at = :now
              WHERE order_id = :order AND (
                    (status IN (:retry) AND attempts < :max)
                 OR (status = :sending AND COALESCE(updated_at, created_at) < :stale)
              )',
            [
                'test' => self::STATUS_TEST,
                'sending' => self::STATUS_SENDING,
                'hash' => $emailHash,
                'now' => $now,
                'order' => Uuid::fromHexToBytes($orderId),
                'retry' => $retry,
                'max' => self::MAX_ATTEMPTS,
                'stale' => (new \DateTimeImmutable('-' . self::STALE_SENDING_MINUTES . ' minutes'))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['retry' => \Doctrine\DBAL\ArrayParameterType::STRING]
        ) === 1;
    }

    public function finish(string $orderId, string $status, ?string $reason = null): void
    {
        $now = self::now();
        $this->connection->executeStatement(
            'UPDATE nova_nachfrage_log
                SET status = :status, reason = :reason, updated_at = :now,
                    attempts = attempts + (CASE WHEN :status IN (:sent, :test, :failed) THEN 1 ELSE 0 END),
                    sent_at = (CASE WHEN :status IN (:sent, :test) THEN :now ELSE sent_at END)
              WHERE order_id = :order',
            [
                'status' => $status,
                'reason' => $reason === null ? null : mb_substr($reason, 0, 255),
                'now' => $now,
                'sent' => self::STATUS_SENT,
                'test' => self::STATUS_TEST,
                'failed' => self::STATUS_FAILED,
                'order' => Uuid::fromHexToBytes($orderId),
            ]
        );
    }

    /**
     * Dauerhaft überspringen (ohne vorherige Reservierung). Bestehende Einträge bleiben unverändert.
     */
    public function skip(string $orderId, string $salesChannelId, string $emailHash, string $reason): void
    {
        $this->connection->executeStatement(
            'INSERT IGNORE INTO nova_nachfrage_log (order_id, sales_channel_id, email_hash, status, reason, attempts, created_at)
             VALUES (:order, :sc, :hash, :status, :reason, 0, :now)',
            [
                'order' => Uuid::fromHexToBytes($orderId),
                'sc' => Uuid::fromHexToBytes($salesChannelId),
                'hash' => $emailHash,
                'status' => self::STATUS_SKIPPED,
                'reason' => $reason,
                'now' => self::now(),
            ]
        );
    }

    /**
     * Letzter Versand an diesen Empfänger (alle Verkaufskanäle). Im Testmodus zählen Test-Mails mit.
     */
    public function lastSentTo(string $emailHash, bool $testMode): ?\DateTimeImmutable
    {
        $statuses = $testMode ? [self::STATUS_SENT, self::STATUS_TEST] : [self::STATUS_SENT];
        $value = $this->connection->fetchOne(
            'SELECT MAX(sent_at) FROM nova_nachfrage_log WHERE email_hash = :hash AND status IN (:statuses)',
            ['hash' => $emailHash, 'statuses' => $statuses],
            ['statuses' => \Doctrine\DBAL\ArrayParameterType::STRING]
        );

        return \is_string($value) && $value !== '' ? new \DateTimeImmutable($value) : null;
    }

    public function cleanup(): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM nova_nachfrage_log WHERE created_at < :limit',
            ['limit' => (new \DateTimeImmutable('-' . self::RETENTION_DAYS . ' days'))->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );
    }

    /**
     * Für Administration und Konsole: letzte Einträge mit Bestellnummer.
     *
     * @return list<array{orderId: string, orderNumber: string|null, salesChannel: string|null, status: string, reason: string|null, sentAt: string|null, createdAt: string}>
     */
    public function recent(int $limit = 20): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(l.order_id)) AS order_id, o.order_number, sct.name AS sales_channel, l.status, l.reason, l.sent_at, l.created_at
               FROM nova_nachfrage_log l
               LEFT JOIN `order` o ON o.id = l.order_id AND o.version_id = :live
               LEFT JOIN sales_channel_translation sct ON sct.sales_channel_id = l.sales_channel_id AND sct.language_id = :lang
              ORDER BY COALESCE(l.sent_at, l.updated_at, l.created_at) DESC
              LIMIT ' . max(1, min(200, $limit)),
            ['live' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'lang' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'orderId' => (string) $row['order_id'],
                'orderNumber' => \is_string($row['order_number'] ?? null) ? $row['order_number'] : null,
                'salesChannel' => \is_string($row['sales_channel'] ?? null) ? $row['sales_channel'] : null,
                'status' => (string) $row['status'],
                'reason' => \is_string($row['reason'] ?? null) ? $row['reason'] : null,
                'sentAt' => \is_string($row['sent_at'] ?? null) ? $row['sent_at'] : null,
                'createdAt' => (string) $row['created_at'],
            ];
        }

        return $out;
    }

    /**
     * @return array{sent: int, test: int, failed: int, skipped: int, sent30: int}
     */
    public function stats(): array
    {
        $rows = $this->connection->fetchAllKeyValue('SELECT status, COUNT(*) FROM nova_nachfrage_log GROUP BY status');
        $sent30 = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM nova_nachfrage_log WHERE status = :sent AND sent_at >= :since',
            ['sent' => self::STATUS_SENT, 'since' => (new \DateTimeImmutable('-30 days'))->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );

        return [
            'sent' => (int) ($rows[self::STATUS_SENT] ?? 0),
            'test' => (int) ($rows[self::STATUS_TEST] ?? 0),
            'failed' => (int) ($rows[self::STATUS_FAILED] ?? 0),
            'skipped' => (int) ($rows[self::STATUS_SKIPPED] ?? 0),
            'sent30' => $sent30,
        ];
    }

    private static function now(): string
    {
        return (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
    }
}
