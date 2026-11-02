<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 已驗證 session 的到期時間，給前端顯示用；不含 token，也不會延長伺服器端的期限。 */
readonly class SessionTiming
{
    public function __construct(public int $serverTime, public int $expiresAt, public int $idleExpiresAt) {}
}
