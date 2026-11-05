<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

/**
 * Links zur Produktseite und zum Bewertungsformular (reine Logik).
 *
 * Shopware 6.7 öffnet den Reiter „Bewertungen“ nicht über die URL (Bootstrap-Reiter reagieren nicht auf
 * einen Anker, und der Parameter „success“ würde fälschlich „Danke für Ihre Bewertung“ anzeigen). Das
 * Plugin hängt deshalb den Anker #nova-review an und liefert auf der Produktseite ein kleines Skript aus,
 * das bei diesem Anker den Reiter „Bewertungen“ öffnet, das Formular aufklappt und hinscrollt.
 */
final class ReviewLink
{
    public const ANCHOR = 'nova-review';

    public static function productUrl(string $baseUrl, string $seoPath): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($seoPath, '/');
    }

    public static function reviewUrl(string $productUrl): string
    {
        $pos = strpos($productUrl, '#');
        if ($pos !== false) {
            $productUrl = substr($productUrl, 0, $pos);
        }

        return $productUrl . '#' . self::ANCHOR;
    }
}
