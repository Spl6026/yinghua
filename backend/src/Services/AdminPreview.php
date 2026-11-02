<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\AuthGrant;
use Yinghua\DTO\ClientContext;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\SessionRepository;
use Yinghua\Support\Secrets;

/** 只有 root 管理員能以其他帳號身分進入唯讀預覽；實際身分仍以資料庫與 session 為準，不信任前端傳來的值。 */
class AdminPreview
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly SessionRepository $sessions,
        private readonly EventRepository $events,
        private readonly Transactions $transactions,
    ) {}

    /** @return list<array{id:string,account:string,displayName:string}> */
    public function targets(Account $owner): array
    {
        $this->root($owner);

        return $this->accounts->previewTargets();
    }

    public function switch(Account $owner, string $previousToken, ?string $targetId, ClientContext $client): AuthGrant
    {
        return $this->transactions->run(function () use ($owner, $previousToken, $targetId, $client): AuthGrant {
            $root = $this->root($owner, true);
            $target = $targetId === null ? null : $this->accounts->find(IdentityKind::Admin, $targetId, true);
            if ($targetId !== null && ($target === null || !$target->isActive() || $target->isRoot)) {
                throw ApplicationError::invalid('adminId', '請選擇啟用中的一般管理員。');
            }
            $session = $this->sessions->find(IdentityKind::Admin, $previousToken, true);
            if ($session === null || $session->ownerId !== $root->id) {
                throw new ApplicationError('UNAUTHENTICATED', '請先登入。');
            }
            $token = Secrets::token();
            $this->sessions->preview($session, $targetId, $token);
            $this->events->audit(
                $root->id,
                $targetId === null ? 'preview-stop' : 'preview-start',
                $targetId ?? $session->previewAdminId ?? $root->id,
                ['id' => $session->previewAdminId ?? $root->id],
                ['id' => $targetId ?? $root->id],
                $client,
            );

            return new AuthGrant($root, $token);
        });
    }

    private function root(Account $owner, bool $lock = false): Account
    {
        $fresh = $owner->kind === IdentityKind::Admin ? $this->accounts->find(IdentityKind::Admin, $owner->id, $lock) : null;
        if ($fresh === null || !$fresh->isActive() || !$fresh->isRoot) {
            throw ApplicationError::forbidden();
        }

        return $fresh;
    }
}
