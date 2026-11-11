<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Widersprüche (Abmeldungen) – Tabelle nova_nachfrage_optout.
 *
 * Gespeichert werden nur der Hash der E-Mail-Adresse (EmailHash), Zeitpunkt, Quelle und – wenn die
 * Abmeldung nur für einen Verkaufskanal gilt – dessen ID. channel_key '' bedeutet „alle Verkaufskanäle“.
 * Einträge werden nie automatisch gelöscht: Der Widerspruch muss dauerhaft beachtet werden, auch bei
 * neuen Bestellungen und Gastbestellungen.
 */
class OptOutStore
{
    public const SOURCE_LINK = 'link';
    public const SOURCE_ONE_CLICK = 'one_click';
    public const SOURCE_ADMIN = 'admin';
    public const SOURCE_CLI = 'cli';
    public const SOURCE_CHECKOUT = 'checkout';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param string|null $salesChannelId null = für alle Verkaufskanäle
     *
     * @return bool true = neu eingetragen, false = gab es schon
     */
    public function addHash(string $emailHash, string $source, ?string $salesChannelId): bool
    {
        if (!EmailHash::isHash($emailHash)) {
            return false;
        }

        return (int) $this->connection->executeStatement(
            'INSERT IGNORE INTO nova_nachfrage_optout (id, email_hash, channel_key, sales_channel_id, source, created_at)
             VALUES (:id, :hash, :key, :sc, :source, :now)',
            [
                'id' => Uuid::randomBytes(),
                'hash' => $emailHash,
                'key' => $salesChannelId === null ? '' : strtolower($salesChannelId),
                'sc' => $salesChannelId === null ? null : Uuid::fromHexToBytes($salesChannelId),
                'source' => mb_substr($source, 0, 32),
                'now' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        ) > 0;
    }

    public function addEmail(string $email, string $source, ?string $salesChannelId = null): bool
    {
        return $this->addHash(EmailHash::hash($email), $source, $salesChannelId);
    }

    /**
     * Widerspruch für diesen Empfänger in diesem Verkaufskanal? Gezählt werden kanalübergreifende
     * Einträge und solche für genau diesen Kanal.
     */
    public function isOptedOut(string $emailHash, string $salesChannelId): bool
    {
        return $this->connection->fetchOne(
            'SELECT 1 FROM nova_nachfrage_optout WHERE email_hash = :hash AND channel_key IN (\'\', :key) LIMIT 1',
            ['hash' => $emailHash, 'key' => strtolower($salesChannelId)]
        ) !== false;
    }

    /**
     * Steht die Adresse in Shopwares Newsletter-Empfängern als abgemeldet (Status optOut)?
     * Meldet sich jemand später wieder zum Newsletter an, ist der Status nicht mehr optOut. Der Vergleich
     * läuft über die Spaltensortierung der Datenbank (Shopware: utf8mb4_unicode_ci, Groß-/Kleinschreibung egal).
     */
    public function newsletterOptedOut(string $email, string $salesChannelId, bool $allChannels): bool
    {
        $email = EmailHash::normalize($email);
        if ($email === '') {
            return false;
        }
        $sql = 'SELECT 1 FROM newsletter_recipient WHERE email = :email AND status = :status';
        $params = ['email' => $email, 'status' => 'optOut'];
        if (!$allChannels) {
            $sql .= ' AND sales_channel_id = :sc';
            $params['sc'] = Uuid::fromHexToBytes($salesChannelId);
        }

        return $this->connection->fetchOne($sql . ' LIMIT 1', $params) !== false;
    }

    /** Gibt es irgendeinen Eintrag (gleich welcher Kanal)? */
    public function exists(string $emailHash): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM nova_nachfrage_optout WHERE email_hash = :hash LIMIT 1', ['hash' => $emailHash]) !== false;
    }

    public function removeHash(string $emailHash): int
    {
        return (int) $this->connection->executeStatement('DELETE FROM nova_nachfrage_optout WHERE email_hash = :hash', ['hash' => $emailHash]);
    }

    public function count(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM nova_nachfrage_optout');
    }

    /**
     * Für Administration und Konsole: nur gekürzter Hash, Datum, Quelle, Kanal – keine Adressen.
     *
     * @return list<array{hash: string, createdAt: string, source: string, salesChannel: string|null}>
     */
    public function list(int $limit = 50, int $offset = 0): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT o.email_hash, o.created_at, o.source, sct.name AS sales_channel
               FROM nova_nachfrage_optout o
               LEFT JOIN sales_channel_translation sct ON sct.sales_channel_id = o.sales_channel_id AND sct.language_id = :lang
              ORDER BY o.created_at DESC
              LIMIT ' . max(1, min(500, $limit)) . ' OFFSET ' . max(0, $offset),
            ['lang' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'hash' => EmailHash::short((string) $row['email_hash']),
                'createdAt' => (string) $row['created_at'],
                'source' => (string) $row['source'],
                'salesChannel' => \is_string($row['sales_channel'] ?? null) ? $row['sales_channel'] : null,
            ];
        }

        return $out;
    }
}
