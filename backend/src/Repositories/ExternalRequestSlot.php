<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

/** [E-40] 每個資料庫一個即時Google備援槽，不持鎖寫入Google資料。 */
class ExternalRequestSlot
{
    public function __construct(private readonly Database $database) {}

    public function acquire(): bool
    {
        return (new Row($this->database->row("SELECT GET_LOCK(CONCAT('google-summary-',LEFT(SHA2(DATABASE(),256),32)),0) AS acquired") ?? []))->integer('acquired') === 1;
    }

    public function release(): void
    {
        $this->database->row("SELECT RELEASE_LOCK(CONCAT('google-summary-',LEFT(SHA2(DATABASE(),256),32))) AS released");
    }
}
