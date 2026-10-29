<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Policies\NeighborSelection;

class NeighborSelectionTest extends TestCase
{
    public function testNewsNeighborsStayInCategoryAndUseStablePublicationOrder(): void
    {
        $map = NeighborSelection::project(['news' => [
            ['id' => 'c','slug' => 'city','url' => '/news/city.html','type' => 'city','publishAt' => '2026-10-29','title' => '城市'],
            ['id' => 'b','slug' => 'event-b','url' => '/news/event-b.html','type' => 'event','publishAt' => '2026-10-28','title' => '乙'],
            ['id' => 'a','slug' => 'event-a','url' => '/news/event-a.html','type' => 'event','publishAt' => '2026-10-28','title' => '甲'],
        ]]);
        self::assertNull($map['/news/city.html']['previous']);
        self::assertNull($map['/news/city.html']['next']);
        self::assertNull($map['/news/event-a.html']['previous']);
        self::assertSame('event-b', $map['/news/event-a.html']['next']['slug']);
        self::assertSame('event-a', $map['/news/event-b.html']['previous']['slug']);
        self::assertNull($map['/news/event-b.html']['next']);
    }

    public function testMuseumAndPlacesUseTheirRealRoutesAndCategory(): void
    {
        $map = NeighborSelection::project(['items' => [
            ['id' => 'a','slug' => 'museum','isMuseum' => true,'category' => '景點','publishAt' => '2026-10-29','name' => '影像博物館'],
            ['id' => 'b','slug' => 'park','category' => '景點','publishAt' => '2026-10-28','name' => '公園'],
            ['id' => 'c','slug' => 'food','category' => '美食','publishAt' => '2026-10-27','name' => '美食'],
        ]]);
        self::assertSame('/museum.html', $map['/places/park.html']['previous']['url']);
        self::assertSame('/places/park.html', $map['/museum.html']['next']['url']);
        self::assertNull($map['/places/food.html']['previous']);
        self::assertNull($map['/places/food.html']['next']);
    }

    public function testFilmsAreNotFilteredByCurrentWeekAndEmptySetHasNoLinks(): void
    {
        self::assertSame([], NeighborSelection::project([]));
        $map = NeighborSelection::project(['films' => [
            ['id' => 'a','slug' => 'old','url' => '/films/old.html','publishAt' => '2020-01-01','title' => '舊片'],
            ['id' => 'b','slug' => 'new','url' => '/films/new.html','publishAt' => '2026-10-29','title' => '新片'],
        ]]);
        self::assertSame('old', $map['/films/new.html']['next']['slug']);
        self::assertSame('new', $map['/films/old.html']['previous']['slug']);
    }
}
