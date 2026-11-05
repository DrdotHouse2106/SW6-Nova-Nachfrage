<?php declare(strict_types=1);

namespace Nova\Nachfrage\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Stündlich: fällige Nachfrage-Mails versenden (höchstens 100 je Lauf), altes Versandprotokoll aufräumen.
 */
class SendTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'nova_nachfrage.send';
    }

    public static function getDefaultInterval(): int
    {
        return self::HOURLY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
