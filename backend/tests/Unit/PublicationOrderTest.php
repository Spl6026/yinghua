<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\Contracts\ContentRenderer;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\NewsRepository;
use Yinghua\Repositories\OfferRepository;
use Yinghua\Repositories\PublicationRepository;
use Yinghua\Services\ContentPublication;

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
        $publication = new ContentPublication($attractions, $journal, $renderer, new Config(), clock: $clock, news: new NewsRepository($database), offers: new OfferRepository($database));
        self::assertTrue($publication->synchronize());
        $generation = $publication->read()['generation'];
        $reverse = true;
        self::assertSame($generation, $publication->read()['generation']);
        self::assertTrue($publication->ready());
    }
}
