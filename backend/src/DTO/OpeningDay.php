<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\OpeningDayStatus;

readonly class OpeningDay
{
    /** @param list<OpeningPeriod> $periods */
    private function __construct(public int $day, public OpeningDayStatus $status, public array $periods) {}

    public static function fromValue(mixed $value): self
    {
        if (!is_array($value) || array_diff(array_keys($value), ['day','status','periods']) !== []) {
            throw ApplicationError::invalid('openingHours', '每日營業時間格式無效。');
        }
        $day = $value['day'] ?? null;
        $status = $value['status'] ?? null;
        $periods = $value['periods'] ?? null;
        if (!is_int($day) || $day < 0 || $day > 6 || !is_string($status) || ($status = OpeningDayStatus::tryFrom($status)) === null
            || !is_array($periods) || !array_is_list($periods) || count($periods) > 4) {
            throw ApplicationError::invalid('openingHours', '請提供有效星期、營業狀態與最多四個時段。');
        }
        if (($status === OpeningDayStatus::Periods) !== ($periods !== [])) {
            throw ApplicationError::invalid('openingHours', '自訂營業須至少一段；公休、全天或未知請移除自訂時段。');
        }
        $items = array_map(OpeningPeriod::fromValue(...), $periods);
        usort($items, static fn(OpeningPeriod $a, OpeningPeriod $b): int => $a->start() <=> $b->start());
        return new self($day, $status, $items);
    }

    /** @return array{day:int,status:string,periods:list<array{opens:string,closes:string,nextDay:bool}>} */
    public function toArray(): array
    {
        return ['day' => $this->day,'status' => $this->status->value,'periods' => array_map(static fn(OpeningPeriod $period): array => $period->toArray(), $this->periods)];
    }

    public function description(): string
    {
        return match ($this->status) {
            OpeningDayStatus::Unknown => '依店家公告',
            OpeningDayStatus::Closed => '公休',
            OpeningDayStatus::AllDay => '全天開放',
            OpeningDayStatus::Periods => implode('、', array_map(static fn(OpeningPeriod $period): string => $period->description(), $this->periods)),
        };
    }
}
