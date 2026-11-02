<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use RuntimeException;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\Row;

class IdentitySeeder
{
    public function __construct(private readonly Database $database) {}

    public function seed(string $resource): void
    {
        $contents = file_get_contents($resource);
        if ($contents === false) {
            throw new RuntimeException('Cannot read role templates.');
        }
        $document = json_decode($contents, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($document) || !isset($document['roles']) || !is_array($document['roles'])) {
            throw new RuntimeException('Invalid role templates.');
        }
        $names = ['root' => '系統管理員', 'content-editor' => '內容管理員', 'news-editor' => '映演與消息編輯', 'viewer' => '唯讀檢視者'];
        foreach ($names as $role => $name) {
            $this->database->execute(
                'INSERT IGNORE INTO roles (id,name,description) VALUES (:id,:name,:description)',
                ['id' => $role, 'name' => $name, 'description' => $name . '起始權限範本'],
            );
            $modules = $document['roles'][$role] ?? null;
            if (!is_array($modules)) {
                throw new RuntimeException('Missing role template.');
            }
            foreach ($modules as $module => $actions) {
                if (!is_string($module) || !is_array($actions)) {
                    throw new RuntimeException('Invalid module template.');
                }
                foreach (['view', 'create', 'edit', 'delete'] as $action) {
                    $allowed = $actions[$action] ?? null;
                    if (!is_bool($allowed)) {
                        throw new RuntimeException('Invalid permission value.');
                    }
                    $this->database->execute(
                        'INSERT IGNORE INTO permissions (module_key,action_key) VALUES (:module,:action)',
                        ['module' => $module, 'action' => $action],
                    );
                    $record = $this->database->row(
                        'SELECT id FROM permissions WHERE module_key=:module AND action_key=:action',
                        ['module' => $module, 'action' => $action],
                    );
                    $this->database->execute(
                        'INSERT IGNORE INTO role_permissions (role_id,permission_id,allowed) VALUES (:role,:permission,:allowed)',
                        ['role' => $role, 'permission' => (new Row($record ?? []))->integer('id'), 'allowed' => $allowed],
                    );
                }
            }
        }
    }
}
