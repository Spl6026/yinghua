<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\{RecommendedRoute, RecommendedRouteInput};
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Policies\RecommendedRouteSelection;

final class RecommendedRouteTest extends TestCase
{
    private const string PLACE = '11111111-1111-4111-8111-111111111111';

    public function testOptionalFieldsAndOrdering(): void
    {
        $input = RecommendedRouteInput::from(['title' => '城市散步','stops' => [['attractionId' => self::PLACE]]], true);
        self::assertSame('', $input->summary);
        self::assertNull($input->durationMinutes);
        self::assertNull($input->stops[0]->suggestedTime);
        self::assertSame('draft', $input->status->value);
    }

    public function testDuplicateStopsRejected(): void
    {
        $this->expectException(ApplicationError::class);
        RecommendedRouteInput::from(['title' => '城市散步','stops' => [['attractionId' => self::PLACE],['attractionId' => self::PLACE]]], true);
    }

    public function testInvalidTimeRejected(): void
    {
        $this->expectException(ApplicationError::class);
        RecommendedRouteInput::from(['title' => '城市散步','stops' => [['attractionId' => self::PLACE,'suggestedTime' => '24:00']]], true);
    }

    public function testEmptyRouteRejectedEvenForDraft(): void
    {
        $this->expectException(ApplicationError::class);
        RecommendedRouteInput::from(['title' => '城市散步','stops' => []], true);
    }

    public function testPublicProjectionUsesPublishedAttractionsOnly(): void
    {
        $route = new RecommendedRoute('route', 'route', '城市散步', '', '', null, 0, 'published', 1, null, [
            ['attractionId' => 'missing','sortOrder' => 0,'suggestedTime' => null,'note' => '','name' => '下架','available' => false],
            ['attractionId' => self::PLACE,'sortOrder' => 1,'suggestedTime' => '10:30','note' => '休息','name' => '舊名稱','available' => true],
        ]);
        self::assertSame([], RecommendedRouteSelection::project([$route], []));
        $items = RecommendedRouteSelection::project([$route], [['id' => self::PLACE,'name' => '目前名稱','url' => '/museum.html']]);
        self::assertCount(1, $items);
        self::assertArrayNotHasKey('version', $items[0]);
        self::assertSame([['attractionId' => self::PLACE,'sortOrder' => 0,'suggestedTime' => '10:30','note' => '休息','name' => '目前名稱','available' => true,'url' => '/museum.html']], $items[0]['stops']);
    }
}
