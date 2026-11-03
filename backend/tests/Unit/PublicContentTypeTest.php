<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Models\PublicContentType;
use Yinghua\Models\SearchType;

final class PublicContentTypeTest extends TestCase
{
    public function testCanonicalUrlsFollowEachTypeDirectoryAndKeepTheMuseumPage(): void
    {
        self::assertSame('/places/tea.html', PublicContentType::Attraction->canonicalUrl(['slug' => 'tea']));
        self::assertSame('/museum.html', PublicContentType::Attraction->canonicalUrl(['slug' => 'tea', 'isMuseum' => true]));
        self::assertSame('/films/tea.html', PublicContentType::Film->canonicalUrl(['slug' => 'tea', 'isMuseum' => true]));
        self::assertSame('/news/tea.html', PublicContentType::News->canonicalUrl(['slug' => 'tea']));
        self::assertSame('/offers/tea.html', PublicContentType::Offer->canonicalUrl(['slug' => 'tea']));
    }

    public function testPublishedUrlUsesStoredUrlExceptForPlaces(): void
    {
        self::assertSame('/places/tea.html', PublicContentType::Attraction->publishedUrl(['slug' => 'tea', 'url' => '/other']));
        self::assertSame('/news/kept.html', PublicContentType::News->publishedUrl(['slug' => 'tea', 'url' => '/news/kept.html']));
        self::assertNull(PublicContentType::Offer->publishedUrl(['slug' => 'tea']));
    }

    public function testTitleGroupingAndSearchTypeDifferPerType(): void
    {
        self::assertSame('name', PublicContentType::Attraction->titleKey());
        self::assertSame('title', PublicContentType::Film->titleKey());
        self::assertSame('category', PublicContentType::Attraction->neighborGroupKey());
        self::assertSame('type', PublicContentType::News->neighborGroupKey());
        self::assertNull(PublicContentType::Offer->neighborGroupKey());
        self::assertSame(SearchType::Food, PublicContentType::Attraction->searchType(['category' => '美食']));
        self::assertSame(SearchType::Place, PublicContentType::Attraction->searchType(['category' => '景點']));
        self::assertSame(SearchType::Offer, PublicContentType::Offer->searchType([]));
    }
}
