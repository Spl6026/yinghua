<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\ScreeningWindow;
use Yinghua\Policies\HomeSelection;
use Yinghua\Services\ContentPublication;
use Yinghua\Services\PublicHome;

class HomeSelectionTest extends TestCase
{
    public function testHomeSelectsThreeDistinctWeeklyFilmsAndSameOrderedNews(): void
    {
        $window = ScreeningWindow::current((int) strtotime('2026-10-22T12:00:00+08:00'));
        $snapshot = ['items' => [['id' => 'museum','isMuseum' => true],['id' => 'featured','featured' => true]],
            'films' => [],'news' => [['id' => 'newest'],['id' => 'second'],['id' => 'third'],['id' => 'older']]];
        foreach ([4,3,2,1] as $day) {
            $snapshot['films'][] = ['id' => 'film-' . $day,'screenings' => [
                ['id' => 'outside','startsAt' => $window->end->format(DATE_ATOM)],
                ['id' => 'inside','startsAt' => $window->start->modify('+' . $day . ' days')->format(DATE_ATOM)]]];
        }
        $result = HomeSelection::fromSnapshot($snapshot, $window);
        self::assertSame('museum', $result->museum['id']);
        self::assertSame('featured', $result->featured['id']);
        self::assertSame(4, $result->filmTotal);
        self::assertSame(['film-1','film-2','film-3'], array_column($result->films, 'id'));
        self::assertCount(1, $result->films[0]['screenings']);
        self::assertSame(['newest','second','third'], array_column($result->news, 'id'));
    }

    public function testEmptyHomeHasNoPrototypeFallbackAndApiReadsExactlyOnce(): void
    {
        $home = HomeSelection::fromSnapshot(['items' => [],'news' => [],'films' => []], ScreeningWindow::current(0));
        self::assertNull($home->museum);
        self::assertNull($home->featured);
        self::assertSame([], $home->news);
        self::assertSame([], $home->films);
        $publication = $this->createMock(ContentPublication::class);
        $publication->expects(self::once())->method('read')->willReturn(['home' => $home->toArray(),'generation' => 'same-version']);
        self::assertSame([...$home->toArray(),'generation' => 'same-version'], (new PublicHome($publication))->get());
    }
}
