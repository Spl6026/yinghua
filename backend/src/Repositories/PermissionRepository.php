<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\Models\Account;

/** @phpstan-type Permission array{module:string,action:string,allowed:bool} */
class PermissionRepository
{
    public function __construct(private readonly Database $database) {}

    /** @return list<array{module:string,action:string}> */
    public function catalog(): array
    {
        return array_map(static function (array $record): array {
            $row = new Row($record);

            return ['module' => $row->string('module_key'), 'action' => $row->string('action_key')];
        }, $this->database->rows('SELECT module_key,action_key FROM permissions ORDER BY id'));
    }

    /** @return list<Permission> */
    public function effective(Account $account): array
    {
        $rows = $this->database->rows('SELECT p.module_key,p.action_key,COALESCE(a.allowed,0) AS allowed FROM permissions p LEFT JOIN admin_role_permissions a ON a.permission_id=p.id AND a.admin_id=:id ORDER BY p.id', ['id' => $account->id]);

        return array_map(static function (array $record) use ($account): array {
            $row = new Row($record);

            return ['module' => $row->string('module_key'), 'action' => $row->string('action_key'), 'allowed' => $account->isRoot || $row->integer('allowed') === 1];
        }, $rows);
    }

    /** @return list<Permission> */
    public function template(string $roleId): array
    {
        return array_map(static function (array $record): array {
            $row = new Row($record);

            return ['module' => $row->string('module_key'), 'action' => $row->string('action_key'), 'allowed' => $row->integer('allowed') === 1];
        }, $this->database->rows('SELECT p.module_key,p.action_key,COALESCE(r.allowed,0) AS allowed FROM permissions p LEFT JOIN role_permissions r ON r.permission_id=p.id AND r.role_id=:role ORDER BY p.id', ['role' => $roleId]));
    }

    /** @return list<array{id:string,name:string}> */
    public function roles(): array
    {
        return array_map(static function (array $record): array {
            $row = new Row($record);

            return ['id' => $row->string('id'), 'name' => $row->string('name')];
        }, $this->database->rows("SELECT id,name FROM roles WHERE id<>'root' ORDER BY id"));
    }

    /** @param list<Permission> $permissions */
    public function save(string $id, array $permissions, string $actorId): void
    {
        foreach ($permissions as $permission) {
            $this->database->execute(
                'INSERT INTO admin_role_permissions (admin_id,permission_id,allowed,updated_by) SELECT :admin,id,:allowed,:actor FROM permissions WHERE module_key=:module AND action_key=:action ON DUPLICATE KEY UPDATE allowed=VALUES(allowed),updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()',
                ['admin' => $id, 'actor' => $actorId, 'allowed' => $permission['allowed'], 'module' => $permission['module'], 'action' => $permission['action']],
            );
        }
        $this->database->execute('UPDATE admin_users SET permissions_version=permissions_version+1 WHERE id=:id', ['id' => $id]);
    }
}
