<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use DateTimeImmutable;
use DateTimeZone;

/** 優惠有效期間以台北日期判斷，結束日當天整天有效；起訖日留空表示該端不限。 */
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
