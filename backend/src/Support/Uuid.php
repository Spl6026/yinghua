<?php

declare(strict_types=1);

namespace Yinghua\Support;

/** 系統內所有識別碼都是小寫 UUID；格式檢查集中在這裡，各處的錯誤訊息由呼叫端決定。 */
final class Uuid
{
    public static function isValid(string $value): bool
    {
        return preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/D', $value) === 1;
    }
}
