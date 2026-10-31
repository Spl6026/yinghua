<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\SearchType;

readonly class SearchQuery
{
    /** [S G18；E-35] 容量沿現有公開清單，不限制中文最少字數。 */
    public function __construct(public string $q, public string $type = '', public int $page = 1, public int $pageSize = 20)
    {
        if (mb_strlen($q) > 255) {
            throw ApplicationError::invalid('q', '搜尋字詞最多255字。');
        }
        if ($type !== '' && SearchType::tryFrom($type) === null) {
            throw ApplicationError::invalid('type', '搜尋類型無效。');
        }
        if ($page < 1 || $page > 100000 || $pageSize < 1 || $pageSize > 100) {
            throw ApplicationError::invalid('page', '搜尋分頁無效。');
        }
    }
}
