<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\AttractionInput;
use Yinghua\DTO\PhoneNumber;
use Yinghua\Exceptions\ApplicationError;

class PhoneNumberTest extends TestCase
{
    public function testOptionalAndCommonFormatsRetainDisplayAndProduceSafeDialLinks(): void
    {
        foreach (['' => '', '03-1234567' => 'tel:031234567', '+886 (3) 123-4567 ext. 25' => 'tel:+88631234567;ext=25',
            '(03)1234567分機123' => 'tel:031234567;ext=123', '0912 345 678' => 'tel:0912345678', '03.123.4567' => 'tel:031234567'] as $text => $uri) {
            $value = PhoneNumber::fromText($text);
            self::assertSame($text, $value->text);
            self::assertSame($uri, $value->uri);
        }
    }

    public function testInvalidPhoneCannotBecomeAContactLink(): void
    {
        foreach (['call me', 'https://example.test', 'javascript:1', '03-123<script>', ')(03)123(', '()123', '03/123', str_repeat('1', 81)] as $text) {
            try {
                PhoneNumber::fromText($text);
                self::fail('Invalid phone accepted.');
            } catch (ApplicationError $error) {
                self::assertArrayHasKey('phone', $error->fieldErrors);
            }
        }
    }

    public function testPhoneIsOptionalInAttractionCommandsButProvidedValuesAreValidated(): void
    {
        $base = ['slug' => 'phone-test', 'name' => '景點', 'category' => '景點', 'district' => '東區',
            'summary' => '摘要', 'description' => '介紹', 'address' => '地址', 'status' => 'draft'];
        self::assertSame('', AttractionInput::fromArray($base, true)->fields['phone']);
        self::assertSame('', AttractionInput::fromArray([...$base, 'phone' => '  '], true)->fields['phone']);
        self::assertSame('03-1234567', AttractionInput::fromArray([...$base, 'phone' => ' 03-1234567 '], true)->fields['phone']);
        $this->expectException(ApplicationError::class);
        AttractionInput::fromArray([...$base, 'phone' => 1234567], true);
    }
}
