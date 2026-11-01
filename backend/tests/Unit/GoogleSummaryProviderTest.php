<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Adapters\GoogleSummaryProvider;
use Yinghua\Config;
use Yinghua\Contracts\GoogleHttp;
use Yinghua\DTO\RemoteResponse;

class GoogleSummaryProviderTest extends TestCase
{
    public function testOriginalTextAndSafeAttributionsArePreserved(): void
    {
        $before = getenv('GOOGLE_PLACES_API_KEY');
        putenv('GOOGLE_PLACES_API_KEY=unit-only');
        try {
            $http = $this->createMock(GoogleHttp::class);
            $text = "  原文 <script>不是HTML</script>\n第二行  ";
            $http->expects(self::once())->method('request')->with(
                'https://places.googleapis.com/v1/places/ChIunit123?languageCode=zh-TW',
                self::callback(static fn(array $headers): bool => $headers['X-Goog-FieldMask'] === 'editorialSummary,attributions'),
                null,
                true,
            )->willReturn(new RemoteResponse(200, json_encode(['editorialSummary' => ['text' => $text], 'attributions' => [['provider' => 'Provider', 'providerUri' => 'javascript:bad']]], JSON_THROW_ON_ERROR), []));
            $value = (new GoogleSummaryProvider($http, new Config()))->fetch('ChIunit123');
            self::assertSame($text, $value->text);
            self::assertSame('', $value->attributions[0]['providerUri']);
            self::assertSame('available', $value->toArray()['status']);
        } finally {
            putenv($before === false ? 'GOOGLE_PLACES_API_KEY' : 'GOOGLE_PLACES_API_KEY=' . $before);
        }
    }

    public function testMissingKeyDoesNotCallGoogle(): void
    {
        $before = getenv('GOOGLE_PLACES_API_KEY');
        putenv('GOOGLE_PLACES_API_KEY');
        try {
            $http = $this->createMock(GoogleHttp::class);
            $http->expects(self::never())->method('request');
            self::assertSame('unavailable', (new GoogleSummaryProvider($http, new Config()))->fetch('ChIunit123')->toArray()['status']);
        } finally {
            putenv($before === false ? 'GOOGLE_PLACES_API_KEY' : 'GOOGLE_PLACES_API_KEY=' . $before);
        }
    }

    public function testVendorFailuresBecomeSafeUnavailableWithoutExposingBodies(): void
    {
        $before = getenv('GOOGLE_PLACES_API_KEY');
        putenv('GOOGLE_PLACES_API_KEY=unit-only');
        try {
            foreach ([new RemoteResponse(429, 'private upstream error', []), new RemoteResponse(200, '{bad-json', []), new RemoteResponse(200, '{}', [])] as $response) {
                $http = $this->createStub(GoogleHttp::class);
                $http->method('request')->willReturn($response);
                $result = (new GoogleSummaryProvider($http, new Config()))->fetch('ChIunit123');
                self::assertSame('unavailable', $result->toArray()['status']);
                self::assertSame('', $result->text);
            }
            $http = $this->createStub(GoogleHttp::class);
            $http->method('request')->willThrowException(new \RuntimeException('private timeout detail'));
            self::assertSame('', (new GoogleSummaryProvider($http, new Config()))->fetch('ChIunit123')->text);
        } finally {
            putenv($before === false ? 'GOOGLE_PLACES_API_KEY' : 'GOOGLE_PLACES_API_KEY=' . $before);
        }
    }
}
