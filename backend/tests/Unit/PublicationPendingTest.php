<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Exceptions\PublicationPending;
use Yinghua\Http\Kernel;

final class PublicationPendingTest extends TestCase
{
    public function testMissingSnapshotKeepsSafe503WithoutInternalDiagnosticsInPayload(): void
    {
        $error = new PublicationPending(null, []);
        self::assertStringContainsString('publication_reason=missing_snapshot', $error->diagnostic());
        self::assertSame('PUBLICATION_PENDING', $error->errorCode);
        self::assertSame(503, Kernel::error($error)->status);
        self::assertSame(5, $error->retryAfter);
    }

    public function testOnlyFixedSectionNamesAndValidatedHashesReachDiagnostics(): void
    {
        $current = ['generation' => str_repeat('a', 64), 'newsAliases' => ['secret-path' => 'secret-content'], 'seo' => 'secret-settings'];
        $expected = ['generation' => "secret\nFORGED", 'newsAliases' => [], 'seo' => [], 'secret-field' => 'secret-value'];
        $error = new PublicationPending($current, $expected);
        self::assertStringContainsString('sections=newsAliases,seo', $error->diagnostic());
        self::assertStringContainsString('published=' . str_repeat('a', 64), $error->diagnostic());
        self::assertStringContainsString('expected=unavailable', $error->diagnostic());
        self::assertStringNotContainsString('secret', $error->diagnostic());
        self::assertStringNotContainsString("\n", $error->diagnostic());
        self::assertStringNotContainsString('sections=', json_encode(Kernel::error($error)->payload, JSON_THROW_ON_ERROR));
    }
}
