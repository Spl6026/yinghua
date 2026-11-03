<?php

declare(strict_types=1);

namespace Yinghua\Support;

/** 寫入資料庫或快照的 JSON：出錯一律丟例外，中文保持原字，不轉成 \u 跳脫。 */
final class Json
{
    public static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
