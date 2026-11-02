<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\Exceptions\ApplicationError;

final class ImportVersion
{
    /** @param array<string,string|int|null> $before
     * @param array<string,string|int|null> $after
     */
    public static function unchanged(array $before, array $after): bool
    {
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                return false;
            }
        }
        return true;
    }

    public static function assertCurrent(?int $provided, int $actual, bool $unchanged): void
    {
        // 內容完全相同的重跑可以略過；內容不同時，不能用過期版本覆蓋。
        if (!$unchanged && $provided !== $actual) {
            throw new ApplicationError('CONFLICT', '內容已更新，請重新載入最新版本後再儲存。');
        }
    }
}
