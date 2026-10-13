<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\DTO\ClientContext;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Support\Secrets;

class SessionRepository
{
    public function __construct(private readonly Database $database, private readonly Config $config, private readonly Clock $clock) {}

    public function create(Account $account, string $token, ClientContext $client): void
    {
        $now = $this->clock->now();
        $this->database->execute(
            'INSERT INTO sessions (id,member_id,admin_id,token_hash,expires_at,idle_expires_at,last_seen_at,ip_hash,user_agent_hash) VALUES (:id,:member,:admin,:token,:expires,:idle,:seen,:ip,:agent)',
            ['id' => Secrets::uuid(), 'member' => $account->kind === IdentityKind::Member ? $account->id : null,
                'admin' => $account->kind === IdentityKind::Admin ? $account->id : null, 'token' => hash('sha256', $token),
                'expires' => gmdate('Y-m-d H:i:s', $now + $this->config->integer('AUTH_SESSION_SECONDS', 43200)),
                'idle' => gmdate('Y-m-d H:i:s', $now + $this->config->integer('AUTH_IDLE_SECONDS', 1800)),
                'seen' => gmdate('Y-m-d H:i:s', $now), 'ip' => $client->ipHash, 'agent' => $client->userAgentHash],
        );
    }

    public function owner(IdentityKind $kind, string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        $row = $this->database->row(
            'SELECT id,' . $kind->foreignKey() . ' AS owner FROM sessions WHERE token_hash=:token AND '
            . $kind->foreignKey() . ' IS NOT NULL AND revoked_at IS NULL AND expires_at>:now AND idle_expires_at>:idle',
            ['token' => hash('sha256', $token), 'now' => gmdate('Y-m-d H:i:s', $this->clock->now()), 'idle' => gmdate('Y-m-d H:i:s', $this->clock->now())],
        );
        if ($row === null) {
            return null;
        }
        $record = new Row($row);
        $this->database->execute(
            'UPDATE sessions SET last_seen_at=:now,idle_expires_at=LEAST(expires_at,:idle) WHERE id=:id AND revoked_at IS NULL',
            ['id' => $record->string('id'), 'now' => gmdate('Y-m-d H:i:s', $this->clock->now()),
                'idle' => gmdate('Y-m-d H:i:s', $this->clock->now() + $this->config->integer('AUTH_IDLE_SECONDS', 1800))],
        );

        return $record->string('owner');
    }

    public function revoke(IdentityKind $kind, string $token): void
    {
        $this->database->execute(
            'UPDATE sessions SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=:token AND ' . $kind->foreignKey() . ' IS NOT NULL AND revoked_at IS NULL',
            ['token' => hash('sha256', $token)],
        );
    }

    public function revokeAll(IdentityKind $kind, string $id): void
    {
        $this->database->execute('UPDATE sessions SET revoked_at=UTC_TIMESTAMP() WHERE ' . $kind->foreignKey() . '=:id AND revoked_at IS NULL', ['id' => $id]);
    }
}
