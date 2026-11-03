<?php

declare(strict_types=1);

namespace Yinghua\Exceptions;

/** 還沒有任何成功發布的版本；診斷訊息只記原因代碼，不寫入公開內容、路徑或設定值。 */
class PublicationPending extends ApplicationError
{
    public function __construct()
    {
        parent::__construct(ErrorCode::PublicationPending, '公開頁面正在更新，請稍後重試。', retryAfter: 5);
    }

    public function diagnostic(): string
    {
        return 'publication_reason=missing_snapshot';
    }
}
