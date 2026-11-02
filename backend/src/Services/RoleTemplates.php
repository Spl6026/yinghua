<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\RoleTemplate;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Repositories\PermissionRepository;

/** 角色範本的查閱與指派規則；建立帳號或更換角色時的交易仍由管理員流程控制。 */
class RoleTemplates
{
    public function __construct(private readonly PermissionRepository $permissions, private readonly Authorization $authorization) {}

    /** @return list<RoleTemplate> */
    public function listing(Account $actor): array
    {
        $allowed = false;
        foreach ([['admins', 'create'], ['admins', 'edit'], ['permissions', 'view']] as [$module, $action]) {
            try {
                $this->authorization->require($actor, $module, $action);
                $allowed = true;
                break;
            } catch (ApplicationError) {
            }
        }
        if (!$allowed) {
            throw ApplicationError::forbidden();
        }

        return array_map(
            fn(array $role): RoleTemplate => new RoleTemplate($role['id'], $role['name'], $this->permissions->template($role['id'])),
            $this->permissions->roles(),
        );
    }

    /** @return list<array{module:string,action:string,allowed:bool}> */
    public function forAssignment(string $roleId): array
    {
        if ($roleId === 'root') {
            throw ApplicationError::forbidden();
        }
        if (!in_array($roleId, array_column($this->permissions->roles(), 'id'), true)) {
            throw ApplicationError::invalid('roleId', '不支援的角色。');
        }

        return $this->permissions->template($roleId);
    }

}
