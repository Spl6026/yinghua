<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Models\ResetToken;
use Yinghua\Support\UtcDateTime;

class ResetRepository
{
    public function __construct(private readonly Database $database) {}

    public function create(string $id, Account $account, string $token, int $expires): void
    {
        $this->revokeAll($account->kind, $account->id);
        $this->database->execute(
            'INSERT INTO password_reset_tokens (id,member_id,admin_id,token_hash,purpose,expires_at) VALUES (:id,:member,:admin,:token,:purpose,:expires)',
            [
                'id' => $id,
                'member' => $account->kind === IdentityKind::Member ? $account->id : null,
                'admin' => $account->kind === IdentityKind::Admin ? $account->id : null,
                'token' => hash('sha256', $token),
                'purpose' => $account->kind->value . '-reset',
                'expires' => UtcDateTime::sql($expires),
            ],
        );
    }

    public function lock(IdentityKind $kind, string $token): ?ResetToken
    {
        $record = $this->database->row(
            'SELECT * FROM password_reset_tokens WHERE token_hash=:token AND purpose=:purpose AND ' . $kind->foreignKey() . ' IS NOT NULL FOR UPDATE',
            ['token' => hash('sha256', $token), 'purpose' => $kind->value . '-reset'],
        );
        if ($record === null) {
            return null;
        }
        $row = new Row($record);

        return new ResetToken(
            $row->string('id'),
            $row->string($kind->foreignKey()),
            (int) strtotime($row->string('expires_at') . ' UTC'),
            $row->nullableString('used_at') !== null || $row->nullableString('revoked_at') !== null,
        );
    }

    public function consume(string $id): void
    {
        $this->database->execute('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE id=:id', ['id' => $id]);
    }

    public function revoke(string $id): void
    {
        $this->database->execute('UPDATE password_reset_tokens SET revoked_at=UTC_TIMESTAMP() WHERE id=:id', ['id' => $id]);
    }

    public function revokeAll(IdentityKind $kind, string $id): void
    {
        $this->database->execute(
            'UPDATE password_reset_tokens SET revoked_at=UTC_TIMESTAMP() WHERE ' . $kind->foreignKey() . '=:id AND used_at IS NULL AND revoked_at IS NULL',
            ['id' => $id],
        );
    }
}
