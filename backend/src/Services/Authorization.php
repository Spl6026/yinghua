<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Policies\PermissionPolicy;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\PermissionRepository;
use Yinghua\Exceptions\ErrorCode;

class Authorization
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly PermissionRepository $permissions,
        private readonly PermissionPolicy $policy,
    ) {}

    public function require(Account $actor, string $module, string $action, bool $lock = false): Account
    {
        $fresh = $this->accounts->find(IdentityKind::Admin, $actor->id, $lock);
        if ($fresh === null) {
            throw ApplicationError::forbidden();
        }
        $this->policy->require($fresh, $this->permissions->effective($fresh), $module, $action);

        return $fresh;
    }

    /** @return list<array{module:string,action:string,allowed:bool}> */
    public function permissions(Account $actor): array
    {
        return $this->permissions->effective($actor);
    }

    /** @param non-empty-list<string> $actions
     * 用於表單載入單筆或選擇器：有新增或編輯權限即可，不強迫另外具備列表檢視權限。
     */
    public function requireAny(Account $actor, string $module, array $actions): Account
    {
        foreach ($actions as $action) {
            try {
                return $this->require($actor, $module, $action);
            } catch (ApplicationError $error) {
                if ($error->errorCode !== ErrorCode::Forbidden) {
                    throw $error;
                }
            }
        }
        throw ApplicationError::forbidden();
    }
}
