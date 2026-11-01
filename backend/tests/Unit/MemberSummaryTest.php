<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\MemberSummary;

class MemberSummaryTest extends TestCase
{
    public function testMemberProjectionMasksUnicodeAndFullEmailDomain(): void
    {
        $data = (new MemberSummary('id', '王小明', 'secret@private.example', 'active', '2026-01-01'))->toArray();
        self::assertSame('王＊＊＊', $data['displayName']);
        self::assertSame('s＊＊＊@p＊＊＊', $data['email']);
        self::assertCount(5, $data);
    }
}
