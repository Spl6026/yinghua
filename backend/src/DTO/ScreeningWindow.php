<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Yinghua\Exceptions\ApplicationError;

/** 一週的放映區間：台北時間週一到下週一（不含結束點），比較與查詢時換成 UTC。 */
readonly class ScreeningWindow
{
    public function __construct(public DateTimeImmutable $start, public DateTimeImmutable $end) {}

    public static function current(int $now): self
    {
        $local = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Asia/Taipei'));
        $start = $local->setISODate((int) $local->format('o'), (int) $local->format('W'), 1)->setTime(0, 0);
        $utc = new DateTimeZone('UTC');

        return new self($start->setTimezone($utc), $start->modify('+1 week')->setTimezone($utc));
    }

    public static function dates(string $from, string $to): self
    {
        $zone = new DateTimeZone('Asia/Taipei');
        $a = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $zone);
        $b = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $zone);
        if ($a === false || $b === false || $a->format('Y-m-d') !== $from || $b->format('Y-m-d') !== $to || $a >= $b) {
            throw ApplicationError::invalid('from', '請提供有效的起始日期與較晚的結束日期（YYYY-MM-DD，結束日不包含）。');
        }
        $utc = new DateTimeZone('UTC');

        return new self($a->setTimezone($utc), $b->setTimezone($utc));
    }

    public function contains(string $startsAt): bool
    {
        return $startsAt >= $this->start->format(DATE_ATOM) && $startsAt < $this->end->format(DATE_ATOM);
    }

    /** @return array<string,string> */
    public function toArray(): array
    {
        return ['from' => $this->start->format(DATE_ATOM),'to' => $this->end->format(DATE_ATOM),'timeZone' => 'Asia/Taipei'];
    }
}
