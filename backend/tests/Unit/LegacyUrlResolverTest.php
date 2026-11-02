<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\LegacyUrlType;
use Yinghua\Services\LegacyUrlResolver;

class LegacyUrlResolverTest extends TestCase
{
    /** @return array<string,mixed> */
    private function snapshot(): array
    {
        return [
            'items' => [['id' => 'a1','legacyId' => 1,'url' => '/museum.html'],['id' => 'a2','legacyId' => 2,'url' => '/places/market.html']],
            'films' => [['id' => 'f1','legacyId' => 1,'url' => '/films/first.html']],
            'news' => [['id' => 'n1','legacyId' => 1,'url' => '/news/first.html']],
            'offers' => [['id' => 'o1','legacyId' => 101,'url' => '/offers/first.html'],['id' => 'o2','legacyId' => 2,'url' => '/offers/second.html']],
            'aliases' => ['/places/old-market.html' => '/places/market.html','/places/market.html' => '/places/market.html'],
            'filmAliases' => [],
            'newsAliases' => ['/news/coffee-walk.html' => '/news/first.html'],
            'offerAliases' => ['/news/coffee-walk.html' => '/offers/second.html'],
        ];
    }

    public function testAliasAndLegacyDetailPageResolveToCurrentUrl(): void
    {
        $snapshot = $this->snapshot();
        self::assertSame('/places/market.html', LegacyUrlResolver::fromSnapshot($snapshot, LegacyUrlType::Attraction, '/places/old-market.html'));
        self::assertSame('/museum.html', LegacyUrlResolver::fromSnapshot($snapshot, LegacyUrlType::Attraction, '/place-detail.html?id=1'));
        self::assertSame('/films/first.html', LegacyUrlResolver::fromSnapshot($snapshot, LegacyUrlType::Film, '/film-detail.html?id=f1'));
        self::assertSame('/news/first.html', LegacyUrlResolver::fromSnapshot($snapshot, LegacyUrlType::News, '/news-detail.html?id=1'));
    }

    public function testPrototypeOfferIndexMapsToImportedLegacyId(): void
    {
        $snapshot = $this->snapshot();
        self::assertSame('/offers/first.html', LegacyUrlResolver::fromSnapshot($snapshot, LegacyUrlType::Offer, '/offer-detail.html?id=0'));
        // 2 會先換成 103；沒有這筆時不能退回比對原本的 legacyId 2。
        $this->assertNotFound($snapshot, LegacyUrlType::Offer, '/offer-detail.html?id=2', '找不到此優惠網址。');
    }

    public function testCoffeeWalkNewsUrlRedirectsToItsOffer(): void
    {
        self::assertSame('/offers/second.html', LegacyUrlResolver::fromSnapshot($this->snapshot(), LegacyUrlType::News, '/news/coffee-walk.html'));
    }

    public function testSelfAliasUnknownIdAndForeignTargetAreNotFound(): void
    {
        $snapshot = $this->snapshot();
        $this->assertNotFound($snapshot, LegacyUrlType::Attraction, '/places/market.html', '找不到此景點網址。');
        $this->assertNotFound($snapshot, LegacyUrlType::Film, '/film-detail.html?id=9', '找不到此電影網址。');
        $this->assertNotFound($snapshot, LegacyUrlType::Film, '/film-detail.html', '找不到此電影網址。');
        $snapshot['filmAliases'] = ['/films/old.html' => '/news/first.html'];
        $this->assertNotFound($snapshot, LegacyUrlType::Film, '/films/old.html', '找不到此電影網址。');
    }

    /** @param array<string,mixed> $snapshot */
    private function assertNotFound(array $snapshot, LegacyUrlType $type, string $original, string $message): void
    {
        try {
            LegacyUrlResolver::fromSnapshot($snapshot, $type, $original);
            self::fail('Expected NOT_FOUND for ' . $original);
        } catch (ApplicationError $error) {
            self::assertSame('NOT_FOUND', $error->errorCode);
            self::assertSame($message, $error->getMessage());
        }
    }
}
