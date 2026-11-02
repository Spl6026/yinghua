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

    /**
     * 同一請求中已讀取的管理員與有效權限。一次儲存會在路由、服務與交易內各檢查一次權限，
     * 不必每次都重查；需要鎖定（交易內的寫入前檢查）時一律重新讀取並更新這份紀錄。
     *
     * @var array<string, array{0:Account,1:list<array{module:string,action:string,allowed:bool}>}>
     */
    private array $known = [];

    public function require(Account $actor, string $module, string $action, bool $lock = false): Account
    {
        [$fresh, $effective] = $this->load($actor->id, $lock);
        $this->policy->require($fresh, $effective, $module, $action);

        return $fresh;
    }

    /** @return array{0:Account,1:list<array{module:string,action:string,allowed:bool}>} */
    private function load(string $id, bool $lock): array
    {
        if (!$lock && isset($this->known[$id])) {
            return $this->known[$id];
        }
        $fresh = $this->accounts->find(IdentityKind::Admin, $id, $lock);
        if ($fresh === null) {
            unset($this->known[$id]);
            throw ApplicationError::forbidden();
        }

        return $this->known[$id] = [$fresh, $this->permissions->effective($fresh)];
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
