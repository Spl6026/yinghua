<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;

/** 對外輸出的帳號欄位白名單；刻意不持有內部 Account，避免不小心帶出密碼雜湊或刪除資訊。 */
readonly class AccountSummary
{
    public function __construct(
        public string $id,
        public IdentityKind $kind,
        public ?string $account,
        public string $email,
        public string $displayName,
        public string $status,
        public bool $isRoot,
        public ?string $roleId,
        public bool $mustChangePassword,
        public int $version,
        public int $permissionsVersion,
    ) {}

    public static function fromAccount(Account $account): self
    {
        return new self(
            $account->id,
            $account->kind,
            $account->account,
            $account->email,
            $account->displayName,
            $account->status,
            $account->isRoot,
            $account->roleId,
            $account->mustChangePassword,
            $account->version,
            $account->permissionsVersion,
        );
    }

    /** @return array<string, bool|int|string|null> */
    public function toArray(): array
    {
        return [
            'id' => $this->id, 'kind' => $this->kind->value, 'account' => $this->account,
            'email' => $this->email, 'displayName' => $this->displayName, 'status' => $this->status,
            'isRoot' => $this->isRoot, 'roleId' => $this->roleId,
            'mustChangePassword' => $this->mustChangePassword,
            'version' => $this->version, 'permissionsVersion' => $this->permissionsVersion,
        ];
    }
}
