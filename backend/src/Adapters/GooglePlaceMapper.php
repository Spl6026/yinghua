<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Yinghua\DTO\MapCandidate;

/**
 * 把 Google 地點欄位轉成本站的候選資料。
 * 行政區必須確認在新竹市；官網網址照原樣保留，不改協定也不去抓取。
 */
class GooglePlaceMapper
{
    public function __construct(private readonly GoogleOpeningHours $openingHours) {}

    public function convert(mixed $value, string $fallbackUrl): ?MapCandidate
    {
        if (!is_array($value)) {
            return null;
        }
        $id = $this->text($value['id'] ?? null, 255);
        $display = $value['displayName'] ?? null;
        $name = $this->text(is_array($display) ? ($display['text'] ?? null) : null, 120);
        if (!preg_match('/^[A-Za-z0-9_-]{5,255}$/D', $id) || $name === '') {
            return null;
        }
        $location = $value['location'] ?? null;
        $latitude = $this->coordinate(is_array($location) ? ($location['latitude'] ?? null) : null, 90);
        $longitude = $this->coordinate(is_array($location) ? ($location['longitude'] ?? null) : null, 180);
        if ($latitude === null || $longitude === null) {
            $latitude = $longitude = null;
        }
        $summary = $value['editorialSummary'] ?? null;
        $summaryText = is_array($summary) ? ($summary['text'] ?? null) : null;
        // 原文只回傳給管理員參考，不截斷、不改寫，也不存進網站介紹。
        $editorial = is_string($summaryText) && mb_check_encoding($summaryText, 'UTF-8')
            && mb_strlen($summaryText) <= 20000 && !str_contains($summaryText, "\0") ? $summaryText : '';
        $attributions = [];
        $providers = $value['attributions'] ?? [];
        if (is_array($providers)) {
            foreach ($providers as $provider) {
                if (is_array($provider) && ($providerName = $this->text($provider['provider'] ?? null, 255)) !== '') {
                    $attributions[] = ['provider' => $providerName,'providerUri' => $this->url($provider['providerUri'] ?? null, true)];
                }
            }
        }
        return new MapCandidate(
            $id,
            $name,
            $this->text($value['formattedAddress'] ?? null, 255),
            $this->district($value['addressComponents'] ?? null),
            $latitude,
            $longitude,
            $this->openingHours->convert($value['regularOpeningHours'] ?? null),
            $this->url($value['websiteUri'] ?? null, true),
            $this->url($value['googleMapsUri'] ?? null, false) ?: $fallbackUrl,
            $editorial,
            $attributions,
        );
    }

    private function district(mixed $components): string
    {
        if (!is_array($components) || !array_is_list($components)) {
            return '';
        }
        $city = false;
        $districts = [];
        foreach ($components as $component) {
            if (!is_array($component) || !is_array($component['types'] ?? null)) {
                continue;
            }
            $types = array_filter($component['types'], is_string(...));
            $text = $this->text($component['longText'] ?? null, 255);
            if (array_intersect($types, ['administrative_area_level_1','administrative_area_level_2','locality']) !== []
                && in_array($text, ['新竹市','Hsinchu City'], true)) {
                $city = true;
            }
            if (array_intersect(
                $types,
                ['administrative_area_level_2','administrative_area_level_3','sublocality_level_1','sublocality'],
            ) !== []) {
                $district = [
                    '東區' => '東區',
                    '北區' => '北區',
                    '香山區' => '香山區',
                    'East District' => '東區',
                    'North District' => '北區',
                    'Xiangshan District' => '香山區',
                ][$text] ?? '';
                if ($district !== '') {
                    $districts[$district] = true;
                }
            }
        }
        return $city && count($districts) === 1 ? (string) array_key_first($districts) : '';
    }

    private function text(mixed $value, int $maximum): string
    {
        return is_string($value) && mb_check_encoding($value, 'UTF-8') && mb_strlen($value) <= $maximum
            && !str_contains($value, "\0") ? trim($value) : '';
    }

    private function coordinate(mixed $value, int $limit): ?float
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value) && abs($value) <= $limit ? (float) $value : null;
    }

    private function url(mixed $value, bool $allowHttp): string
    {
        $url = $this->text($value, 2000);
        return $url !== '' && filter_var($url, FILTER_VALIDATE_URL)
            && in_array(parse_url($url, PHP_URL_SCHEME), $allowHttp ? ['http','https'] : ['https'], true)
            && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null ? $url : '';
    }
}
