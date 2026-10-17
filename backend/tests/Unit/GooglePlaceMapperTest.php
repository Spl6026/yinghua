<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Adapters\GoogleOpeningHours;
use Yinghua\Adapters\GooglePlaceMapper;
use Yinghua\DTO\AttractionInput;
use Yinghua\Exceptions\ApplicationError;

class GooglePlaceMapperTest extends TestCase
{
    /** @return array<string,mixed> */
    private function place(): array
    {
        return ['id' => 'ChIJ_example','displayName' => ['text' => '測試地點'],'formattedAddress' => '新竹市東區測試路',
            'addressComponents' => [['longText' => '東區','types' => ['administrative_area_level_3']],['longText' => '新竹市','types' => ['administrative_area_level_1']]],
            'location' => ['latitude' => 24.8,'longitude' => 120.97],'websiteUri' => 'http://example.test','googleMapsUri' => 'https://maps.google.com/maps?q=test'];
    }

    public function testDistrictRequiresHsinchuCityAndKnownDistrict(): void
    {
        $mapper = new GooglePlaceMapper(new GoogleOpeningHours());
        self::assertSame('東區', $mapper->convert($this->place(), 'https://maps.google.com/maps')->district);
        foreach (['台中市','新竹縣',''] as $city) {
            $place = $this->place();
            $place['addressComponents'][1]['longText'] = $city;
            self::assertSame('', $mapper->convert($place, 'https://maps.google.com/maps')->district);
        }
        $place = $this->place();
        $place['addressComponents'][0]['longText'] = '香山區';
        self::assertSame('香山區', $mapper->convert($place, 'https://maps.google.com/maps')->district);
        $place['addressComponents'][0]['longText'] = '西區';
        self::assertSame('', $mapper->convert($place, 'https://maps.google.com/maps')->district);
    }

    public function testExternalMalformedFieldsNeverPassThroughAsArraysOrPartialCoordinates(): void
    {
        $mapper = new GooglePlaceMapper(new GoogleOpeningHours());
        $place = $this->place();
        $place['location']['longitude'] = ['bad'];
        $place['addressComponents'][0]['types'] = [['bad']];
        $place['websiteUri'] = 'javascript:alert(1)';
        $item = $mapper->convert($place, 'https://maps.google.com/maps');
        self::assertNull($item->latitude);
        self::assertNull($item->longitude);
        self::assertSame('', $item->district);
        self::assertSame('', $item->websiteUrl);
        $place['displayName']['text'] = ['bad'];
        self::assertNull($mapper->convert($place, 'https://maps.google.com/maps'));
    }

    public function testCandidatePreservesHttpWebsiteButRejectsCredentialsAndUnsafeMapLink(): void
    {
        $mapper = new GooglePlaceMapper(new GoogleOpeningHours());
        self::assertSame('http://example.test', $mapper->convert($this->place(), 'https://maps.google.com/maps')->websiteUrl);
        foreach (['ftp://example.test','https://user:secret@example.test','javascript:alert(1)'] as $url) {
            $place = $this->place();
            $place['websiteUri'] = $url;
            $place['googleMapsUri'] = 'http://example.test';
            $item = $mapper->convert($place, 'https://maps.google.com/maps');
            self::assertSame('', $item->websiteUrl);
            self::assertSame('https://maps.google.com/maps', $item->mapUrl);
        }
    }

    public function testWebsiteHttpIsAcceptedWhileMapAndOtherSchemesRemainRestricted(): void
    {
        $valid = ['slug' => 'place','name' => '景點','category' => '景點','district' => '東區','summary' => '摘要','description' => '正文','address' => '地址','status' => 'draft','websiteUrl' => 'http://example.test'];
        self::assertSame('http://example.test', AttractionInput::fromArray($valid, true)->fields['website_url']);
        foreach ([['mapUrl','http://example.test'],['websiteUrl','javascript:alert(1)'],['websiteUrl','https://user:password@example.test']] as [$key,$url]) {
            try {
                AttractionInput::fromArray([...$valid,$key => $url], true);
                self::fail('Unsafe URL accepted.');
            } catch (ApplicationError $error) {
                self::assertArrayHasKey($key, $error->fieldErrors);
            }
        }
    }

    public function testEditorialReferenceStaysUnmodifiedAndProviderLinksAreSanitized(): void
    {
        $place = $this->place();
        $place['editorialSummary'] = ['text' => " 原文 <安全>\n保留換行 "];
        $place['attributions'] = [['provider' => '資料提供者','providerUri' => 'https://example.test/source'],['provider' => '安全文字','providerUri' => 'javascript:alert(1)']];
        $item = (new GooglePlaceMapper(new GoogleOpeningHours()))->convert($place, 'https://maps.google.com/maps')->toArray();
        self::assertSame($place['editorialSummary']['text'], $item['editorialSummary']);
        self::assertSame('', $item['attributions'][1]['providerUri']);
        self::assertArrayNotHasKey('description', $item);
        self::assertArrayNotHasKey('summary', $item);
    }
}
