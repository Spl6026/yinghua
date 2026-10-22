<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Adapters\HtmlPurifierSanitizer;
use Yinghua\DTO\NewsInput;
use Yinghua\DTO\OfferInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Policies\OfferValidity;

class PublicContentInputTest extends TestCase
{
    public function testExcelAllowsBlankDatesAndTextButRequiresOfferTerms(): void
    {
        $news = NewsInput::fromArray(['title' => '消息','type' => 'event'], true, 0);
        self::assertNull($news->fields['event_starts_at']);
        self::assertSame('', $news->fields['body']);
        $offer = OfferInput::fromArray(['title' => '優惠','merchantId' => '11111111-1111-4111-8111-111111111111','discount' => '九折','terms' => '請出示本頁'], true, 0);
        self::assertNull($offer->fields['valid_to']);
        $this->expectException(ApplicationError::class);
        OfferInput::fromArray(['title' => '優惠','merchantId' => '11111111-1111-4111-8111-111111111111','discount' => '九折','terms' => '<p> </p>'], true, 0);
    }

    public function testEndDateIncludesTheEntireTaipeiDayWithoutGuessingMissingBounds(): void
    {
        $end = '2026-10-22';
        self::assertSame('active', OfferValidity::state($end, $end, OfferValidity::day(strtotime('2026-10-22T23:59:59+08:00'))));
        self::assertSame('expired', OfferValidity::state($end, $end, OfferValidity::day(strtotime('2026-10-23T00:00:00+08:00'))));
        self::assertSame('upcoming', OfferValidity::state($end, null, '2026-10-21'));
        self::assertSame('active', OfferValidity::state(null, null, '2099-01-01'));
    }

    public function testUnsafeRichTextIsSanitizedWithoutRemovingNormalFormatting(): void
    {
        $html = (new HtmlPurifierSanitizer())->clean('<h2>介紹</h2><p onclick="alert(1)"><strong>內文</strong><script>alert(1)</script><a href="javascript:alert(1)">連結</a></p>');
        self::assertStringContainsString('<h2>介紹</h2>', $html);
        self::assertStringContainsString('<strong>內文</strong>', $html);
        self::assertStringNotContainsString('script', $html);
        self::assertStringNotContainsString('onclick', $html);
    }

    public function testNewsCannotUseOffersAsAClassification(): void
    {
        $this->expectException(ApplicationError::class);
        NewsInput::fromArray(['title' => '消息','type' => 'offers'], true, 0);
    }

    public function testInvalidCalendarAndReverseDatesAreNotNormalized(): void
    {
        $this->expectException(ApplicationError::class);
        NewsInput::fromArray(['title' => '消息','type' => 'event','eventStartAt' => '2026-02-30T10:00:00+08:00'], true, 0);
    }
}
