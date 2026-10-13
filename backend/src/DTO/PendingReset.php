<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use SensitiveParameter;
use Yinghua\Models\Account;

readonly class PendingReset
{
    public function __construct(public string $id, public Account $account, #[SensitiveParameter] public string $token) {}
}
