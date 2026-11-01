<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Support\Secrets;
use RuntimeException;
use Yinghua\Config;
use Yinghua\Contracts\Transactions;
use Yinghua\DTO\AuthGrant;
use Yinghua\DTO\AuthenticatedIdentity;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\LoginInput;
use Yinghua\DTO\RegisterInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\AccountConflict;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\ConsentRepository;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\SessionRepository;

class Authentication
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly SessionRepository $sessions,
        private readonly PasswordHasher $passwords,
        private readonly RateLimiter $limiter,
        private readonly EventRepository $events,
        private readonly Transactions $transactions,
        private readonly Captcha $captcha,
        private readonly Config $config,
        private readonly ConsentRepository $consents,
        private readonly ?PublishedPolicies $policies = null,
    ) {}

    public function current(IdentityKind $kind, string $token): AuthenticatedIdentity
    {
        $session = $this->sessions->find($kind, $token);
        $account = $session === null ? null : $this->accounts->find($kind, $session->ownerId);
        if ($session === null || $account === null || !$account->isActive()) {
            throw new ApplicationError('UNAUTHENTICATED', '請先登入。');
        }

        if ($session->previewAdminId !== null && ($kind !== IdentityKind::Admin || !$account->isRoot)) {
            throw new ApplicationError('UNAUTHENTICATED', '請先登入。');
        }
        $preview = $session->previewAdminId === null ? null : $this->accounts->find(IdentityKind::Admin, $session->previewAdminId);

        return new AuthenticatedIdentity($account, $preview, $session->previewAdminId, $session->timing);
    }

    public function login(IdentityKind $kind, LoginInput $input, ClientContext $client, string $previousToken = ''): AuthGrant
    {
        try {
            $this->limiter->check($kind->value . '-login-ip', $client->ipHash, 40, 900);
            $this->limiter->check($kind->value . '-login-account', $input->identifier, 5, 900);
        } catch (ApplicationError $error) {
            $this->events->auth('login', false, 'RATE_LIMITED', $kind, null, $input->identifier, $client);
            throw $error;
        }
        $account = $this->accounts->byLogin($kind, $input->identifier);
        $verified = $this->passwords->verify($input->password, $account?->passwordHash);
        if ($account === null || !$account->isActive() || !$verified) {
            $this->events->auth('login', false, 'INVALID_CREDENTIALS', $kind, $account, $input->identifier, $client);
            throw new ApplicationError('INVALID_CREDENTIALS', '帳號或密碼不正確。');
        }
        $grant = $this->transactions->run(function () use ($kind, $account, $input, $client, $previousToken): AuthGrant {
            $fresh = $this->accounts->find($kind, $account->id, true);
            if ($fresh === null || !$fresh->isActive() || !hash_equals($fresh->passwordHash, $account->passwordHash)) {
                throw new ApplicationError('INVALID_CREDENTIALS', '帳號或密碼不正確。');
            }
            if ($this->passwords->needsRehash($fresh->passwordHash)) {
                $this->accounts->rehashPassword($kind, $fresh->id, $this->passwords->hash($input->password));
            }
            $token = Secrets::token();
            $this->sessions->create($fresh, $token, $client);
            $this->sessions->revoke($kind, $previousToken);
            $this->accounts->loginSeen($kind, $fresh->id);
            $this->events->auth('login', true, 'OK', $kind, $fresh, $input->identifier, $client);

            return new AuthGrant($fresh, $token);
        });
        $this->limiter->clear($kind->value . '-login-account', $input->identifier);

        return $grant;
    }

    public function register(RegisterInput $input, ClientContext $client, string $previousToken = ''): AuthGrant
    {
        $this->limiter->check('register-ip', $client->ipHash, 20, 3600);
        $this->limiter->check('register-email', $input->email, 5, 900);
        if ($input->termsVersion !== ($this->policies?->current() ?? $this->config->string('TERMS_VERSION', 'local-development-v1'))) {
            throw ApplicationError::invalid('termsVersion', '條款版本已更新，請重新閱讀。');
        }
        $hash = $this->passwords->hash($input->password);
        try {
            $grant = $this->transactions->run(function () use ($input, $client, $hash, $previousToken): ?AuthGrant {
                if ($this->policies !== null && $input->termsVersion !== $this->policies->current(true)) {
                    throw ApplicationError::invalid('termsVersion', '條款版本已更新，請重新閱讀。');
                }
                if (!$this->captcha->consume($input, $client)) {
                    $this->events->auth('register', false, 'INVALID_CHALLENGE', IdentityKind::Member, null, $input->email, $client);

                    return null;
                }
                $id = Secrets::uuid();
                $this->accounts->createMember($id, $input, $hash);
                $this->consents->save($id, $input->termsVersion, $client->ipHash);
                $account = $this->accounts->find(IdentityKind::Member, $id);
                if ($account === null) {
                    throw new RuntimeException('Created member was not found.');
                }
                $token = Secrets::token();
                $this->sessions->create($account, $token, $client);
                $this->sessions->revoke(IdentityKind::Member, $previousToken);
                $this->events->auth('register', true, 'OK', IdentityKind::Member, $account, $input->email, $client);

                return new AuthGrant($account, $token);
            });
        } catch (AccountConflict $error) {
            if ($this->accounts->byEmail(IdentityKind::Member, $input->email) !== null) {
                throw new ApplicationError('CONFLICT', '此電子郵件已註冊。', ['email' => '此電子郵件已註冊。']);
            }
            throw $error;
        }
        if ($grant === null) {
            throw ApplicationError::invalid('answer', '驗證碼無效、過期或已使用，請重新取得。');
        }

        return $grant;
    }

    public function logout(Account $account, string $token, ClientContext $client): void
    {
        $this->transactions->run(function () use ($account, $token, $client): void {
            $this->sessions->revoke($account->kind, $token);
            $this->events->auth('logout', true, 'OK', $account->kind, $account, $account->email, $client);
        });
    }
}
