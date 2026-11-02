<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\ContentStatus;
use Yinghua\Policies\GoogleUrlPolicy;
use Yinghua\Support\Uuid;
use Yinghua\Support\Json;

/** 景點表單輸入；後台表單與匯入共用同一組驗證。 */
readonly class AttractionInput
{
    /** @param array<string,bool|int|string|null> $fields */
    private function __construct(
        public array $fields,
        public ?int $version,
        public ContentStatus $status,
        public bool $googleLinkConfirmed,
    ) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values, bool $creating): self
    {
        $input = new Input($values);
        $input->only(['slug','name','category','district','typeLabel','summary','description','hours','address',
            'phone','latitude','longitude','transport','websiteUrl','mapUrl','googlePlaceId','mediaId','featured','isMuseum',
            'status','publishAt','seoTitle','seoDescription','version',
            'imageAlt','imageSource','imageLicense','recommendationLabel','openingHours','googleLinkConfirmed']);
        $slug = $input->optionalText('slug', 120);
        if ($slug !== '' && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) {
            throw ApplicationError::invalid('slug', '網址識別只可使用小寫英數字與連字號。');
        }
        $category = $input->text('category');
        $district = $input->optionalText('district', 40);
        if (!in_array($category, ['景點','美食','在地店家'], true)) {
            throw ApplicationError::invalid('category', '請選擇有效分類。');
        }
        if (!in_array($district, ['','北區','東區','香山區'], true)) {
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
        if ($mediaId !== '' && !Uuid::isValid($mediaId)) {
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
            // 官網允許 HTTP 外連；地圖網址會被伺服器拿去發外部請求，所以只接受 HTTPS。
            $schemes = $key === 'websiteUrl' ? ['http','https'] : ['https'];
            if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), $schemes, true)
                || parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null)) {
                throw ApplicationError::invalid($key, $key === 'websiteUrl' ? '請輸入不含登入資訊的 HTTP 或 HTTPS 官網網址。' : '請輸入不含登入資訊的 HTTPS 地圖網址。');
            }
            $urls[$key] = $url;
        }
        $placeId = $optional('googlePlaceId', 255);
        if ($urls['mapUrl'] !== '') {
            (new GoogleUrlPolicy())->validate($urls['mapUrl']);
        }
        if ($placeId !== '' && !preg_match('/^[A-Za-z0-9_-]+$/D', $placeId)) {
            throw ApplicationError::invalid('googlePlaceId', '地圖地點識別無效。');
        }
        // 摘要與介紹都是選填，留空存成空字串，但仍檢查型別與字數上限。
        $fields = [
            'slug' => $slug, 'name' => $input->text('name', 1, 120), 'category' => $category, 'district' => $district,
            'type_label' => $optional('typeLabel', 80), 'summary' => $optional('summary', 500),
            'description' => $optional('description', 20000), 'hours' => $optional('hours', 500),
            'address' => $input->optionalText('address'), 'phone' => PhoneNumber::fromText($input->optionalText('phone', 80))->text,
            'latitude' => $coordinates['latitude'],'longitude' => $coordinates['longitude'],
            'transport' => $optional('transport', 2000), 'website_url' => $urls['websiteUrl'],'map_url' => $urls['mapUrl'],
            'google_place_id' => $placeId === '' ? null : $placeId, 'media_id' => $mediaId === '' ? null : $mediaId,
            'featured' => array_key_exists('featured', $values) ? $input->boolean('featured') : false,
            'is_museum' => array_key_exists('isMuseum', $values) ? $input->boolean('isMuseum') : false,
            'status' => $status->value,'publish_at' => $publishAt->format('Y-m-d H:i:s'),
            'seo_title' => $optional('seoTitle', 160),'seo_description' => $optional('seoDescription', 500),
        ];

        // 營業時間以結構化週表為準，hours 欄位只是由週表產生的文字摘要。
        if (array_key_exists('openingHours', $values)) {
            $opening = OpeningHours::fromValue($values['openingHours']);
            $fields['opening_hours'] = Json::encode($opening->toArray());
            $fields['hours'] = $opening->summary();
        }

        // 沒送的欄位保留原值；明確送空字串或 null 表示改回圖片素材本身的說明。
        // 這些說明只屬於這個景點，不會改動共用 media_assets 的來源紀錄。
        foreach (['imageAlt' => 'image_alt','imageSource' => 'image_source','imageLicense' => 'image_license'] as $key => $column) {
            if (array_key_exists($key, $values)) {
                $text = $values[$key] === null ? '' : $input->text($key, 0, 255);
                $fields[$column] = $text === '' ? null : $text;
            }
        }
        if (array_key_exists('recommendationLabel', $values)) {
            $fields['recommendation_label'] = $input->text('recommendationLabel', 0, 80);
        }

        return new self(
            $fields,
            $creating ? null : $input->positiveInt('version'),
            $status,
            array_key_exists('googleLinkConfirmed', $values) ? $input->boolean('googleLinkConfirmed') : false,
        );
    }

    /** 新增時 slug 留空由系統產生；編輯時留空則沿用原本的網址。 */
    public function withDefaultSlug(string $slug): self
    {
        return $this->fields['slug'] !== '' ? $this : new self(
            [...$this->fields, 'slug' => $slug],
            $this->version,
            $this->status,
            $this->googleLinkConfirmed,
        );
    }
}
