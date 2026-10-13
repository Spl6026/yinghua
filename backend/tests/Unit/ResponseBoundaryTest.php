<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\AccountSummary;
use Yinghua\DTO\CaptchaResponse;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;

/** [E-01] 對外物件不能攜帶內部憑證；兩種身分與模擬操作者不可混用。 */
class ResponseBoundaryTest extends TestCase
{
    private function account(IdentityKind $kind): Account
    {
        return new Account(
            'target-id',
            $kind,
            $kind === IdentityKind::Admin ? 'editor' : null,
            'target@example.test',
            '對象',
            'internal-password-hash',
            'active',
            false,
            $kind === IdentityKind::Admin ? 'viewer' : null,
            false,
            2,
            3,
            'internal-deleted-at',
        );
    }

    public function testAccountSummaryDoesNotRetainInternalAccountOrSecrets(): void
    {
        $summary = AccountSummary::fromAccount($this->account(IdentityKind::Admin));
        self::assertSame(['id', 'kind', 'account', 'email', 'displayName', 'status', 'isRoot', 'roleId', 'mustChangePassword', 'version', 'permissionsVersion'], array_keys($summary->toArray()));
        foreach (get_object_vars($summary) as $value) {
            self::assertNotInstanceOf(Account::class, $value);
        }
        // 即使誤用直接 JSON 序列化，DTO 本身也沒有敏感欄位。
        $encoded = json_encode($summary, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('internal-password-hash', $encoded);
        self::assertStringNotContainsString('internal-deleted-at', $encoded);
    }

    public function testCaptchaModesDoNotIntroduceAnswerOrNullAlternativeFields(): void
    {
        $image = (new CaptchaResponse('public-challenge', 'expiry', imageDataUrl: 'data:image/png;base64,test'))->toArray();
        $email = (new CaptchaResponse('public-challenge', 'expiry', message: '已寄送'))->toArray();
        self::assertSame(['challengeToken', 'expiresAt', 'imageDataUrl'], array_keys($image));
        self::assertSame(['challengeToken', 'expiresAt', 'message'], array_keys($email));
    }
}
