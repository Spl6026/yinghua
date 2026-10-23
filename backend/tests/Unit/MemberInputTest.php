<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\FavoriteStatusInput;
use Yinghua\DTO\ItineraryItemInput;
use Yinghua\DTO\MemberPageInput;
use Yinghua\Exceptions\ApplicationError;

class MemberInputTest extends TestCase
{
    public function testVersionZeroAndCursorRoundTrip(): void
    {
        self::assertSame(0, ItineraryItemInput::fromArray(['version' => 0])->version);
        $position = ['at' => '2026-10-23 01:02:03', 'id' => '06000000-0000-4000-8000-000000000001'];
        self::assertSame($position, MemberPageInput::fromArray(['cursor' => MemberPageInput::cursor($position), 'pageSize' => '100'])->position);
        self::assertSame([], FavoriteStatusInput::fromArray(['ids' => ''])->ids);
    }

    public function testMemberIdCannotBeAssigned(): void
    {
        $this->expectException(ApplicationError::class);
        ItineraryItemInput::fromArray(['version' => 0, 'memberId' => 'other']);
    }

    public function testMalformedCursorRejected(): void
    {
        $this->expectException(ApplicationError::class);
        MemberPageInput::fromArray(['cursor' => 'eyJ4Ijox']);
    }

    public function testStatusIdsCannotReferencePrototypeIndexes(): void
    {
        $this->expectException(ApplicationError::class);
        FavoriteStatusInput::fromArray(['ids' => '1']);
    }

    public function testStringVersionIsNotCoerced(): void
    {
        $this->expectException(ApplicationError::class);
        ItineraryItemInput::fromArray(['version' => '1']);
    }
}
