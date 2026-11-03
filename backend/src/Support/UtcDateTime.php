<?php

declare(strict_types=1);

namespace Yinghua\Support;

/** 資料庫連線固定使用 UTC，讀出的 DATETIME 字串在這裡統一轉成帶時區的 ISO 8601 格式。 */
final class UtcDateTime
{
    /** Unix 時間轉成資料庫 DATETIME 欄位使用的 UTC 字串。 */
    public static function sql(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    /** @phpstan-return ($value is null ? null : string) */
    public static function iso(?string $value): ?string
    {
        return $value === null ? null : str_replace(' ', 'T', $value) . '+00:00';
    }
}
