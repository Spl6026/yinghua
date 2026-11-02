<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Support\Secrets;
use Yinghua\Contracts\Transactions;
use Yinghua\DTO\AdminWriteInput;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\AccountSummary;
use Yinghua\DTO\AdminPage;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\AccountConflict;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Policies\PermissionPolicy;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\PermissionRepository;
use Yinghua\Repositories\ResetRepository;
use Yinghua\Repositories\SessionRepository;

/** [E-01／S-026] 管理員帳號生命週期；個人矩陣編輯交由 PermissionManagement。 */
class AdminManagement
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly PermissionRepository $permissions,
        private readonly RoleTemplates $roles,
        private readonly Authorization $authorization,
        private readonly PermissionPolicy $policy,
        private readonly PasswordHasher $passwords,
        private readonly Transactions $transactions,
        private readonly EventRepository $events,
        private readonly SessionRepository $sessions,
        private readonly ResetRepository $resets,
    ) {}

    public function listing(Account $actor, string $query, string $status, int $page, int $size): AdminPage
    {
        $this->authorization->require($actor, 'admins', 'view');
        $result = $this->accounts->listAdmins($query, $status, $page, $size);

        return new AdminPage(array_map(AccountSummary::fromAccount(...), $result['items']), $result['total'], $page, $size);
    }

    public function get(Account $actor, string $id): AccountSummary
    {
        $this->authorization->require($actor, 'admins', 'view');

        return AccountSummary::fromAccount($this->target($id));
    }

    public function create(Account $actor, AdminWriteInput $input, ClientContext $client): AccountSummary
    {
        $template = $this->roles->forAssignment($input->roleId);
        if ($input->password === null) {
            throw ApplicationError::forbidden();
        }
        // [S：功能規格 G26 admin 保留字；U-24] 以欄位錯誤說明保留帳號，不再只回籠統的權限不足。
        if ($this->accounts->byLogin(IdentityKind::Admin, $input->account)?->isRoot) {
            throw ApplicationError::invalid('account', '此帳號為系統保留帳號，請改用其他帳號。');
        }
        $hash = $this->passwords->hash($input->password);
        try {
            return $this->transactions->run(function () use ($actor, $input, $client, $hash, $template): AccountSummary {
                $fresh = $this->authorization->require($actor, 'admins', 'create', true);
                $this->policy->grantable($fresh, $this->permissions->effective($fresh), $template);
                $id = Secrets::uuid();
                $this->accounts->createAdmin($id, $input, $hash);
                $this->permissions->save($id, $template, $fresh->id);
                $created = AccountSummary::fromAccount($this->target($id));
                $this->events->audit($fresh->id, 'admin-create', $id, null, $created->toArray(), $client);

                return $created;
            });
        } catch (AccountConflict $error) {
            $this->uniqueError($error, $input);
        }
    }

    public function update(Account $actor, string $id, AdminWriteInput $input, ClientContext $client): AccountSummary
    {
        $template = $this->roles->forAssignment($input->roleId);
        try {
            return $this->transactions->run(function () use ($actor, $id, $input, $client, $template): AccountSummary {
                $fresh = $this->authorization->require($actor, 'admins', 'edit', true);
                $target = $this->target($id, true);
                $this->policy->writableTarget($target);
                if ($this->accounts->byLogin(IdentityKind::Admin, $input->account)?->isRoot) {
                    throw ApplicationError::invalid('account', '此帳號為系統保留帳號，請改用其他帳號。');
                }
                if ($input->version !== $target->version) {
                    throw new ApplicationError('CONFLICT', '資料已被其他人修改，請重新載入。');
                }
                if ($input->roleId !== $target->roleId) {
                    $this->policy->grantable($fresh, $this->permissions->effective($fresh), $template);
                    $this->permissions->save($id, $template, $fresh->id);
                }
                $this->accounts->updateAdmin($id, $input);
                if ($input->status === 'disabled') {
                    $this->sessions->revokeAll(IdentityKind::Admin, $id);
                    $this->resets->revokeAll(IdentityKind::Admin, $id);
                }
                $updated = AccountSummary::fromAccount($this->target($id));
                $this->events->audit($fresh->id, 'admin-update', $id, AccountSummary::fromAccount($target)->toArray(), $updated->toArray(), $client);

                return $updated;
            });
        } catch (AccountConflict $error) {
            $this->uniqueError($error, $input, $id);
        }
    }

    public function delete(Account $actor, string $id, ?int $version, ClientContext $client): void
    {
        $this->transactions->run(function () use ($actor, $id, $version, $client): void {
            $fresh = $this->authorization->require($actor, 'admins', 'delete', true);
            $target = $this->target($id, true);
            $this->policy->deletableTarget($fresh, $target);
            if ($version !== null && $version !== $target->version) {
                throw new ApplicationError('CONFLICT', '資料已變更，請重新載入。');
            }
            $this->accounts->deleteAdmin($id);
            $this->sessions->revokeAll(IdentityKind::Admin, $id);
            $this->resets->revokeAll(IdentityKind::Admin, $id);
            $this->events->audit($fresh->id, 'admin-delete', $id, AccountSummary::fromAccount($target)->toArray(), ['deleted' => true], $client);
        });
    }

    private function target(string $id, bool $lock = false): Account
    {
        $account = $this->accounts->find(IdentityKind::Admin, $id, $lock);
        if ($account === null || $account->deletedAt !== null) {
            throw new ApplicationError('NOT_FOUND', '找不到此管理員。');
        }

        return $account;
    }

    private function uniqueError(AccountConflict $error, AdminWriteInput $input, ?string $targetId = null): never
    {
        $existing = $this->accounts->byLogin(IdentityKind::Admin, $input->account);
        $field = $existing !== null && $existing->id !== $targetId ? 'account' : 'email';
        if ($field === 'account' || $this->accounts->byEmail(IdentityKind::Admin, $input->email) !== null) {
            throw new ApplicationError('CONFLICT', '帳號或電子郵件已使用。', [$field => '此資料已使用。']);
        }
        throw $error;
    }
}
