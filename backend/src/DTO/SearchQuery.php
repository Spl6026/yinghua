<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\SearchType;

readonly class SearchQuery
{
    /** 分頁上限與其他公開清單相同；不限制最少字數，單一中文字也可以搜尋。 */
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
