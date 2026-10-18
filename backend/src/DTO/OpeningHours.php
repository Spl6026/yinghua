<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\OpeningDayStatus;

/** [U-09／E-20] 人工與 Google Adapter 共用的常態週表；不保存即時 openNow。 */
readonly class OpeningHours
{
    /** @param list<OpeningDay> $days */
    private function __construct(public string $note, public array $days) {}

    public static function fromValue(mixed $value): self
    {
        if (!is_array($value) || array_diff(array_keys($value), ['timezone','note','days']) !== [] || ($value['timezone'] ?? null) !== 'Asia/Taipei') {
            throw ApplicationError::invalid('openingHours', '請提供台北時區的每週營業時間。');
        }
        $note = $value['note'] ?? null;
        $days = $value['days'] ?? null;
        if (!is_string($note) || !mb_check_encoding($note, 'UTF-8') || mb_strlen($note) > 500 || str_contains($note, "\0")
            || !is_array($days) || !array_is_list($days) || count($days) !== 7) {
            throw ApplicationError::invalid('openingHours', '請提供七天資料，營業備註最多 500 字。');
        }
        $items = array_map(OpeningDay::fromValue(...), $days);
        usort($items, static fn(OpeningDay $a, OpeningDay $b): int => $a->day <=> $b->day);
        if (array_map(static fn(OpeningDay $day): int => $day->day, $items) !== range(0, 6)) {
            throw ApplicationError::invalid('openingHours', '星期不可重複或缺漏。');
        }
        // 將跨週時段的尾端放到週首，檢查所有開始日之間的重疊。
        $intervals = [];
        foreach ($items as $day) {
            $offset = $day->day * 1440;
            if ($day->status === OpeningDayStatus::AllDay) {
                $intervals[] = [$offset, $offset + 1440];
            }
            foreach ($day->periods as $period) {
                $start = $offset + $period->start();
                $end = $offset + $period->end();
                $intervals[] = [$start, min($end, 10080)];
                if ($end > 10080) {
                    $intervals[] = [0, $end - 10080];
                }
            }
        }
        usort($intervals, static fn(array $a, array $b): int => $a[0] <=> $b[0]);
        $previous = -1;
        foreach ($intervals as [$start,$end]) {
            if ($start < $previous) {
                throw ApplicationError::invalid('openingHours', '營業時段重疊，請檢查同日及跨日至隔日的時間。');
            }
            $previous = $end;
        }
        return new self(trim($note), $items);
    }

    /** @return array{timezone:string,note:string,days:list<array{day:int,status:string,periods:list<array{opens:string,closes:string,nextDay:bool}>}>} */
    public function toArray(): array
    {
        return ['timezone' => 'Asia/Taipei','note' => $this->note,'days' => array_map(static fn(OpeningDay $day): array => $day->toArray(), $this->days)];
    }

    public function summary(): string
    {
        $descriptions = array_values(array_unique(array_map(static fn(OpeningDay $day): string => $day->description(), $this->days)));
        if (count($descriptions) === 1) {
            return $descriptions[0] === '依店家公告' ? ($this->note ?: '依店家公告') : '每日 ' . $descriptions[0];
        }
        return '每週時段不同，詳見介紹';
    }
}
