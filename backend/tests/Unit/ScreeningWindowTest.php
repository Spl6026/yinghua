<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yinghua\DTO\ScreeningWindow;

class ScreeningWindowTest extends TestCase
{
    public function testTaipeiMondayUsesTheLocalCalendarAndExclusiveEnd(): void
    {
        $window = ScreeningWindow::current((new DateTimeImmutable('2026-10-19T00:00:00+08:00'))->getTimestamp());
        self::assertSame('2026-10-18T16:00:00+00:00', $window->start->format(DATE_ATOM));
        self::assertTrue($window->contains('2026-10-18T16:00:00+00:00'));
        self::assertFalse($window->contains('2026-10-25T16:00:00+00:00'));
    }

    public function testIsoWeekYearCrossingIsCorrect(): void
    {
        $window = ScreeningWindow::current((new DateTimeImmutable('2027-01-01T12:00:00+08:00'))->getTimestamp());
        self::assertSame('2026-12-27T16:00:00+00:00', $window->start->format(DATE_ATOM));
        self::assertSame('2027-01-03T16:00:00+00:00', $window->end->format(DATE_ATOM));
    }
}
