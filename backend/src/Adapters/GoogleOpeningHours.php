<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Yinghua\DTO\OpeningHours;
use Yinghua\Exceptions\ApplicationError;

/** [U-09／E-20] Google 的常態 periods 轉為本站週表；文字僅作無法映射時的人工參考。 */
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
        // Google 明確定義的 24/7：週日 00:00 開始，沒有 close。
        if (count($periods) === 1 && is_array($periods[0]) && !isset($periods[0]['close']) && $this->point($periods[0]['open'] ?? null) === 0) {
            foreach ($days as &$day) {
                $day['status'] = 'all_day';
            }
            unset($day);
            return OpeningHours::fromValue(['timezone' => 'Asia/Taipei','note' => '', 'days' => $days]);
        }
        foreach ($periods as $period) {
            if (!is_array($period) || ($start = $this->point($period['open'] ?? null)) === null || ($end = $this->point($period['close'] ?? null)) === null) {
                return OpeningHours::fromValue($unknown);
            }
            if ($end <= $start) {
                $end += 10080;
            }
            // 連續超過一天時拆成午夜邊界，完整的一天顯示全天。
            while ($start < $end) {
                $dayIndex = intdiv($start, 1440) % 7;
                $minutes = $start % 1440;
                $stop = $end - $start <= 1440 ? $end : min($end, (intdiv($start, 1440) + 1) * 1440);
                $duration = $stop - $start;
                $closeMinutes = $minutes + $duration;
                $days[$dayIndex]['status'] = 'periods';
                $days[$dayIndex]['periods'][] = ['opens' => $this->time($minutes),'closes' => $this->time($closeMinutes % 1440),'nextDay' => $closeMinutes >= 1440];
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
            // 不完整、超出本站四段限制或重疊的提供者資料不冒充已正確匯入。
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
        if (!is_int($day) || $day < 0 || $day > 6 || !is_int($hour) || $hour < 0 || $hour > 23 || !is_int($minute) || $minute < 0 || $minute > 59) {
            return null;
        }
        return $day * 1440 + $hour * 60 + $minute;
    }

    private function time(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
