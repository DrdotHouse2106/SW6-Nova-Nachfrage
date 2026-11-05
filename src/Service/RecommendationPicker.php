<?php declare(strict_types=1);

namespace Nova\Nachfrage\Service;

/**
 * Auswahl der „ähnlichen Produkte“ (reine Logik).
 *
 * Die Kandidaten kommen in Prioritätsreihenfolge (erst Cross-Sellings der gekauften Artikel in deren
 * Reihenfolge, dann Artikel aus denselben Kategorien). Gruppe = Hauptartikel-ID (bei Varianten die
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
}
