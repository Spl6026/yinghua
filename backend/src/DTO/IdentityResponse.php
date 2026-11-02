<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Models\IdentityKind;

/** 登入身分的 API 輸出，和內部的 AuthGrant 分開；Cookie 與 CSRF 仍由 HTTP 層處理。 */
readonly class IdentityResponse
{
    /** @param list<array{module:string,action:string,allowed:bool}> $permissions */
    public function __construct(public AccountSummary $account, public array $permissions = [], public ?PreviewStatus $preview = null) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        if ($this->account->kind === IdentityKind::Member) {
            return ['member' => $this->account->toArray()];
        }
        $data = ['admin' => $this->account->toArray(), 'permissions' => $this->permissions];
        if ($this->preview !== null) {
            $data['preview'] = $this->preview->toArray();
        }

        return $data;
    }
}
