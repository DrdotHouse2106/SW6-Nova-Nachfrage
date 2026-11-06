<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

/**
 * Auswahl der „ähnlichen Produkte“ (reine Logik).
 *
 * Die Kandidaten kommen in Prioritätsreihenfolge (nach gemeinsamen spezifischen Kategorien gewichtet, bei
 * Gleichstand Cross-Sellings vor Bestsellern). Gruppe = Hauptartikel-ID (bei Varianten die
 * parentId, sonst die eigene ID): Je Gruppe höchstens ein Vorschlag, und keine Gruppe, aus der schon
 * gekauft wurde – also keine andere Farbe/Größe desselben Artikels.
 */
final class RecommendationPicker
{
    /**
     * @param list<string> $candidateIds Kandidaten in Prioritätsreihenfolge (dürfen doppelt vorkommen)
     * @param array<string, string> $groupOf Kandidat-ID → Gruppe; fehlt eine ID, ist sie nicht verfügbar
     * @param list<string> $excludedGroups Gruppen der gekauften Artikel
     *
     * @return list<string>
     */
    public static function pick(array $candidateIds, array $groupOf, array $excludedGroups, int $max): array
    {
        if ($max <= 0) {
            return [];
        }
        $usedGroups = array_fill_keys($excludedGroups, true);
        $out = [];
        foreach ($candidateIds as $id) {
            $group = $groupOf[$id] ?? null;
            if ($group === null || isset($usedGroups[$group])) {
                continue;
            }
            $usedGroups[$group] = true;
            $out[] = $id;
            if (\count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    /**
     * Spezifische Kategorien eines Artikels: direkt zugeordnet, im Navigationsbaum des Verkaufskanals, nicht die
     * Hauptnavigation selbst, nicht ausgeschlossen und keine Oberkategorie einer anderen zugeordneten Kategorie.
     *
     * @param array<string, string> $paths Kategorie-ID → Pfad der Oberkategorien („|a|b|“)
     * @param list<string> $ignored ausgeschlossene Sammelkategorien (z. B. „Alle Artikel“)
     *
     * @return list<string>
     */
    public static function specificCategories(array $paths, ?string $rootId, array $ignored = []): array
    {
        $paths = array_filter($paths, static fn (string $path, string $id): bool => $id !== $rootId
            && !\in_array($id, $ignored, true)
            && ($rootId === null || str_contains($path, '|' . $rootId . '|')), \ARRAY_FILTER_USE_BOTH);
        $ancestors = [];
        foreach ($paths as $path) {
            foreach (array_filter(explode('|', $path)) as $id) {
                $ancestors[$id] = true;
            }
        }

        return array_values(array_filter(array_keys($paths), static fn (string $id): bool => !isset($ancestors[$id])));
    }

    /**
     * Gewicht je Kategorie: Jeder gekaufte Artikel verteilt 1 auf seine spezifischen Kategorien. Ein Teil, das zu
     * vielen Fahrzeugen passt, zählt also je Fahrzeug wenig. Kategorien unter der Hälfte des Höchstgewichts entfallen.
     *
     * @param list<list<string>> $perProduct spezifische Kategorien je gekauftem Artikel
     *
     * @return array<string, float>
     */
    public static function weights(array $perProduct): array
    {
        $weights = [];
        foreach ($perProduct as $categories) {
            foreach ($categories as $id) {
                $weights[$id] = ($weights[$id] ?? 0.0) + 1 / \count($categories);
            }
        }
        $limit = $weights === [] ? 0.0 : max($weights) / 2;

        return array_filter($weights, static fn (float $w): bool => $w >= $limit);
    }

    /**
     * Nur Kandidaten mit mindestens einer gewichteten Kategorie, höchste Summe zuerst; bei Gleichstand bleibt die
     * bisherige Reihenfolge (Cross-Selling vor Bestsellern).
     *
     * @param list<string> $candidateIds
     * @param array<string, list<string>> $categoriesOf Kandidat-ID → direkt zugeordnete Kategorien
     * @param array<string, float> $weights
     *
     * @return list<string>
     */
    public static function rank(array $candidateIds, array $categoriesOf, array $weights): array
    {
        $scored = [];
        foreach (array_values(array_unique($candidateIds)) as $pos => $id) {
            $score = array_sum(array_intersect_key($weights, array_flip($categoriesOf[$id] ?? [])));
            if ($score > 0) {
                $scored[] = [$score, $pos, $id];
            }
        }
        usort($scored, static fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_column($scored, 2);
    }
}
