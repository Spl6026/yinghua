<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

/** 檢查資料庫與 migration 是否就緒；正式環境用 PDO 實作，測試可替換成模擬故障的版本。 */
interface DatabaseHealthProbe
{
    public function ready(): bool;
}
