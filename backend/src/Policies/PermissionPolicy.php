<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;

class PermissionPolicy
{
    /** @param list<array{module:string,action:string,allowed:bool}> $permissions */
    public function require(Account $actor, array $permissions, string $module, string $action): void
    {
        if (!$actor->isActive() || $actor->kind !== IdentityKind::Admin) {
            throw ApplicationError::forbidden();
        }
        foreach ($permissions as $permission) {
            if ($permission['module'] === $module && $permission['action'] === $action && $permission['allowed']) {
                return;
            }
        }
        throw ApplicationError::forbidden();
    }

    public function writableTarget(Account $target): void
    {
        // [S-026] UI 隱藏按鈕之外，業務規則也拒絕所有 root CRUD。
        if ($target->isRoot) {
            throw ApplicationError::forbidden();
        }
    }

    public function deletableTarget(Account $actor, Account $target): void
    {
        $this->writableTarget($target);
        // [S：功能規格 G26「不可自我移除最後管理權」；U-24] 介面已隱藏本人刪除，服務端同樣拒絕，避免自行刪號失去管理權。
        if ($actor->id === $target->id) {
            throw new ApplicationError('FORBIDDEN', '不能刪除目前登入中的管理員帳號。');
        }
    }

    /** @param list<array{module:string,action:string,allowed:bool}> $actorRights
     * @param list<array{module:string,action:string,allowed:bool}> $grant
     */
    public function grantable(Account $actor, array $actorRights, array $grant): void
    {
        if ($actor->isRoot) {
            return;
        }
        foreach ($grant as $permission) {
            if ($permission['allowed']) {
                $this->require($actor, $actorRights, $permission['module'], $permission['action']);
            }
        }
    }
}
