<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Plugin-Einstellungen je Verkaufskanal (NovaNachfrage.config.*). Nicht gesetzte Werte erben die
 * Einstellung „Alle Verkaufskanäle“, sonst gilt der Standard.
 */
class Settings
{
    private const PREFIX = 'NovaNachfrage.config.';

    public function __construct(private readonly SystemConfigService $config)
    {
    }

    public function active(string $salesChannelId): bool
    {
        return $this->bool('active', $salesChannelId, false);
    }

    public function policy(string $salesChannelId): DuePolicy
    {
        return new DuePolicy(
            $this->int('daysAfterShipping', $salesChannelId, 10, 0, 365),
            $this->int('maxAgeDays', $salesChannelId, 60, 1, 730),
        );
    }

    /** Stichtag als „Y-m-d H:i:s“ (UTC) oder null = Installationsdatum verwenden */
    public function notBefore(string $salesChannelId): ?string
    {
        $value = $this->string('notBefore', $salesChannelId);
        if ($value === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($value))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    public function onlyStorefrontOrders(string $salesChannelId): bool
    {
        return $this->bool('onlyStorefrontOrders', $salesChannelId, true);
    }

    public function minDaysBetweenMails(string $salesChannelId): int
    {
        return $this->int('minDaysBetweenMails', $salesChannelId, 30, 0, 3650);
    }

    public function maxRecommendations(string $salesChannelId): int
    {
        return $this->int('maxRecommendations', $salesChannelId, 4, 0, 12);
    }

    /** @return list<string> */
    public function ignoredCategories(string $salesChannelId): array
    {
        $value = $this->config->get(self::PREFIX . 'ignoredCategories', $salesChannelId);

        return \is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    public function extraText(string $salesChannelId): string
    {
        return $this->string('extraText', $salesChannelId);
    }

    public function checkoutNotice(string $salesChannelId): string
    {
        return $this->string('checkoutNotice', $salesChannelId);
    }

    public function optOutAllChannels(string $salesChannelId): bool
    {
        return $this->bool('optOutAllChannels', $salesChannelId, true);
    }

    public function respectNewsletterOptOut(string $salesChannelId): bool
    {
        return $this->bool('respectNewsletterOptOut', $salesChannelId, true);
    }

    public function listUnsubscribeHeader(string $salesChannelId): bool
    {
        return $this->bool('listUnsubscribeHeader', $salesChannelId, true);
    }

    public function testMode(string $salesChannelId): bool
    {
        return $this->bool('testMode', $salesChannelId, false);
    }

    public function testRecipient(string $salesChannelId): string
    {
        return EmailHash::normalize($this->string('testRecipient', $salesChannelId));
    }

    /** Link zur Google-Bewertung; nur https, sonst leer (Block entfällt) */
    public function googleReviewUrl(string $salesChannelId): string
    {
        return self::httpsUrl($this->string('googleReviewUrl', $salesChannelId));
    }

    public function googleReviewText(string $salesChannelId): string
    {
        return $this->string('googleReviewText', $salesChannelId);
    }

    /** Nur gültige https-URLs, sonst '' */
    public static function httpsUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || !str_starts_with(strtolower($url), 'https://') || filter_var($url, \FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return $url;
    }

    private function bool(string $key, ?string $salesChannelId, bool $default): bool
    {
        $value = $this->config->get(self::PREFIX . $key, $salesChannelId);

        return $value === null ? $default : (bool) $value;
    }

    private function int(string $key, ?string $salesChannelId, int $default, int $min, int $max): int
    {
        $value = $this->config->get(self::PREFIX . $key, $salesChannelId);
        $int = is_numeric($value) ? (int) $value : $default;

        return max($min, min($max, $int));
    }

    private function string(string $key, ?string $salesChannelId): string
    {
        $value = $this->config->get(self::PREFIX . $key, $salesChannelId);

        return \is_string($value) ? trim($value) : '';
    }
}
