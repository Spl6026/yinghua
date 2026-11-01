<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Yinghua\Config;
use Yinghua\Contracts\GoogleHttp;
use Yinghua\Contracts\PlaceSummaryProvider;
use Yinghua\DTO\GoogleSummary;

/** [U-23／E-40] 只取editorialSummary原文，不抓評論／AI摘要／照片，不保存外部內容。 */
class GoogleSummaryProvider implements PlaceSummaryProvider
{
    public function __construct(private readonly GoogleHttp $http, private readonly Config $config) {}

    public function fetch(string $placeId): GoogleSummary
    {
        $key = $this->config->string('GOOGLE_PLACES_API_KEY');
        if ($key === '' || !preg_match('/^[A-Za-z0-9_-]{5,255}$/D', $placeId)) {
            return new GoogleSummary();
        }
        try {
            $response = $this->http->request(
                'https://places.googleapis.com/v1/places/' . rawurlencode($placeId) . '?languageCode=zh-TW',
                ['X-Goog-Api-Key' => $key, 'X-Goog-FieldMask' => 'editorialSummary,attributions', 'Accept' => 'application/json'],
                api: true,
            );
            if ($response->status !== 200) {
                return new GoogleSummary();
            }
            $data = json_decode($response->body, true, 16, JSON_THROW_ON_ERROR);
            $summary = is_array($data) ? ($data['editorialSummary'] ?? null) : null;
            $text = is_array($summary) ? ($summary['text'] ?? null) : null;
            if (!is_string($text) || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > 20000 || str_contains($text, "\0") || preg_match('/[^\s\p{Z}]/u', $text) !== 1) {
                return new GoogleSummary();
            }
            $providers = [];
            $values = is_array($data) ? ($data['attributions'] ?? []) : [];
            foreach (is_array($values) ? $values : [] as $value) {
                if (!is_array($value) || !is_string($value['provider'] ?? null) || trim($value['provider']) === '' || mb_strlen($value['provider']) > 255) {
                    continue;
                }
                $url = $value['providerUri'] ?? '';
                $valid = is_string($url) && strlen($url) <= 2000 && filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
                    && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null;
                $providers[] = ['provider' => $value['provider'], 'providerUri' => $valid ? $url : ''];
            }
            return new GoogleSummary($text, 'https://www.google.com/maps/search/?api=1&query=Google%20Maps&query_place_id=' . rawurlencode($placeId), $providers);
        } catch (\Throwable) {
            return new GoogleSummary();
        }
    }
}
