<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\ContentStatus;

/** [S：功能規格 F13/G20；E-18] 表單與受控匯入共用同一組驗證。 */
readonly class AttractionInput
{
    /** @param array<string,bool|int|string|null> $fields */
    private function __construct(public array $fields, public ?int $version, public ContentStatus $status) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values, bool $creating): self
    {
        $input = new Input($values);
        $input->only(['slug','name','category','district','typeLabel','summary','description','hours','address',
            'phone','latitude','longitude','transport','websiteUrl','mapUrl','googlePlaceId','mediaId','featured','isMuseum',
            'status','publishAt','seoTitle','seoDescription','version','imageAlt','imageSource','imageLicense','recommendationLabel']);
        $slug = $input->text('slug', 1, 120);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw ApplicationError::invalid('slug', '網址識別只可使用小寫英數字與連字號。');
        }
        $category = $input->text('category');
        $district = $input->text('district');
        if (!in_array($category, ['景點','美食','在地店家'], true)) {
            throw ApplicationError::invalid('category', '請選擇有效分類。');
        }
        if (!in_array($district, ['北區','東區','香山區'], true)) {
            throw ApplicationError::invalid('district', '請選擇新竹市行政區。');
        }
        $status = ContentStatus::tryFrom($input->text('status'));
        if ($status === null) {
            throw ApplicationError::invalid('status', '請選擇有效發布狀態。');
        }
        $optional = static function (string $key, int $max) use ($input, $values): string {
            return array_key_exists($key, $values) ? $input->text($key, 0, $max) : '';
        };
        $mediaId = $optional('mediaId', 36);
        if ($mediaId !== '' && !preg_match('/^[a-f0-9-]{36}$/D', $mediaId)) {
            throw ApplicationError::invalid('mediaId', '請選擇有效圖片。');
        }
        $coordinates = [];
        foreach (['latitude' => 90, 'longitude' => 180] as $key => $limit) {
            $value = $values[$key] ?? null;
            if ($value === '' || $value === null) {
                $coordinates[$key] = null;
            } elseif ((is_int($value) || is_float($value)) && is_finite((float) $value) && abs($value) <= $limit) {
                $coordinates[$key] = (string) round($value, 7);
            } else {
                throw ApplicationError::invalid($key, '座標超出有效範圍。');
            }
        }
        if (($coordinates['latitude'] === null) !== ($coordinates['longitude'] === null)) {
            throw ApplicationError::invalid('latitude', '經緯度必須一同填寫或留空。');
        }
        $publishText = $optional('publishAt', 30);
        $publishAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        if ($publishText !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $publishText);
            if ($date === false || $date->format('Y-m-d\TH:i:sP') !== $publishText) {
                throw ApplicationError::invalid('publishAt', '請提供包含時區的有效日期時間。');
            }
            $publishAt = $date->setTimezone(new DateTimeZone('UTC'));
        }
        $urls = [];
        foreach (['websiteUrl','mapUrl'] as $key) {
            $url = $optional($key, 2000);
            if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https'
                || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null)) {
                throw ApplicationError::invalid($key, '請輸入不含登入資訊的 HTTPS 網址。');
            }
            $urls[$key] = $url;
        }
        $placeId = $optional('googlePlaceId', 255);
        if ($placeId !== '' && !preg_match('/^[A-Za-z0-9_-]+$/D', $placeId)) {
            throw ApplicationError::invalid('googlePlaceId', '地圖地點識別無效。');
        }
        $fields = [
            'slug' => $slug, 'name' => $input->text('name', 1, 120), 'category' => $category, 'district' => $district,
            'type_label' => $optional('typeLabel', 80), 'summary' => $input->text('summary', 1, 500),
            'description' => $input->text('description', 1, 20000), 'hours' => $optional('hours', 500),
            'address' => $input->text('address', 1, 255), 'phone' => PhoneNumber::fromText($input->optionalText('phone', 80))->text, 'latitude' => $coordinates['latitude'],'longitude' => $coordinates['longitude'],
            'transport' => $optional('transport', 2000), 'website_url' => $urls['websiteUrl'],'map_url' => $urls['mapUrl'],
            'google_place_id' => $placeId === '' ? null : $placeId, 'media_id' => $mediaId === '' ? null : $mediaId,
            'featured' => array_key_exists('featured', $values) ? $input->boolean('featured') : false,
            'is_museum' => array_key_exists('isMuseum', $values) ? $input->boolean('isMuseum') : false,
            'status' => $status->value,'publish_at' => $publishAt->format('Y-m-d H:i:s'),
            'seo_title' => $optional('seoTitle', 160),'seo_description' => $optional('seoDescription', 500),
        ];

        // [E-18／R3-01] 未提供欄位保留既有值；明確空白／null 表示回到資產原始說明。
        // 說明屬於此景點的使用情境，不修改共用 media_assets 的來源紀錄。
        foreach (['imageAlt' => 'image_alt','imageSource' => 'image_source','imageLicense' => 'image_license'] as $key => $column) {
            if (array_key_exists($key, $values)) {
                $text = $values[$key] === null ? '' : $input->text($key, 0, 255);
                $fields[$column] = $text === '' ? null : $text;
            }
        }
        if (array_key_exists('recommendationLabel', $values)) {
            $fields['recommendation_label'] = $input->text('recommendationLabel', 0, 80);
        }

        return new self($fields, $creating ? null : $input->positiveInt('version'), $status);
    }
}
