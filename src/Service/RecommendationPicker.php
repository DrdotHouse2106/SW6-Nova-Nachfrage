<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

/**
 * Auswahl der „ähnlichen Produkte“ (reine Logik).
 *
 * Die Kandidaten kommen in Prioritätsreihenfolge (erst manuelle Cross-Selling-Listen, dann nach gemeinsamen
 * spezifischen Kategorien gewichtete Artikel). Gruppe = Hauptartikel-ID (bei Varianten die
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
     * Spezifische Kategorien eines Artikels: direkt zugeordnet, ohne Hauptnavigation und ohne Kategorien,
     * die Oberkategorie einer anderen zugeordneten Kategorie sind.
     *
     * @param array<string, string> $paths Kategorie-ID → Pfad der Oberkategorien („|a|b|“)
     *
     * @return list<string>
     */
    public static function specificCategories(array $paths, ?string $rootId): array
    {
        $ancestors = [];
        foreach ($paths as $path) {
            foreach (array_filter(explode('|', $path)) as $id) {
                $ancestors[$id] = true;
            }
        }
        $out = [];
        foreach (array_keys($paths) as $id) {
            if ($id !== $rootId && !isset($ancestors[$id])) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /**
     * Nur Kandidaten mit mindestens einer gemeinsamen Kategorie, die meisten gemeinsamen zuerst;
     * bei Gleichstand bleibt die bisherige Reihenfolge (Cross-Selling vor Bestsellern).
     *
     * @param list<string> $candidateIds
     * @param array<string, list<string>> $categoriesOf Kandidat-ID → direkt zugeordnete Kategorien
     * @param list<string> $specific spezifische Kategorien der gekauften Artikel
     *
     * @return list<string>
     */
    public static function rank(array $candidateIds, array $categoriesOf, array $specific): array
    {
        $scored = [];
        foreach (array_values(array_unique($candidateIds)) as $pos => $id) {
            $score = \count(array_intersect($categoriesOf[$id] ?? [], $specific));
            if ($score > 0) {
                $scored[] = [$score, $pos, $id];
            }
        }
        usort($scored, static fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_column($scored, 2);
    }
}
