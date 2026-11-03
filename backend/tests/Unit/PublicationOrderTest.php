<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\Contracts\ContentRenderer;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\FilmRepository;
use Yinghua\Repositories\FilmScreeningRepository;
use Yinghua\Repositories\GeneralSettingsRepository;
use Yinghua\Repositories\NewsRepository;
use Yinghua\Repositories\OfferRepository;
use Yinghua\Repositories\PublicationRepository;
use Yinghua\Repositories\RecommendedRouteRepository;
use Yinghua\Repositories\SearchRepository;
use Yinghua\Repositories\SeoOverrideRepository;
use Yinghua\Repositories\SeoSettingsRepository;
use Yinghua\Services\ContentPublication;
use Yinghua\Services\PublicationSnapshotBuilder;
use Yinghua\Services\SeoPublication;

final class PublicationOrderTest extends TestCase
{
    public function testOfferAliasRowOrderDoesNotInvalidatePublishedContent(): void
    {
        $this->assertAliasOrderDoesNotInvalidate('offer_aliases');
    }

    public function testNewsAliasRowOrderDoesNotInvalidatePublishedContent(): void
    {
        $this->assertAliasOrderDoesNotInvalidate('news_aliases');
    }

    private function assertAliasOrderDoesNotInvalidate(string $table): void
    {
        $reverse = false;
        $database = $this->createStub(Database::class);
        $database->method('rows')->willReturnCallback(static function (string $sql) use (&$reverse, $table): array {
            if (!str_contains($sql, 'FROM ' . $table . ' ')) {
                return [];
            }
            $rows = [['path' => '/z.html', 'slug' => 'z'], ['path' => '/a.html', 'slug' => 'a']];
            return $reverse ? array_reverse($rows) : $rows;
        });
        $attractions = $this->createStub(AttractionRepository::class);
        $attractions->method('published')->willReturn([]);
        $attractions->method('aliases')->willReturn([]);
        $journal = $this->createStub(PublicationRepository::class);
        $journal->method('begin')->willReturn(1);
        $clock = $this->createStub(Clock::class);
        $clock->method('now')->willReturn(1792886400);
        $current = null;
        $renderer = $this->createStub(ContentRenderer::class);
        $renderer->method('version')->willReturn('fixed-renderer');
        $renderer->method('current')->willReturnCallback(static function () use (&$current): ?array {
            return $current;
        });
        $renderer->method('render')->willReturnCallback(static function (array $snapshot) use (&$current): void {
            $current = $snapshot;
        });
        $publication = new ContentPublication(
            $journal,
            $renderer,
            // 搜尋索引寫入與別名排序無關，用替身避免碰到資料庫。
            $this->createStub(SearchRepository::class),
            new PublicationSnapshotBuilder(
                new Config(),
                $clock,
                $attractions,
                new FilmRepository($database, new FilmScreeningRepository($database)),
                new NewsRepository($database),
                new OfferRepository($database),
                new RecommendedRouteRepository($database),
                new SeoPublication(new SeoSettingsRepository($database), new SeoOverrideRepository($database)),
                new GeneralSettingsRepository($database),
            ),
        );
        self::assertTrue($publication->synchronize());
        $generation = $publication->read()['generation'];
        // 發布延遲的診斷依 SECTIONS 比對區段，快照新增區段時清單也要跟著更新。
        self::assertSame([...PublicationSnapshotBuilder::SECTIONS, 'generation'], array_keys($publication->read()));
        $reverse = true;
        self::assertSame($generation, $publication->read()['generation']);
        self::assertTrue($publication->ready());
    }
}
