<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use SensitiveParameter;
use Yinghua\Models\Account;

/** 只在 Service 與 HTTP 層之間傳遞；原始 token 只能寫進 Cookie，絕不能放進 JSON 回應。 */
readonly class AuthGrant
{
    public function __construct(public Account $account, #[SensitiveParameter] public string $token) {}
}
