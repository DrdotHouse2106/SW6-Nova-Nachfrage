<?php declare(strict_types=1);

namespace Nova\Nachfrage\Tests\Unit;

use Nova\Nachfrage\Service\RecommendationPicker;
use Nova\Nachfrage\Service\ReviewLink;
use Nova\Nachfrage\Service\Settings;
use PHPUnit\Framework\TestCase;

final class RecommendationPickerTest extends TestCase
{
    public function testKeepsPriorityAndMax(): void
    {
        $groups = ['a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd'];
        self::assertSame(['a', 'b'], RecommendationPicker::pick(['a', 'b', 'c', 'd'], $groups, [], 2));
        self::assertSame([], RecommendationPicker::pick(['a'], $groups, [], 0));
    }

    public function testSkipsPurchasedGroupsVariantsAndUnavailable(): void
    {
        // v1/v2: Varianten von p (gekauft), x: nicht mehr verfügbar (fehlt in groupOf), y1/y2: Varianten desselben Artikels
        $groups = ['v1' => 'p', 'v2' => 'p', 'y1' => 'y', 'y2' => 'y', 'z' => 'z'];
        self::assertSame(['y1', 'z'], RecommendationPicker::pick(['v1', 'x', 'y1', 'y2', 'y1', 'v2', 'z'], $groups, ['p'], 4));
    }

    public function testSpecificCategories(): void
    {
        // fk: Hauptnavigation, ft: anderer Verkaufskanal, alle: Sammelkategorie, fz/cx: Oberkategorie und Fahrzeug
        $paths = ['fk' => '', 'fz' => '|fk|', 'cx' => '|fk|fz|', 'alle' => '|fk|', 'kugeln' => '|ft|fw|'];
        self::assertSame(['cx'], RecommendationPicker::specificCategories($paths, 'fk', ['alle']));
    }

    public function testWeightsAndRank(): void
    {
        // Federkugel CX vorne + hinten + Druckspeicher für 7 Modelle: nur CX zählt
        $all = ['bx', 'cx', 'gs', 'ds', 'sm', 'xantia', 'xm'];
        $weights = RecommendationPicker::weights([['cx'], ['cx'], $all]);
        self::assertSame(['cx'], array_keys($weights));
        $categories = ['gs35' => ['gs'], 'oring' => ['cx', 'gs'], 'cxteil' => ['cx'], 'kaffee' => ['melitta']];
        self::assertSame(['oring', 'cxteil'], RecommendationPicker::rank(['gs35', 'oring', 'kaffee', 'cxteil'], $categories, $weights));
        // Nur der Druckspeicher gekauft: alle Modelle gleich gewichtet
        self::assertCount(7, RecommendationPicker::weights([$all]));
        self::assertSame([], RecommendationPicker::weights([]));
    }

    public function testReviewLink(): void
    {
        self::assertSame('https://shop.example.com/produkt-1/ITEM-001', ReviewLink::productUrl('https://shop.example.com/', '/produkt-1/ITEM-001'));
        self::assertSame('https://shop.example.com/a#nova-review', ReviewLink::reviewUrl('https://shop.example.com/a'));
        self::assertSame('https://shop.example.com/a#nova-review', ReviewLink::reviewUrl('https://shop.example.com/a#alt'));
    }

    public function testHttpsUrl(): void
    {
        self::assertSame('https://g.page/r/abc/review', Settings::httpsUrl(' https://g.page/r/abc/review '));
        self::assertSame('', Settings::httpsUrl('http://g.page/r/abc/review'));
        self::assertSame('', Settings::httpsUrl('javascript:alert(1)'));
        self::assertSame('', Settings::httpsUrl('https://'));
        self::assertSame('', Settings::httpsUrl(''));
    }
}
