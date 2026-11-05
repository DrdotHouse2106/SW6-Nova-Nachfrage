<?php declare(strict_types=1);

namespace Nova\Nachfrage\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * Tabellen von Nova Nachfrage:
 * - nova_nachfrage_optout: Widersprüche, nur Hash der E-Mail-Adresse (channel_key '' = alle Kanäle)
 * - nova_nachfrage_log: Versandprotokoll, eine Zeile je Bestellung
 */
class Migration1793900000NovaNachfrage extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1793900000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_nachfrage_optout` (
                `id` BINARY(16) NOT NULL,
                `email_hash` CHAR(64) NOT NULL,
                `channel_key` VARCHAR(32) NOT NULL DEFAULT \'\',
                `sales_channel_id` BINARY(16) NULL,
                `source` VARCHAR(32) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.nova_nachfrage_optout.hash_channel` (`email_hash`, `channel_key`),
                KEY `idx.nova_nachfrage_optout.created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `nova_nachfrage_log` (
                `order_id` BINARY(16) NOT NULL,
                `sales_channel_id` BINARY(16) NOT NULL,
                `email_hash` CHAR(64) NOT NULL,
                `status` VARCHAR(16) NOT NULL,
                `reason` VARCHAR(255) NULL,
                `attempts` INT NOT NULL DEFAULT 0,
                `sent_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`order_id`),
                KEY `idx.nova_nachfrage_log.email_sent` (`email_hash`, `status`, `sent_at`),
                KEY `idx.nova_nachfrage_log.created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
