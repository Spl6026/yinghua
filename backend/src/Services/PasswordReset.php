<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Support\Secrets;
use Throwable;
use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\Contracts\Mailer;
use Yinghua\Contracts\Transactions;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\PasswordInput;
use Yinghua\DTO\PendingReset;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\ResetRepository;
use Yinghua\Repositories\SessionRepository;

class PasswordReset
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly ResetRepository $tokens,
        private readonly SessionRepository $sessions,
        private readonly PasswordHasher $passwords,
        private readonly RateLimiter $limiter,
        private readonly EventRepository $events,
        private readonly Transactions $transactions,
        private readonly Clock $clock,
        private readonly Config $config,
        private readonly Mailer $mailer,
    ) {}

    public function prepare(IdentityKind $kind, string $email, ClientContext $client): ?PendingReset
    {
        $this->limiter->check($kind->value . '-forgot-ip', $client->ipHash, 15, 900);
        $this->limiter->check($kind->value . '-forgot-email', $email, 3, 900);
        $account = $this->accounts->byEmail($kind, $email);
        $this->events->auth('reset-request', true, 'ACCEPTED', $kind, $account, $email, $client);
        if ($account === null || !$account->isActive() || $account->isRoot) {
            return null;
        }

        return $this->transactions->run(function () use ($account): ?PendingReset {
            $fresh = $this->accounts->find($account->kind, $account->id, true);
            if ($fresh === null || !$fresh->isActive() || $fresh->isRoot) {
                return null;
            }
            $token = Secrets::token();
            $id = Secrets::uuid();
            $this->tokens->create($id, $fresh, $token, $this->clock->now() + $this->config->integer('PASSWORD_RESET_SECONDS', 1800));

            return new PendingReset($id, $fresh, $token);
        });
    }

    public function deliver(PendingReset $pending, ClientContext $client): void
    {
        // HTTP 回應已先以統一訊息送出，寄信耗時或失敗都不會讓外部推測帳號是否存在。
        $path = $pending->account->kind === IdentityKind::Admin ? '/admin/reset-password.html' : '/reset-password.html';
        $url = $this->config->origin() . $path . '#token=' . rawurlencode($pending->token);
        try {
            $this->mailer->send(
                $pending->account->email,
                '映畫竹塹密碼重設',
                "請開啟以下連結重設密碼：\n{$url}\n連結三十分鐘內有效且只能使用一次。若不是您申請，請忽略此信。",
            );
        } catch (Throwable) {
            $this->tokens->revoke($pending->id);
            $this->events->auth(
                'reset-delivery',
                false,
                'MAIL_UNAVAILABLE',
                $pending->account->kind,
                $pending->account,
                $pending->account->email,
                $client,
            );
        }
    }

    public function reset(IdentityKind $kind, PasswordInput $input, ClientContext $client): void
    {
        $this->limiter->check($kind->value . '-reset-ip', $client->ipHash, 20, 900);
        $hash = $this->passwords->hash($input->password);
        $this->transactions->run(function () use ($kind, $input, $client, $hash): void {
            $token = $this->tokens->lock($kind, $input->credential);
            if ($token === null || $token->unavailable || $token->expiresAt <= $this->clock->now()) {
                throw ApplicationError::invalid('token', '重設連結無效、過期或已使用。');
            }
            $account = $this->accounts->find($kind, $token->ownerId, true);
            if ($account === null || !$account->isActive() || $account->isRoot) {
                throw ApplicationError::invalid('token', '重設連結無效、過期或已使用。');
            }
            $this->accounts->savePassword($kind, $account->id, $hash);
            $this->tokens->consume($token->id);
            $this->tokens->revokeAll($kind, $account->id);
            $this->sessions->revokeAll($kind, $account->id);
            $this->events->auth('password-reset', true, 'OK', $kind, $account, $account->email, $client);
        });
    }

    public function change(Account $account, PasswordInput $input, ClientContext $client): void
    {
        $this->limiter->check($account->kind->value . '-change-account', $account->id, 5, 900);
        if (!$this->passwords->verify($input->credential, $account->passwordHash)) {
            $this->events->auth('password-change', false, 'INVALID_CREDENTIALS', $account->kind, $account, $account->email, $client);
            throw ApplicationError::invalid('currentPassword', '目前密碼不正確。');
        }
        $hash = $this->passwords->hash($input->password);
        $this->transactions->run(function () use ($account, $client, $hash): void {
            $fresh = $this->accounts->find($account->kind, $account->id, true);
            if ($fresh === null || !$fresh->isActive() || !hash_equals($fresh->passwordHash, $account->passwordHash)) {
                throw new ApplicationError('UNAUTHENTICATED', '登入狀態已變更，請重新登入。');
            }
            $this->accounts->savePassword($fresh->kind, $fresh->id, $hash);
            $this->tokens->revokeAll($fresh->kind, $fresh->id);
            $this->sessions->revokeAll($fresh->kind, $fresh->id);
            $this->events->auth('password-change', true, 'OK', $fresh->kind, $fresh, $fresh->email, $client);
        });
    }
}
