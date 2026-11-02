<?php

declare(strict_types=1);

namespace Yinghua\Services;

use RuntimeException;
use Yinghua\Contracts\Transactions;
use Yinghua\DTO\AdminWriteInput;
use Yinghua\DTO\Input;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\SessionRepository;
use Yinghua\Repositories\ResetRepository;
use Yinghua\Support\Secrets;

class RootInitializer
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly PasswordHasher $passwords,
        private readonly Transactions $transactions,
        private readonly SessionRepository $sessions,
        private readonly ResetRepository $resets,
    ) {}

    public function initialize(string $email, string $password): Account
    {
        $input = new Input(['email' => $email, 'password' => $password]);
        $email = $input->email();
        $hash = $this->passwords->hash($input->password());

        return $this->transactions->run(function () use ($email, $hash): Account {
            $existing = $this->accounts->byLogin(IdentityKind::Admin, 'admin');
            if ($existing !== null) {
                return $existing;
            }
            $id = Secrets::uuid();
            $this->accounts->createAdmin($id, new AdminWriteInput('admin', '系統管理員', $email, 'root', 'active', null, null), $hash, true);

            return $this->accounts->find(IdentityKind::Admin, $id) ?? throw new RuntimeException('Root initialization failed.');
        });
    }

    public function recover(string $password): void
    {
        $input = new Input(['password' => $password]);
        $hash = $this->passwords->hash($input->password());
        $account = $this->accounts->byLogin(IdentityKind::Admin, 'admin')
            ?? throw new RuntimeException('Root account has not been initialized.');
        $this->transactions->run(function () use ($account, $hash): void {
            $this->accounts->find(IdentityKind::Admin, $account->id, true);
            $this->accounts->savePassword(IdentityKind::Admin, $account->id, $hash);
            $this->sessions->revokeAll(IdentityKind::Admin, $account->id);
            $this->resets->revokeAll(IdentityKind::Admin, $account->id);
        });
    }
}
