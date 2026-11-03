<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Exceptions\ErrorCode;

/** 會員只能存取自己的資料，不套用管理員的角色權限。寫入時會在交易內重新確認帳號沒有被停權或刪除。 */
class MemberAccess
{
    public function __construct(private readonly AccountRepository $accounts) {}

    public function require(Account $actor, bool $lock = false): Account
    {
        if ($actor->kind !== IdentityKind::Member) {
            throw new ApplicationError(ErrorCode::Unauthenticated, '請先登入會員。');
        }
        $fresh = $this->accounts->find(IdentityKind::Member, $actor->id, $lock);
        if ($fresh === null || !$fresh->isActive()) {
            throw new ApplicationError(ErrorCode::Unauthenticated, '會員登入已失效，請重新登入。');
        }

        return $fresh;
    }
}
