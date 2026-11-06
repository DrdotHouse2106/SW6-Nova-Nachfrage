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
        // root: Hauptnavigation, cx liegt unter fahrzeuge, kugeln ist eigenständig
        $paths = ['root' => '', 'fahrzeuge' => '|root|', 'cx' => '|root|fahrzeuge|', 'kugeln' => '|root|'];
        self::assertSame(['cx', 'kugeln'], RecommendationPicker::specificCategories($paths, 'root'));
    }

    public function testRankByCommonCategories(): void
    {
        $categories = ['gs' => ['kugeln', 'gs'], 'cx' => ['kugeln', 'cx'], 'dichtung' => ['kaffee'], 'cxteil' => ['cx']];
        self::assertSame(['cx', 'gs', 'cxteil'], RecommendationPicker::rank(['gs', 'dichtung', 'cx', 'cxteil'], $categories, ['kugeln', 'cx']));
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
