<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Config;
use Yinghua\Contracts\GoogleHttp;
use Yinghua\DTO\RemoteResponse;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\IdentityKind;
use Yinghua\Policies\GoogleUrlPolicy;
use Yinghua\Services\Authorization;
use Yinghua\Services\MapResolver;
use Yinghua\Services\RateLimiter;

/** [E-19] 測外部邊界與惡意 redirect；不消耗 Google 額度，不替代真實 HTTP 授權驗收。 */
class MapResolverTest extends TestCase
{
    private string|false $previousKey;

    protected function setUp(): void
    {
        $this->previousKey = getenv('GOOGLE_PLACES_API_KEY');
        putenv('GOOGLE_PLACES_API_KEY=unit-test-key');
    }

    protected function tearDown(): void
    {
        putenv($this->previousKey === false ? 'GOOGLE_PLACES_API_KEY' : 'GOOGLE_PLACES_API_KEY=' . $this->previousKey);
    }

    private function actor(): Account
    {
        return new Account('03000000-0000-4000-8000-000000000099', IdentityKind::Admin, 'editor', 'editor@example.test', '測試', 'unused', 'active', false, 'editor', false, 1, 1, null);
    }

    private function resolver(GoogleHttp $http): MapResolver
    {
        $authorization = $this->createMock(Authorization::class);
        $authorization->expects(self::once())->method('require')->with(self::isInstanceOf(Account::class), 'attractions', 'edit')->willReturn($this->actor());
        $limiter = $this->createStub(RateLimiter::class);

        return new MapResolver($http, new GoogleUrlPolicy(), new Config(), $authorization, $limiter, new \Yinghua\Adapters\GoogleOpeningHours());
    }

    public function testUnsafeRedirectIsRejectedBeforeSecondNetworkCall(): void
    {
        $http = $this->createMock(GoogleHttp::class);
        $http->expects(self::once())->method('request')->with('https://maps.app.goo.gl/example')->willReturn(new RemoteResponse(302, '', ['location' => 'https://127.0.0.1/private']));
        $this->expectException(ApplicationError::class);
        $this->resolver($http)->resolve($this->actor(), 'https://maps.app.goo.gl/example', 'edit');
    }

    public function testRedirectLoopCannotKeepCallingNetwork(): void
    {
        $http = $this->createMock(GoogleHttp::class);
        $http->expects(self::once())->method('request')->willReturn(new RemoteResponse(302, '', ['location' => 'https://maps.app.goo.gl/example']));
        $this->expectException(ApplicationError::class);
        $this->resolver($http)->resolve($this->actor(), 'https://maps.app.goo.gl/example', 'edit');
    }

    public function testMalformedProviderJsonIsUnavailable(): void
    {
        $http = $this->createMock(GoogleHttp::class);
        $http->expects(self::once())->method('request')->willReturn(new RemoteResponse(200, 'not-json', []));
        try {
            $this->resolver($http)->resolve($this->actor(), 'https://www.google.com/maps?query_place_id=ChIJ_test', 'edit');
            self::fail('Expected sanitized provider error.');
        } catch (ApplicationError $error) {
            self::assertSame('MAP_UNAVAILABLE', $error->errorCode);
            self::assertStringNotContainsString('unit-test-key', $error->getMessage());
        }
    }

    public function testQuotaErrorIsSanitized(): void
    {
        $http = $this->createMock(GoogleHttp::class);
        $http->expects(self::once())->method('request')->willReturn(new RemoteResponse(429, '{"key":"unit-test-key"}', []));
        try {
            $this->resolver($http)->resolve($this->actor(), 'https://www.google.com/maps?q=新竹公園', 'edit');
            self::fail('Expected unavailable provider.');
        } catch (ApplicationError $error) {
            self::assertSame('MAP_UNAVAILABLE', $error->errorCode);
            self::assertStringNotContainsString('unit-test-key', $error->getMessage());
        }
    }

    public function testMissingKeyDoesNotCallProvider(): void
    {
        putenv('GOOGLE_PLACES_API_KEY=');
        $http = $this->createMock(GoogleHttp::class);
        $http->expects(self::never())->method('request');
        $this->expectException(ApplicationError::class);
        $this->resolver($http)->resolve($this->actor(), 'https://maps.app.goo.gl/example', 'edit');
    }

    public function testPrivateAndReservedNetworksAreRejected(): void
    {
        $policy = new GoogleUrlPolicy();
        foreach (['127.0.0.1','10.0.0.1','172.16.0.1','192.168.0.1','169.254.169.254','100.64.0.1','100.127.255.254','224.0.0.1','0.0.0.0','::1'] as $ip) {
            self::assertFalse($policy->publicIp($ip), $ip);
        }
        self::assertTrue($policy->publicIp('8.8.8.8'));
    }

    public function testCandidateUsesTheSharedStructuredSchedule(): void
    {
        $http = $this->createMock(GoogleHttp::class);
        $http->expects(self::once())->method('request')->with(
            self::stringContains('places.googleapis.com/v1/places/'),
            self::callback(static fn(array $headers): bool => str_contains($headers['X-Goog-FieldMask'] ?? '', 'regularOpeningHours.periods')),
            null,
            true,
        )->willReturn(new RemoteResponse(200, json_encode(['id' => 'ChIJ_test','displayName' => ['text' => '店家'],'regularOpeningHours' => ['periods' => [['open' => ['day' => 1,'hour' => 9],'close' => ['day' => 1,'hour' => 12]]]]], JSON_THROW_ON_ERROR), []));
        $result = $this->resolver($http)->resolve($this->actor(), 'https://www.google.com/maps?query_place_id=ChIJ_test', 'edit');
        self::assertSame('09:00', $result['candidates'][0]['openingHours']['days'][1]['periods'][0]['opens']);
        self::assertSame('每週時段不同，詳見介紹', $result['candidates'][0]['hours']);
    }
}
