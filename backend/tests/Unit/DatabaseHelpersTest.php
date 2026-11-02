<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PDOException;
use PHPUnit\Framework\TestCase;
use Yinghua\Repositories\Database;

class DatabaseHelpersTest extends TestCase
{
    public function testContainsEscapesLikeWildcards(): void
    {
        self::assertSame('%咖啡%', Database::contains('咖啡'));
        self::assertSame('%100!%!_off!!%', Database::contains('100%_off!'));
    }

    public function testInListBuildsNamedPlaceholders(): void
    {
        self::assertSame([':id0,:id1', ['id0' => 'a', 'id1' => 'b']], Database::inList('id', ['a', 'b']));
    }

    public function testDuplicateKeyRecognisesOnlyMariaDbError1062(): void
    {
        $duplicate = new PDOException('duplicate', 23000);
        $duplicate->errorInfo = ['23000', 1062, 'Duplicate entry'];
        $foreignKey = new PDOException('foreign key', 23000);
        $foreignKey->errorInfo = ['23000', 1452, 'Cannot add or update a child row'];
        self::assertFalse(Database::isDuplicateKey($foreignKey));
        // PDOException 的 code 由 PHP 設為 SQLSTATE 字串，這裡模擬實際拋出的樣子。
        $reflection = new \ReflectionProperty(\Exception::class, 'code');
        $reflection->setValue($duplicate, '23000');
        $reflection->setValue($foreignKey, '23000');
        self::assertTrue(Database::isDuplicateKey($duplicate));
        self::assertFalse(Database::isDuplicateKey($foreignKey));
    }
}
