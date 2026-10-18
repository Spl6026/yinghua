<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\Attraction;
use Yinghua\DTO\AttractionInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Policies\GooglePlaceLinkPolicy;

class GooglePlaceLinkPolicyTest extends TestCase
{
    /** @return array<string,mixed> */
    private function values(): array
    {
        return ['slug' => 'place','name' => '景點','category' => '景點','district' => '東區','summary' => '摘要','description' => '正文','address' => '地址','status' => 'draft','latitude' => 24.8,'longitude' => 120.97,'googlePlaceId' => 'ChIJ_original','version' => 1];
    }

    private function before(): Attraction
    {
        return new Attraction('a', 'place', 1, 'draft', null, $this->values());
    }

    public function testUnchangedLinkAndExplicitDetachDoNotRequireFreshConfirmation(): void
    {
        $policy = new GooglePlaceLinkPolicy();
        $policy->assertConfirmed($this->before(), AttractionInput::fromArray($this->values(), false));
        $policy->assertConfirmed($this->before(), AttractionInput::fromArray([...$this->values(),'name' => '另一景點','googlePlaceId' => ''], false));
        self::assertTrue(true);
    }

    public function testNewChangedAndReplacedLinkNeedConfirmation(): void
    {
        $policy = new GooglePlaceLinkPolicy();
        $cases = [[null,$this->values()],[$this->before(),[...$this->values(),'name' => '改名']],[$this->before(),[...$this->values(),'address' => '改址']],[$this->before(),[...$this->values(),'latitude' => 24.7]],[$this->before(),[...$this->values(),'googlePlaceId' => 'ChIJ_replaced']]];
        foreach ($cases as [$before,$values]) {
            try {
                $policy->assertConfirmed($before, AttractionInput::fromArray($values, $before === null));
                self::fail('Unconfirmed link accepted.');
            } catch (ApplicationError $error) {
                self::assertArrayHasKey('googlePlaceId', $error->fieldErrors);
            }
            $policy->assertConfirmed($before, AttractionInput::fromArray([...$values,'googleLinkConfirmed' => true], $before === null));
        }
    }

    public function testConfirmationIsStrictBooleanAndNeverPartOfSqlFields(): void
    {
        $input = AttractionInput::fromArray([...$this->values(),'googleLinkConfirmed' => true], false);
        self::assertTrue($input->googleLinkConfirmed);
        self::assertArrayNotHasKey('googleLinkConfirmed', $input->fields);
        $this->expectException(ApplicationError::class);
        AttractionInput::fromArray([...$this->values(),'googleLinkConfirmed' => 'true'], false);
    }
}
