<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

use Yinghua\DTO\AdminContentQuery;
use Yinghua\DTO\ContentPage;
use Yinghua\DTO\NewsPost;
use Yinghua\DTO\Offer;

/** 後台以同一套流程管理的內容（消息、優惠）在資料存取上共同的操作。 */
interface AdminContentRecords
{
    public function listing(AdminContentQuery $query, string $today): ContentPage;

    public function find(string $id, bool $lock = false): NewsPost|Offer|null;

    public function delete(string $id): void;
}
