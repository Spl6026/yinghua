<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [S G34／B15；E-29] 已驗證登入的期限；不含 token，也不改變伺服器期限。 */
readonly class SessionTiming
{
    public function __construct(public int $serverTime, public int $expiresAt, public int $idleExpiresAt) {}
}
