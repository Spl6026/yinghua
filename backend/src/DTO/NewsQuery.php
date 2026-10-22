<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\NewsType;

readonly class NewsQuery
{
    public function __construct(public string $type, public string $q, public int $page, public int $pageSize)
    {
        if ($type !== '' && NewsType::tryFrom($type) === null) {
            throw ApplicationError::invalid('type', '分類只允許 event／city。');
        }
        if ($page < 1 || $pageSize < 1 || $pageSize > 100 || mb_strlen($q) > 255) {
            throw ApplicationError::invalid('page', '分頁或關鍵字無效。');
        }
    }
}
