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
        // 介面雖然已隱藏按鈕，root 管理員仍要在這裡擋下所有編輯與刪除。
        if ($target->isRoot) {
            throw ApplicationError::forbidden();
        }
    }

    public function deletableTarget(Account $actor, Account $target): void
    {
        $this->writableTarget($target);
        // 介面雖然已隱藏刪除自己的按鈕，伺服器端仍要拒絕，避免管理員誤刪自己的帳號而失去管理權。
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
