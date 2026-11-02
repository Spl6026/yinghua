<?php

declare(strict_types=1);

namespace Yinghua\Exceptions;

use RuntimeException;
use Throwable;

/** 帳號唯一值衝突；由資料存取層轉換，Service 不需要知道 SQLSTATE 或資料庫驅動。 */
class AccountConflict extends RuntimeException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct('Account unique value already exists.', 0, $previous);
    }
}
