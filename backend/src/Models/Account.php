<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** 內部帳號模型含密碼雜湊；API 必須明確組合安全欄位。 */
readonly class Account
{
    public function __construct(
        public string $id,
        public IdentityKind $kind,
        public ?string $account,
        public string $email,
        public string $displayName,
        public string $passwordHash,
        public string $status,
        public bool $isRoot,
        public ?string $roleId,
        public bool $mustChangePassword,
        public int $version,
        public int $permissionsVersion,
        public ?string $deletedAt,
    ) {}

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deletedAt === null;
    }

    /** @return array<string, bool|int|string|null> */
    public function summary(): array
    {
        return [
            'id' => $this->id, 'kind' => $this->kind->value,
            'account' => $this->account, 'email' => $this->email,
            'displayName' => $this->displayName, 'status' => $this->status,
            'isRoot' => $this->isRoot, 'roleId' => $this->roleId,
            'mustChangePassword' => $this->mustChangePassword,
            'version' => $this->version, 'permissionsVersion' => $this->permissionsVersion,
        ];
    }
}
