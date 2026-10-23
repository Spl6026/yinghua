<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\AccountRepository;

/** [S：G15；E-27] 會員所有權不使用管理員 RBAC。寫入交易內重查停權／刪除狀態。 */
class MemberAccess
{
    public function __construct(private readonly AccountRepository $accounts) {}

    public function require(Account $actor, bool $lock = false): Account
    {
        if ($actor->kind !== IdentityKind::Member) {
            throw new ApplicationError('UNAUTHENTICATED', '請先登入會員。');
        }
        $fresh = $this->accounts->find(IdentityKind::Member, $actor->id, $lock);
        if ($fresh === null || !$fresh->isActive()) {
            throw new ApplicationError('UNAUTHENTICATED', '會員登入已失效，請重新登入。');
        }

        return $fresh;
    }
}
