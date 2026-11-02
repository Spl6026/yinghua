<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\AccountSummary;
use Yinghua\DTO\CaptchaResponse;
use Yinghua\DTO\IdentityResponse;
use Yinghua\DTO\PreviewStatus;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;

/** 對外回應的物件不能帶出內部憑證；會員與管理員兩種身分、以及模擬操作者都不可混用。 */
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

    public function testMemberResponseCannotCarryAdministratorPrivileges(): void
    {
        $response = new IdentityResponse(
            AccountSummary::fromAccount($this->account(IdentityKind::Member)),
            [['module' => 'admins', 'action' => 'delete', 'allowed' => true]],
            new PreviewStatus(true, true, 'target-id', 'owner-id', 'admin', '操作者'),
        );
        self::assertSame(['member'], array_keys($response->toArray()));
        self::assertSame('member', $response->toArray()['member']['kind']);
    }

    public function testPreviewIdentityKeepsOperatorSeparateAndUnavailableRightsEmpty(): void
    {
        $summary = AccountSummary::fromAccount($this->account(IdentityKind::Admin));
        $login = (new IdentityResponse($summary))->toArray();
        self::assertArrayNotHasKey('preview', $login);
        $me = (new IdentityResponse($summary, [], new PreviewStatus(true, false, 'target-id', 'owner-id', 'admin', '操作者')))->toArray();
        self::assertSame('target-id', $me['admin']['id']);
        self::assertSame('owner-id', $me['preview']['operator']['id']);
        self::assertTrue($me['preview']['readOnly']);
        self::assertFalse($me['preview']['targetAvailable']);
        self::assertSame([], $me['permissions']);
    }

    public function testCaptchaModesDoNotIntroduceAnswerOrNullAlternativeFields(): void
    {
        $image = (new CaptchaResponse('public-challenge', 'expiry', imageDataUrl: 'data:image/png;base64,test'))->toArray();
        $email = (new CaptchaResponse('public-challenge', 'expiry', message: '已寄送'))->toArray();
        self::assertSame(['challengeToken', 'expiresAt', 'imageDataUrl'], array_keys($image));
        self::assertSame(['challengeToken', 'expiresAt', 'message'], array_keys($email));
    }
}
