<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

/** 每個資料庫只有一個即時查詢 Google 的備援槽；佔用期間不持有鎖去寫入 Google 資料。 */
class ExternalRequestSlot
{
    public function __construct(private readonly Database $database) {}

    public function acquire(): bool
    {
        return (new Row(
            $this->database->row("SELECT GET_LOCK(CONCAT('google-summary-',LEFT(SHA2(DATABASE(),256),32)),0) AS acquired") ?? [],
        ))->integer('acquired') === 1;
    }

    public function release(): void
    {
        $this->database->row("SELECT RELEASE_LOCK(CONCAT('google-summary-',LEFT(SHA2(DATABASE(),256),32))) AS released");
    }
}
