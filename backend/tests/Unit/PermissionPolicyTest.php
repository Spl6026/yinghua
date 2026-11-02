<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Policies\PermissionPolicy;
use Yinghua\DTO\AdminWriteInput;

class PermissionPolicyTest extends TestCase
{
    private function account(string $id, bool $root = false): Account
    {
        return new Account(
            $id,
            IdentityKind::Admin,
            $root ? 'admin' : 'editor',
            'test@example.test',
            '測試',
            '',
            'active',
            $root,
            $root ? 'root' : 'content-editor',
            1,
            1,
            null,
        );
    }

    public function testMissingPermissionDeniesAccess(): void
    {
        $this->expectException(ApplicationError::class);
        (new PermissionPolicy())->require($this->account('one'), [], 'admins', 'view');
    }

    public function testRootCannotBeWrittenByAnotherRoot(): void
    {
        $this->expectException(ApplicationError::class);
        (new PermissionPolicy())->writableTarget($this->account('root', true));
    }

    public function testOrdinaryAdministratorIsNotProtectedLikeRoot(): void
    {
        (new PermissionPolicy())->writableTarget($this->account('one'));
        self::assertTrue(true);
    }

    public function testCannotGrantRightsBeyondOwnMatrix(): void
    {
        $this->expectException(ApplicationError::class);
        (new PermissionPolicy())->grantable($this->account('one'), [], [['module' => 'admins', 'action' => 'delete', 'allowed' => true]]);
    }

    public function testRootMassAssignmentIsRejected(): void
    {
        $this->expectException(ApplicationError::class);
        AdminWriteInput::fromArray(['isRoot' => true], true);
    }

    public function testAdministratorCannotDeleteOwnAccount(): void
    {
        $this->expectException(ApplicationError::class);
        $this->expectExceptionMessage('不能刪除目前登入中的管理員帳號。');
        $editor = $this->account('editor-id');
        (new PermissionPolicy())->deletableTarget($editor, $editor);
    }

    public function testAdministratorCanDeleteAnotherOrdinaryAdministrator(): void
    {
        (new PermissionPolicy())->deletableTarget($this->account('one'), $this->account('two'));
        $this->addToAssertionCount(1);
    }
}
