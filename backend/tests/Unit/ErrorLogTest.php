<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PDOException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Yinghua\Support\ErrorLog;

class ErrorLogTest extends TestCase
{
    public function testDescribesLocationAndCodeWithoutMessageOrArguments(): void
    {
        $error = $this->failWith('user@example.test', 'S3cret-password');
        $line = ErrorLog::describe($error);
        self::assertStringContainsString('exception=RuntimeException', $line);
        self::assertStringContainsString('code=7', $line);
        self::assertMatchesRegularExpression('/at=tests\/Unit\/ErrorLogTest\.php:\d+/', $line);
        self::assertStringContainsString('ErrorLogTest->failWith', $line);
        self::assertStringNotContainsString('user@example.test', $line);
        self::assertStringNotContainsString('S3cret-password', $line);
    }

    public function testDatabaseErrorsKeepSqlStateAndDriverCodeButNotTheDuplicateValue(): void
    {
        $duplicate = new PDOException("SQLSTATE[23000]: Duplicate entry 'user@example.test' for key 'email'");
        $duplicate->errorInfo = ['23000', 1062, "Duplicate entry 'user@example.test' for key 'email'"];
        $line = ErrorLog::describe(new RuntimeException('wrapper', 0, $duplicate));
        self::assertStringContainsString('caused_by exception=PDOException code=23000/1062', $line);
        self::assertStringNotContainsString('user@example.test', $line);
    }

    private function failWith(string $email, string $password): RuntimeException
    {
        return new RuntimeException('Could not save ' . $email . ' ' . $password, 7);
    }
}
