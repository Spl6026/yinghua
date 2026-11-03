<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Exceptions\PublicationPending;
use Yinghua\Http\Kernel;
use Yinghua\Exceptions\ErrorCode;

final class PublicationPendingTest extends TestCase
{
    public function testMissingSnapshotKeepsSafe503WithoutInternalDiagnosticsInPayload(): void
    {
        $error = new PublicationPending();
        self::assertSame('publication_reason=missing_snapshot', $error->diagnostic());
        self::assertSame(ErrorCode::PublicationPending, $error->errorCode);
        self::assertSame(503, Kernel::error($error)->status);
        self::assertSame(5, $error->retryAfter);
        self::assertStringNotContainsString('publication_reason', json_encode(Kernel::error($error)->payload, JSON_THROW_ON_ERROR));
    }
}
