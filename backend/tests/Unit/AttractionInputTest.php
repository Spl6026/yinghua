<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\AttractionInput;
use Yinghua\Exceptions\ApplicationError;

class AttractionInputTest extends TestCase
{
    /** @return array<string,mixed> */
    private function valid(): array
    {
        return ['slug' => 'sample','name' => '景點','category' => '景點','district' => '北區','summary' => '摘要','description' => '正文','address' => '地址','status' => 'draft'];
    }

    public function testOmittedMetadataDoesNotClearAnExistingOverride(): void
    {
        $input = AttractionInput::fromArray($this->valid(), true);
        foreach (['image_alt','image_source','image_license','recommendation_label'] as $column) {
            self::assertArrayNotHasKey($column, $input->fields);
        }
    }

    public function testMetadataIsNormalizedAndBlankRestoresAssetDefaults(): void
    {
        $input = AttractionInput::fromArray([...$this->valid(),'imageAlt' => ' 圖片說明 ','imageSource' => '','imageLicense' => null,'recommendationLabel' => '城市記憶'], true);
        self::assertSame('圖片說明', $input->fields['image_alt']);
        self::assertNull($input->fields['image_source']);
        self::assertNull($input->fields['image_license']);
        self::assertSame('城市記憶', $input->fields['recommendation_label']);
    }

    public function testNonStringMetadataIsRejected(): void
    {
        $this->expectException(ApplicationError::class);
        AttractionInput::fromArray([...$this->valid(),'imageAlt' => ['not text']], true);
    }

    public function testLongRecommendationLabelIsRejected(): void
    {
        $this->expectException(ApplicationError::class);
        AttractionInput::fromArray([...$this->valid(),'recommendationLabel' => str_repeat('字', 81)], true);
    }
}
