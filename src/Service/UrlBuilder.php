<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Absolute Links für Mails (ohne Request): Domain des Verkaufskanals (passende Sprache, https bevorzugt)
 * plus kanonische SEO-URL der Produktseite, ersatzweise /detail/{id}.
 */
class UrlBuilder
{
    /** @var array<string, string> */
    private array $baseCache = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function reset(): void
    {
        $this->baseCache = [];
    }

    public function base(string $salesChannelId, string $languageId): string
    {
        $key = $salesChannelId . '|' . $languageId;
        if (isset($this->baseCache[$key])) {
            return $this->baseCache[$key];
        }
        $url = $this->connection->fetchOne(
            'SELECT url FROM sales_channel_domain WHERE sales_channel_id = :sc
              ORDER BY (language_id = :lang) DESC, (url LIKE \'https%\') DESC, created_at ASC LIMIT 1',
            ['sc' => Uuid::fromHexToBytes($salesChannelId), 'lang' => Uuid::fromHexToBytes($languageId)]
        );
        $base = \is_string($url) ? rtrim($url, '/') : rtrim((string) ($_SERVER['APP_URL'] ?? $_ENV['APP_URL'] ?? ''), '/');

        return $this->baseCache[$key] = $base;
    }

    /**
     * @param list<string> $productIds
     *
     * @return array<string, string> Produkt-ID → absolute URL
     */
    public function productUrls(array $productIds, string $salesChannelId, string $languageId): array
    {
        if ($productIds === []) {
            return [];
        }
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(foreign_key)), seo_path_info FROM seo_url
              WHERE foreign_key IN (:ids) AND route_name = :route AND sales_channel_id = :sc AND language_id = :lang
                AND is_canonical = 1 AND is_deleted = 0',
            [
                'ids' => array_map(static fn (string $id): string => Uuid::fromHexToBytes($id), $productIds),
                'route' => 'frontend.detail.page',
                'sc' => Uuid::fromHexToBytes($salesChannelId),
                'lang' => Uuid::fromHexToBytes($languageId),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );
        $base = $this->base($salesChannelId, $languageId);
        $out = [];
        foreach ($productIds as $id) {
            $path = $rows[$id] ?? null;
            $out[$id] = ReviewLink::productUrl($base, \is_string($path) && $path !== '' ? $path : '/detail/' . $id);
        }

        return $out;
    }
}
