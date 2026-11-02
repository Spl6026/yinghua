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

    public function auth(
        string $type,
        bool $success,
        string $reason,
        IdentityKind $kind,
        ?Account $account,
        string $identifier,
        ClientContext $client,
    ): void {
        $this->database->execute(
            'INSERT INTO auth_events (member_id,admin_id,event_type,outcome,reason_code,account_hash,ip_hash,user_agent_hash,request_id) VALUES (:member,:admin,:type,:outcome,:reason,:account,:ip,:agent,:request)',
            [
                'member' => $kind === IdentityKind::Member ? $account?->id : null,
                'admin' => $kind === IdentityKind::Admin ? $account?->id : null,
                'type' => $type,
                'outcome' => $success ? 'success' : 'failure',
                'reason' => $reason,
                'account' => $this->secrets->keyed('event-account', $kind->value . ':' . $identifier),
                'ip' => $client->ipHash,
                'agent' => $client->userAgentHash,
                'request' => $client->requestId,
            ],
        );
    }

    /** @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     */
    public function audit(
        string $actorId,
        string $action,
        string $targetId,
        ?array $before,
        ?array $after,
        ClientContext $client,
    ): void {
        // 只寫入 Service 整理好的安全欄位，不直接傳入帳號模型或任何憑證。
        $this->database->execute(
            'INSERT INTO audit_logs (actor_admin_id,action,resource_type,resource_id,before_json,after_json,request_id,ip_hash) VALUES (:actor,:action,\'admin\',:target,:before,:after,:request,:ip)',
            [
                'actor' => $actorId,
                'action' => $action,
                'target' => $targetId,
                'before' => $before === null ? null : json_encode($this->masked($before), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'after' => $after === null ? null : json_encode($this->masked($after), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'request' => $client->requestId,
                'ip' => $client->ipHash,
            ],
        );
    }

    /** @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function masked(array $values): array
    {
        $safe = array_intersect_key($values, array_flip(['id', 'kind', 'status', 'isRoot', 'roleId',
            'version', 'permissionsVersion', 'deleted', 'permissions']));
        foreach (['account', 'email', 'displayName'] as $field) {
            if (isset($values[$field]) && is_string($values[$field])) {
                $safe[$field . 'Hash'] = $this->secrets->keyed('audit-' . $field, $values[$field]);
            }
        }
        return $safe;
    }

    /** 內容稽核只記錄識別、版本與狀態，不保存上傳內容或金鑰。 */
    public function content(
        string $actorId,
        string $action,
        string $resource,
        string $id,
        ?int $before,
        int $after,
        string $status,
        ClientContext $client,
    ): void {
        $this->database->execute(
            'INSERT INTO audit_logs (actor_admin_id,action,resource_type,resource_id,before_json,after_json,request_id,ip_hash) VALUES (:actor,:action,:resource,:id,:before,:after,:request,:ip)',
            [
                'actor' => $actorId,
                'action' => $action,
                'resource' => $resource,
                'id' => $id,
                'before' => $before === null ? null : json_encode(['version' => $before], JSON_THROW_ON_ERROR),
                'after' => json_encode(['version' => $after,'status' => $status], JSON_THROW_ON_ERROR),
                'request' => $client->requestId,
                'ip' => $client->ipHash,
            ],
        );
    }
}
