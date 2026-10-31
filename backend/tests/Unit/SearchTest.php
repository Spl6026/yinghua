<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\{SearchDocument, SearchQuery};
use Yinghua\Exceptions\ApplicationError;

class SearchTest extends TestCase
{
    public function testProjectionKeepsVisibleTextAndExcludesPrivateFields(): void
    {
        $base = ['id' => '10000000-0000-4000-8000-000000000001','slug' => 'coffee','name' => '<img src=x>咖啡','category' => '美食','description' => '<p>一杯&nbsp;咖啡</p>','googlePlaceId' => 'private','password' => 'secret'];
        $documents = SearchDocument::project(['items' => [$base],'films' => [],'news' => [],'offers' => []]);
        self::assertCount(1, $documents);
        self::assertSame('food', $documents[0]->type->value);
        self::assertSame('/places/coffee.html', $documents[0]->url);
        self::assertSame('<img src=x>咖啡', $documents[0]->title);
        self::assertStringContainsString('咖啡', $documents[0]->summary);
        self::assertStringNotContainsString('secret', json_encode($documents, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('private', $documents[0]->text);
    }

    public function testInactiveOfferIsNotIndexedAndMuseumRetainsItsRealUrl(): void
    {
        $item = ['id' => '10000000-0000-4000-8000-000000000002','slug' => 'museum','name' => '館舍','isMuseum' => true];
        $documents = SearchDocument::project(['items' => [$item],'films' => [],'news' => [],'offers' => [['active' => false]]]);
        self::assertCount(1, $documents);
        self::assertSame('/museum.html', $documents[0]->url);
    }

    public function testSingleChineseCharacterAndLiteralWildcardAreAllowed(): void
    {
        self::assertSame('竹', (new SearchQuery('竹'))->q);
        self::assertSame('%_', (new SearchQuery('%_'))->q);
        self::assertSame(255, mb_strlen((new SearchQuery(str_repeat('竹', 255)))->q));
    }

    public function testUnknownTypeIsRejected(): void
    {
        $this->expectException(ApplicationError::class);
        new SearchQuery('秘密', 'member');
    }

    public function testOverlongUnicodeQueryIsRejected(): void
    {
        $this->expectException(ApplicationError::class);
        new SearchQuery(str_repeat('竹', 256));
    }
}
