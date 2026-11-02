<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

/** 只讀取最近一次發布成功的快照；不需要產生頁面或發布鎖的服務只依賴這個介面。 */
interface PublishedSnapshot
{
    /** @return array<string,mixed>|null */
    public function current(): ?array;
}
