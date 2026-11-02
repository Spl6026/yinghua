<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Yinghua\DTO\OpeningHours;
use Yinghua\Exceptions\ApplicationError;

/** 把 Google 的常態營業時段（periods）轉成本站的週表；文字版只在無法轉換時給人工參考。 */
class GoogleOpeningHours
{
    public function convert(mixed $value): OpeningHours
    {
        $days = array_map(static fn(int $day): array => ['day' => $day,'status' => 'unknown','periods' => []], range(0, 6));
        $unknown = ['timezone' => 'Asia/Taipei','note' => '', 'days' => $days];
        if (!is_array($value)) {
            return OpeningHours::fromValue($unknown);
        }
        $texts = $value['weekdayDescriptions'] ?? [];
        if (is_array($texts)) {
            $unknown['note'] = mb_substr(implode('；', array_filter($texts, is_string(...))), 0, 500);
        }
        $periods = $value['periods'] ?? null;
        if (!is_array($periods) || !array_is_list($periods) || count($periods) > 28) {
            return OpeningHours::fromValue($unknown);
        }
        foreach ($days as &$day) {
            $day['status'] = 'closed';
        }
        unset($day);
        // Google 對 24 小時全年無休的表示法：週日 00:00 開始，且沒有 close。
        if (count($periods) === 1 && is_array($periods[0]) && !isset($periods[0]['close'])
            && $this->point($periods[0]['open'] ?? null) === 0) {
            foreach ($days as &$day) {
                $day['status'] = 'all_day';
            }
            unset($day);
            return OpeningHours::fromValue(['timezone' => 'Asia/Taipei','note' => '', 'days' => $days]);
        }
        foreach ($periods as $period) {
            if (!is_array($period) || ($start = $this->point($period['open'] ?? null)) === null
                || ($end = $this->point($period['close'] ?? null)) === null) {
                return OpeningHours::fromValue($unknown);
            }
            if ($end <= $start) {
                $end += 10080;
            }
            // 跨越超過一天的時段在午夜切開，整天都營業的日子顯示為全天。
            while ($start < $end) {
                $dayIndex = intdiv($start, 1440) % 7;
                $minutes = $start % 1440;
                $stop = $end - $start <= 1440 ? $end : min($end, (intdiv($start, 1440) + 1) * 1440);
                $duration = $stop - $start;
                $closeMinutes = $minutes + $duration;
                $days[$dayIndex]['status'] = 'periods';
                $days[$dayIndex]['periods'][] = [
                    'opens' => $this->time($minutes),
                    'closes' => $this->time($closeMinutes % 1440),
                    'nextDay' => $closeMinutes >= 1440,
                ];
                $start = $stop;
            }
        }
        foreach ($days as &$day) {
            $entries = $day['periods'] ?? [];
            if (count($entries) === 1 && $entries[0] === ['opens' => '00:00','closes' => '00:00','nextDay' => true]) {
                $day['status'] = 'all_day';
                $day['periods'] = [];
            }
        }
        unset($day);
        try {
            return OpeningHours::fromValue(['timezone' => 'Asia/Taipei','note' => '', 'days' => $days]);
        } catch (ApplicationError) {
            // 資料不完整、超過本站每天四段的上限或時段重疊時，就不當作已成功匯入。
            return OpeningHours::fromValue($unknown);
        }
    }

    private function point(mixed $value): ?int
    {
        if (!is_array($value)) {
            return null;
        }
        $day = $value['day'] ?? null;
        $hour = $value['hour'] ?? 0;
        $minute = $value['minute'] ?? 0;
        if (!is_int($day) || $day < 0 || $day > 6
            || !is_int($hour) || $hour < 0 || $hour > 23
            || !is_int($minute) || $minute < 0 || $minute > 59) {
            return null;
        }
        return $day * 1440 + $hour * 60 + $minute;
    }

    private function time(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
