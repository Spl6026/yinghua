<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

readonly class OfferQuery
{
    public function __construct(public bool $active, public string $q, public string $district, public int $page, public int $pageSize)
    {
        if (!in_array($district, ['', '北區','東區','香山區','新竹市'], true)) {
            throw ApplicationError::invalid('district', '行政區無效。');
        }
        if ($page < 1 || $pageSize < 1 || $pageSize > 100 || mb_strlen($q) > 255) {
            throw ApplicationError::invalid('page', '分頁或關鍵字無效。');
        }
    }
}
