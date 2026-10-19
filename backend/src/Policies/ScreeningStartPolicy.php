<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\ScreeningInput;
use Yinghua\Exceptions\ApplicationError;

/** [U-16] 僅比較開始分鐘，不推算片長／結束時間。 */
readonly class ScreeningStartPolicy
{
    /** @param list<ScreeningInput> $items */
    public function assertDistinct(array $items): void
    {
        $starts = [];
        $ids = [];
        foreach ($items as $index => $item) {
            $time = $item->startsAt->format('Y-m-d H:i');
            if (isset($starts[$time])) {
                throw new ApplicationError('CONFLICT', '此電影有重複的放映時間。', ['screenings.' . $index . '.startsAt' => '放映日期與開始時間不可重複。']);
            }
            if ($item->id !== null && isset($ids[$item->id])) {
                throw ApplicationError::invalid('screenings.' . $index . '.id', '場次識別不可重複。');
            }
            $starts[$time] = true;
            if ($item->id !== null) {
                $ids[$item->id] = true;
            }
        }
    }
}
