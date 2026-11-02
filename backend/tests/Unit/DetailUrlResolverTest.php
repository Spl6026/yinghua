<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\PublicContentType;
use Yinghua\Services\DetailUrlResolver;
use Yinghua\Exceptions\ErrorCode;

class DetailUrlResolverTest extends TestCase
{
    /** @return array<string,mixed> */
    private function snapshot(): array
    {
        return [
            'items' => [['id' => 'a1','prototypeId' => 1,'url' => '/museum.html'],['id' => 'a2','prototypeId' => 2,'url' => '/places/market.html']],
            'films' => [['id' => 'f1','prototypeId' => 1,'url' => '/films/first.html']],
            'news' => [['id' => 'n1','prototypeId' => 1,'url' => '/news/first.html']],
            'offers' => [['id' => 'o1','prototypeId' => 101,'url' => '/offers/first.html'],['id' => 'o2','prototypeId' => 2,'url' => '/offers/second.html']],
            'aliases' => ['/places/old-market.html' => '/places/market.html','/places/market.html' => '/places/market.html'],
            'filmAliases' => [],
            'newsAliases' => ['/news/coffee-walk.html' => '/news/first.html'],
            'offerAliases' => ['/news/coffee-walk.html' => '/offers/second.html'],
        ];
    }

    public function testAliasAndIdDetailPageResolveToCurrentUrl(): void
    {
        $snapshot = $this->snapshot();
        self::assertSame('/places/market.html', DetailUrlResolver::fromSnapshot($snapshot, PublicContentType::Attraction, '/places/old-market.html'));
        self::assertSame('/museum.html', DetailUrlResolver::fromSnapshot($snapshot, PublicContentType::Attraction, '/place-detail.html?id=1'));
        self::assertSame('/films/first.html', DetailUrlResolver::fromSnapshot($snapshot, PublicContentType::Film, '/film-detail.html?id=f1'));
        self::assertSame('/news/first.html', DetailUrlResolver::fromSnapshot($snapshot, PublicContentType::News, '/news-detail.html?id=1'));
    }

    public function testPrototypeOfferIndexMapsToImportedPrototypeId(): void
    {
        $snapshot = $this->snapshot();
        self::assertSame('/offers/first.html', DetailUrlResolver::fromSnapshot($snapshot, PublicContentType::Offer, '/offer-detail.html?id=0'));
        // 2 會先換成 103；沒有這筆時不能退回比對原本的 prototypeId 2。
        $this->assertNotFound($snapshot, PublicContentType::Offer, '/offer-detail.html?id=2', '找不到此優惠網址。');
    }

    public function testCoffeeWalkNewsUrlRedirectsToItsOffer(): void
    {
        self::assertSame('/offers/second.html', DetailUrlResolver::fromSnapshot($this->snapshot(), PublicContentType::News, '/news/coffee-walk.html'));
    }

    public function testSelfAliasUnknownIdAndForeignTargetAreNotFound(): void
    {
        $snapshot = $this->snapshot();
        $this->assertNotFound($snapshot, PublicContentType::Attraction, '/places/market.html', '找不到此景點網址。');
        $this->assertNotFound($snapshot, PublicContentType::Film, '/film-detail.html?id=9', '找不到此電影網址。');
        $this->assertNotFound($snapshot, PublicContentType::Film, '/film-detail.html', '找不到此電影網址。');
        $snapshot['filmAliases'] = ['/films/old.html' => '/news/first.html'];
        $this->assertNotFound($snapshot, PublicContentType::Film, '/films/old.html', '找不到此電影網址。');
    }

    /** @param array<string,mixed> $snapshot */
    private function assertNotFound(array $snapshot, PublicContentType $type, string $original, string $message): void
    {
        try {
            DetailUrlResolver::fromSnapshot($snapshot, $type, $original);
            self::fail('Expected NOT_FOUND for ' . $original);
        } catch (ApplicationError $error) {
            self::assertSame(ErrorCode::NotFound, $error->errorCode);
            self::assertSame($message, $error->getMessage());
        }
    }
}
