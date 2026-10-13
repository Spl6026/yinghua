<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\ClientContext;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Support\Secrets;

class EventRepository
{
    public function __construct(private readonly Database $database, private readonly Secrets $secrets) {}

    public function auth(string $type, bool $success, string $reason, IdentityKind $kind, ?Account $account, string $identifier, ClientContext $client): void
    {
        $this->database->execute(
            'INSERT INTO auth_events (member_id,admin_id,event_type,outcome,reason_code,account_hash,ip_hash,user_agent_hash,request_id) VALUES (:member,:admin,:type,:outcome,:reason,:account,:ip,:agent,:request)',
            ['member' => $kind === IdentityKind::Member ? $account?->id : null,
                'admin' => $kind === IdentityKind::Admin ? $account?->id : null,
                'type' => $type, 'outcome' => $success ? 'success' : 'failure', 'reason' => $reason,
                'account' => $this->secrets->keyed('event-account', $kind->value . ':' . $identifier),
                'ip' => $client->ipHash, 'agent' => $client->userAgentHash, 'request' => $client->requestId],
        );
    }

    /** @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function audit(string $actorId, string $action, string $targetId, ?array $before, ?array $after, ClientContext $client): void
    {
        // [S-026／S-027] 僅接受 Service 組合的安全欄位，不傳入帳號模型或憑證。
        $this->database->execute(
            'INSERT INTO audit_logs (actor_admin_id,action,resource_type,resource_id,before_json,after_json,request_id,ip_hash) VALUES (:actor,:action,\'admin\',:target,:before,:after,:request,:ip)',
            ['actor' => $actorId, 'action' => $action, 'target' => $targetId,
                'before' => $before === null ? null : json_encode($this->masked($before), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'after' => $after === null ? null : json_encode($this->masked($after), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'request' => $client->requestId, 'ip' => $client->ipHash],
        );
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function masked(array $values): array
    {
        $safe = array_intersect_key($values, array_flip(['id', 'kind', 'status', 'isRoot', 'roleId',
            'mustChangePassword', 'version', 'permissionsVersion', 'deleted', 'permissions']));
        foreach (['account', 'email', 'displayName'] as $field) {
            if (isset($values[$field]) && is_string($values[$field])) {
                $safe[$field . 'Hash'] = $this->secrets->keyed('audit-' . $field, $values[$field]);
            }
        }
        return $safe;
    }
}
