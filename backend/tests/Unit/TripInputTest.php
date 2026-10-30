<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\TripInput;
use Yinghua\Exceptions\ApplicationError;

final class TripInputTest extends TestCase
{
    private const string PLACE = '11111111-1111-4111-8111-111111111111';

    public function testEmptyOptionalFieldsAndEmptyTrip(): void
    {
        $input = TripInput::from(['title' => '','tripDate' => null,'items' => []], true);
        self::assertSame('', $input->title);
        self::assertNull($input->tripDate);
        self::assertSame([], $input->items);
        self::assertSame('我的旅行計畫', TripInput::from([], true)->title);
    }

    public function testArrayOrderAndOptionalTimes(): void
    {
        $input = TripInput::from(['title' => '旅行','version' => 3,'tripDate' => '2026-11-05','items' => [['attractionId' => self::PLACE,'startTime' => '23:59','note' => '備註']]], false);
        self::assertSame('23:59:00', $input->items[0]->startTime);
        self::assertSame('備註', $input->items[0]->note);
        self::assertSame(3, $input->version);
    }

    public function testDuplicateStopsRejected(): void
    {
        $this->expectException(ApplicationError::class);
        TripInput::from(['title' => '旅行','items' => [['attractionId' => self::PLACE],['attractionId' => self::PLACE]]], true);
    }

    public function testOwnershipCannotBeSupplied(): void
    {
        $this->expectException(ApplicationError::class);
        TripInput::from(['memberId' => self::PLACE], true);
    }

    public function testInvalidDateRejected(): void
    {
        $this->expectException(ApplicationError::class);
        TripInput::from(['tripDate' => '2026-02-30'], true);
    }

    public function testInvalidTimeRejected(): void
    {
        $this->expectException(ApplicationError::class);
        TripInput::from(['items' => [['attractionId' => self::PLACE,'startTime' => '24:00']]], true);
    }

    public function testBlankDateAndTimeAreOptional(): void
    {
        $input = TripInput::from(['tripDate' => '', 'items' => [['attractionId' => self::PLACE, 'startTime' => '']]], true);
        self::assertNull($input->tripDate);
        self::assertNull($input->items[0]->startTime);
    }

    public function testExplicitNullIdCannotDisableRequestIdentityValidation(): void
    {
        $this->expectException(ApplicationError::class);
        TripInput::from(['id' => null], true);
    }
}
