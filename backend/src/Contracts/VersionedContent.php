<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

/** 後台以樂觀鎖儲存、並寫入稽核紀錄的內容：都有版本號與發布狀態。 */
interface VersionedContent
{
    public int $version { get; }

    public string $status { get; }
}
