<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Policies\AttractionContentPolicy;

class AttractionContentTest extends TestCase
{
    public function testAuthoredValuesWinAndAreNotRewritten(): void
    {
        $value = AttractionContentPolicy::select('自填摘要', "自填介紹\n第二行", true);
        self::assertSame('自填摘要', $value->summary);
        self::assertSame("自填介紹\n第二行", $value->description);
        self::assertSame(['summary' => 'authored', 'description' => 'authored'], $value->sources());
    }

    public function testExcerptUsesUnicodeAndDoesNotChangeDescription(): void
    {
        $description = "介紹\n\n" . str_repeat('竹', 130);
        $value = AttractionContentPolicy::select("\n　", $description, false);
        self::assertSame(120, mb_strlen($value->summary));
        self::assertStringStartsWith('介紹 竹', $value->summary);
        self::assertStringEndsWith('…', $value->summary);
        self::assertSame($description, $value->description);
        self::assertSame('description-excerpt', $value->summarySource);
    }

    public function testEmptyDescriptionOnlyRequestsGoogleWhenLinked(): void
    {
        self::assertSame('google-pending', AttractionContentPolicy::select('', '', true)->descriptionSource);
        self::assertSame('empty', AttractionContentPolicy::select('', '', false)->descriptionSource);
        self::assertSame('自填', AttractionContentPolicy::select('自填', '', true)->summary);
    }
}
