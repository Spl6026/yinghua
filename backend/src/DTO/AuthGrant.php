<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use SensitiveParameter;
use Yinghua\Models\Account;

/** 僅在 Service／HTTP 邊界傳遞；raw token 只能寫 Cookie，不能回 JSON。 */
readonly class AuthGrant
{
    public function __construct(public Account $account, #[SensitiveParameter] public string $token) {}
}
