<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\ContentStatus;
use Yinghua\Models\NewsType;

/** [S G22／G23；E-31] 已有欄位的管理查詢，不新增業務必填。 */
readonly class AdminContentQuery
{
    public function __construct(
        public string $q,
        public string $status,
        public string $type,
        public string $merchantId,
        public string $active,
        public int $page,
        public int $pageSize,
    ) {
        if (mb_strlen($q) > 400) {
            throw ApplicationError::invalid('q', '搜尋文字過長。');
        }
        if ($status !== '' && ContentStatus::tryFrom($status) === null && !in_array($status, ['active','inactive'], true)) {
            throw ApplicationError::invalid('status', '不支援的狀態。');
        }
        if ($type !== '' && NewsType::tryFrom($type) === null) {
            throw ApplicationError::invalid('type', '分類只允許 event／city。');
        }
        if ($merchantId !== '') {
            ContentFields::uuid($merchantId, 'merchantId');
        }
        if (!in_array($active, ['', 'true', 'false'], true)) {
            throw ApplicationError::invalid('active', '有效狀態須為 true／false。');
        }
        if ($page < 1 || $page > 100000 || $pageSize < 1 || $pageSize > 100) {
            throw ApplicationError::invalid('page', '分頁超出範圍。');
        }
    }
}
