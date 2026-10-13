<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use PDOException;
use Yinghua\DTO\AdminWriteInput;
use Yinghua\DTO\RegisterInput;
use Yinghua\Exceptions\AccountConflict;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;

class AccountRepository
{
    public function __construct(private readonly Database $database) {}

    public function find(IdentityKind $kind, string $id, bool $lock = false): ?Account
    {
        return $this->query($kind, 'id = :id', ['id' => $id], $lock);
    }

    public function byLogin(IdentityKind $kind, string $identifier): ?Account
    {
        return $this->query($kind, ($kind === IdentityKind::Admin ? 'account' : 'email') . ' = :value', ['value' => $identifier]);
    }

    public function byEmail(IdentityKind $kind, string $email): ?Account
    {
        return $this->query($kind, 'email = :email', ['email' => $email]);
    }

    public function createMember(string $id, RegisterInput $input, string $hash): void
    {
        $this->writeAccount(
            'INSERT INTO members (id,email,display_name,password_hash) VALUES (:id,:email,:name,:hash)',
            ['id' => $id, 'email' => $input->email, 'name' => $input->displayName, 'hash' => $hash],
        );
    }

    public function createAdmin(string $id, AdminWriteInput $input, string $hash, bool $root = false): void
    {
        $this->writeAccount(
            'INSERT INTO admin_users (id,account,email,display_name,password_hash,role_id,status,is_root,must_change_password) VALUES (:id,:account,:email,:name,:hash,:role,:status,:root,:change)',
            ['id' => $id, 'account' => $input->account, 'email' => $input->email,
                'name' => $input->displayName, 'hash' => $hash, 'role' => $input->roleId,
                'status' => $input->status, 'root' => $root, 'change' => false],
        );
    }

    public function updateAdmin(string $id, AdminWriteInput $input): void
    {
        $this->writeAccount(
            'UPDATE admin_users SET account=:account,display_name=:name,email=:email,role_id=:role,status=:status,version=version+1 WHERE id=:id',
            ['id' => $id, 'account' => $input->account, 'name' => $input->displayName, 'email' => $input->email, 'role' => $input->roleId, 'status' => $input->status],
        );
    }

    public function deleteAdmin(string $id): void
    {
        $this->database->execute('UPDATE admin_users SET deleted_at=UTC_TIMESTAMP(),status=\'disabled\',version=version+1 WHERE id=:id', ['id' => $id]);
    }

    public function savePassword(IdentityKind $kind, string $id, string $hash): void
    {
        $extra = $kind === IdentityKind::Admin ? ',must_change_password=0' : '';
        $this->database->execute('UPDATE ' . $kind->table() . ' SET password_hash=:hash' . $extra . ' WHERE id=:id', ['hash' => $hash, 'id' => $id]);
    }

    public function loginSeen(IdentityKind $kind, string $id): void
    {
        $this->database->execute('UPDATE ' . $kind->table() . ' SET last_login_at=UTC_TIMESTAMP() WHERE id=:id', ['id' => $id]);
    }

    public function rehashPassword(IdentityKind $kind, string $id, string $hash): void
    {
        $this->database->execute('UPDATE ' . $kind->table() . ' SET password_hash=:hash WHERE id=:id', ['hash' => $hash, 'id' => $id]);
    }

    /** @return array{items:list<Account>,total:int} */
    public function listAdmins(string $query, string $status, int $page, int $size): array
    {
        $where = 'deleted_at IS NULL';
        $parameters = [];
        if ($query !== '') {
            $where .= " AND (account LIKE :q1 ESCAPE '!' OR email LIKE :q2 ESCAPE '!' OR display_name LIKE :q3 ESCAPE '!')";
            $search = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
            $parameters = ['q1' => $search, 'q2' => $search, 'q3' => $search];
        }
        if ($status !== '') {
            $where .= ' AND status=:status';
            $parameters['status'] = $status;
        }
        $count = $this->database->row('SELECT COUNT(*) AS total FROM admin_users WHERE ' . $where, $parameters);
        $rows = $this->database->rows(
            'SELECT * FROM admin_users WHERE ' . $where . ' ORDER BY created_at DESC,id DESC LIMIT :limit OFFSET :offset',
            [...$parameters, 'limit' => $size, 'offset' => ($page - 1) * $size],
        );

        return ['items' => array_map(fn(array $row): Account => $this->map(IdentityKind::Admin, $row), $rows),
            'total' => (new Row($count ?? []))->integer('total')];
    }

    /** @return list<array{id:string,account:string,displayName:string,isRoot:bool}> */
    public function targets(): array
    {
        return array_map(static function (array $record): array {
            $row = new Row($record);

            return ['id' => $row->string('id'), 'account' => $row->string('account'), 'displayName' => $row->string('display_name'), 'isRoot' => $row->integer('is_root') === 1];
        }, $this->database->rows('SELECT id,account,display_name,is_root FROM admin_users WHERE deleted_at IS NULL ORDER BY display_name,id'));
    }

    /** [U-06] 只給 root 的選單，排除停用／刪除及固定 root；不回傳 Email 等不必要資料。
     * @return list<array{id:string,account:string,displayName:string}>
     */
    public function previewTargets(): array
    {
        return array_map(static function (array $record): array {
            $row = new Row($record);

            return ['id' => $row->string('id'), 'account' => $row->string('account'), 'displayName' => $row->string('display_name')];
        }, $this->database->rows("SELECT id,account,display_name FROM admin_users WHERE deleted_at IS NULL AND status='active' AND is_root=0 ORDER BY display_name,id"));
    }

    /** [E-01] 只轉譯帳號寫入的 duplicate key；外鍵、CHECK、連線及稽核錯誤仍是非預期失敗。
     * @param array<string, bool|int|string|null> $parameters
     */
    private function writeAccount(string $sql, array $parameters): void
    {
        try {
            $this->database->execute($sql, $parameters);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000' && ($error->errorInfo[1] ?? null) === 1062) {
                throw new AccountConflict($error);
            }
            throw $error;
        }
    }

    /** @param array<string, bool|int|string|null> $parameters */
    private function query(IdentityKind $kind, string $where, array $parameters, bool $lock = false): ?Account
    {
        $row = $this->database->row('SELECT * FROM ' . $kind->table() . ' WHERE ' . $where . ($lock ? ' FOR UPDATE' : ''), $parameters);

        return $row === null ? null : $this->map($kind, $row);
    }

    /** @param array<string, mixed> $record */
    private function map(IdentityKind $kind, array $record): Account
    {
        $row = new Row($record);
        $admin = $kind === IdentityKind::Admin;

        return new Account(
            $row->string('id'),
            $kind,
            $admin ? $row->string('account') : null,
            $row->string('email'),
            $row->string('display_name'),
            $row->string('password_hash'),
            $row->string('status'),
            $admin && $row->integer('is_root') === 1,
            $admin ? $row->string('role_id') : null,
            false, // [U-13] 舊回應欄位保留相容，已取消首登強制改密碼。
            $admin ? $row->integer('version') : 1,
            $admin ? $row->integer('permissions_version') : 1,
            $row->nullableString('deleted_at'),
        );
    }
}
