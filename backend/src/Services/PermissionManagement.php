<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\PermissionCatalog;
use Yinghua\DTO\PermissionMatrix;
use Yinghua\DTO\PermissionMatrixInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Policies\PermissionPolicy;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\PermissionRepository;
use Yinghua\Exceptions\ErrorCode;

/** 管理員個人權限矩陣的查閱與修改，含版本比對與稽核紀錄；帳號本身的增刪改由 AdminManagement 負責。 */
class PermissionManagement
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly PermissionRepository $permissions,
        private readonly Authorization $authorization,
        private readonly PermissionPolicy $policy,
        private readonly Transactions $transactions,
        private readonly EventRepository $events,
    ) {}

    public function catalog(Account $actor): PermissionCatalog
    {
        $this->authorization->require($actor, 'permissions', 'view');

        return new PermissionCatalog($this->permissions->catalog(), $this->accounts->targets());
    }

    public function matrix(Account $actor, string $id): PermissionMatrix
    {
        $this->authorization->require($actor, 'permissions', 'view');
        $target = $this->target($id);

        return new PermissionMatrix($id, $target->permissionsVersion, $target->isRoot, $this->permissions->effective($target));
    }

    public function saveMatrix(Account $actor, string $id, PermissionMatrixInput $input, ClientContext $client): void
    {
        $keys = array_map(static fn(array $right): string => $right['module'] . ':' . $right['action'], $this->permissions->catalog());
        $provided = array_map(static fn(array $right): string => $right['module'] . ':' . $right['action'], $input->permissions);
        sort($keys);
        sort($provided);
        if ($provided !== $keys) {
            throw ApplicationError::invalid('permissions', '請提交完整且不重複的功能矩陣。');
        }
        $this->transactions->run(function () use ($actor, $id, $input, $client): void {
            $fresh = $this->authorization->require($actor, 'permissions', 'edit', true);
            $target = $this->target($id, true);
            $this->policy->writableTarget($target);
            if ($target->permissionsVersion !== $input->version) {
                throw new ApplicationError(ErrorCode::Conflict, '權限已被其他人修改，請重新載入。');
            }
            $this->policy->grantable($fresh, $this->permissions->effective($fresh), $input->permissions);
            $before = $this->permissions->effective($target);
            $this->permissions->save($id, $input->permissions, $fresh->id);
            $this->events->audit(
                $fresh->id,
                'permission-update',
                $id,
                ['permissions' => $before],
                ['permissions' => $input->permissions],
                $client,
            );
        });
    }

    private function target(string $id, bool $lock = false): Account
    {
        $account = $this->accounts->find(IdentityKind::Admin, $id, $lock);
        if ($account === null || $account->deletedAt !== null) {
            throw new ApplicationError(ErrorCode::NotFound, '找不到此管理員。');
        }

        return $account;
    }

}
