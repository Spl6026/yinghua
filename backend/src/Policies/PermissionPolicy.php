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
        if (!$actor->isActive() || $actor->kind !== IdentityKind::Admin || $actor->mustChangePassword) {
            throw ApplicationError::forbidden();
        }
        foreach ($permissions as $permission) {
            if ($permission['module'] === $module && $permission['action'] === $action && $permission['allowed']) {
                return;
            }
        }
        throw ApplicationError::forbidden();
    }

    public function writableTarget(Account $actor, Account $target, bool $removing = false): void
    {
        // [S-026] UI 隱藏按鈕之外，業務規則也拒絕所有 root CRUD。
        if ($target->isRoot || ($removing && $actor->id === $target->id)) {
            throw ApplicationError::forbidden();
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
