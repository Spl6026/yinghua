<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Models\IdentityKind;

/** [E-01／E-02／U-06] 認證輸出與內部 AuthGrant 分離；Cookie、CSRF 仍由 HTTP 層處理。 */
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
