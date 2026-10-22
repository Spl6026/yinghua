<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use DateTimeImmutable;
use DateTimeZone;

/** [S：功能規格 G11；E-26] 台北當地日期，迄日整天有效；空值不限制該端。 */
final class OfferValidity
{
    public static function day(int $now): string
    {
        return (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Asia/Taipei'))->format('Y-m-d');
    }

    public static function state(?string $from, ?string $to, string $today): string
    {
        return $from !== null && $from > $today ? 'upcoming' : ($to !== null && $to < $today ? 'expired' : 'active');
    }
}
