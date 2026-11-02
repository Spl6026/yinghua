<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Config;
use Yinghua\Adapters\GooglePlaceMapper;
use Yinghua\Contracts\GoogleHttp;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Policies\GoogleUrlPolicy;

/** 從 Google 地圖網址查出候選地點資料；只回傳給前端參考，不寫入景點，也不下載 Google 照片。 */
class MapResolver
{
    public function __construct(
        private readonly GoogleHttp $http,
        private readonly GoogleUrlPolicy $policy,
        private readonly Config $config,
        private readonly Authorization $authorization,
        private readonly RateLimiter $limiter,
        private readonly GooglePlaceMapper $mapper,
    ) {}

    /** @return array<string,mixed> */
    public function resolve(Account $actor, string $url, string $action): array
    {
        $this->authorization->require($actor, 'attractions', $action);
        $this->policy->validate($url);
        $this->limiter->check('map-resolve', $actor->id, 10, 60);
        $key = $this->config->string('GOOGLE_PLACES_API_KEY');
        if ($key === '') {
            throw new ApplicationError('MAP_UNAVAILABLE', '尚未設定 Google Places 金鑰，請人工輸入資料。');
        }
        $seen = [];
        for ($step = 0;$step < 4;$step++) {
            $host = $this->policy->validate($url);
            if (!in_array($host, ['maps.app.goo.gl','goo.gl'], true)) {
                break;
            }
            if (isset($seen[$url]) || $step === 3) {
                throw ApplicationError::invalid('mapUrl', '短網址重導向過多或形成循環。');
            }
            $seen[$url] = true;
            $response = $this->http->request($url);
            $next = $response->headers['location'] ?? '';
            if (!in_array($response->status, [301,302,303,307,308], true) || $next === '') {
                throw ApplicationError::invalid('mapUrl', '短網址無法展開，請貼上完整 Google Maps 網址。');
            }
            $url = str_starts_with($next, '/') && !str_starts_with($next, '//') ? 'https://' . $host . $next : $next;
        }
        $queryString = parse_url($url, PHP_URL_QUERY);
        parse_str(is_string($queryString) ? $queryString : '', $query);
        $placeId = $query['query_place_id'] ?? null;
        $q = $query['q'] ?? $query['query'] ?? null;
        if (is_string($q) && str_starts_with($q, 'place_id:')) {
            $placeId = substr($q, 9);
        }
        if (!is_string($placeId) && preg_match('/!1s(ChI[A-Za-z0-9_-]+)/', rawurldecode($url), $match)) {
            $placeId = $match[1];
        }
        $fields = 'id,displayName,formattedAddress,addressComponents,location,regularOpeningHours.periods,regularOpeningHours.weekdayDescriptions,websiteUri,googleMapsUri,editorialSummary,attributions';
        $headers = ['X-Goog-Api-Key' => $key,'X-Goog-FieldMask' => $fields,'Accept' => 'application/json'];
        if (is_string($placeId) && preg_match('/^[A-Za-z0-9_-]{5,255}$/D', $placeId)) {
            $response = $this->http->request('https://places.googleapis.com/v1/places/' . rawurlencode($placeId)
                . '?languageCode=zh-TW', $headers, api: true);
            $value = $this->decode($response->status, $response->body);
            $candidates = [$value];
        } else {
            $path = parse_url($url, PHP_URL_PATH);
            if ((!is_string($q) || $q === '') && is_string($path) && preg_match('~/maps/place/([^/]+)~', $path, $match)) {
                $q = urldecode($match[1]);
            }
            if (!is_string($q) || mb_strlen($q) < 2 || mb_strlen($q) > 200) {
                throw ApplicationError::invalid('mapUrl', '無法從網址取得地點，請提供包含地點名稱的完整網址。');
            }
            $headers['X-Goog-FieldMask'] = 'places.' . str_replace(',', ',places.', $fields);
            $headers['Content-Type'] = 'application/json';
            $response = $this->http->request(
                'https://places.googleapis.com/v1/places:searchText',
                $headers,
                json_encode(['textQuery' => $q,'languageCode' => 'zh-TW','regionCode' => 'TW','pageSize' => 5], JSON_THROW_ON_ERROR),
                true,
            );
            $decoded = $this->decode($response->status, $response->body);
            $candidates = $decoded['places'] ?? [];
        }
        if (!is_array($candidates) || $candidates === []) {
            throw ApplicationError::invalid('mapUrl', 'Google Places 查無地點，請人工輸入。');
        }
        $result = [];
        foreach ($candidates as $candidate) {
            $mapped = $this->mapper->convert($candidate, $url);
            if ($mapped !== null) {
                $result[] = $mapped->toArray();
            }
        }
        if ($result === []) {
            throw ApplicationError::invalid('mapUrl', 'Google 回傳的地點資料不完整，請人工輸入或重新查詢。');
        }

        return ['candidates' => $result,
            'attribution' => 'Google Maps',
            'notice' => '候選資料不會自動儲存。正式採用前須依 Google Maps Platform 條款確認可保存範圍，圖片請使用自行授權素材。'];
    }

    /** @return array<string,mixed> */
    private function decode(int $status, string $body): array
    {
        if ($status !== 200) {
            throw new ApplicationError('MAP_UNAVAILABLE', 'Google Places 暫時不可用，請檢查金鑰、配額與 API 啟用狀態或人工輸入。');
        }
        try {
            $value = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApplicationError('MAP_UNAVAILABLE', '地圖服務回應無效，可改為人工輸入。');
        }
        if (!is_array($value)) {
            throw new ApplicationError('MAP_UNAVAILABLE', '地圖服務回應無效。');
        }
        $result = [];
        foreach ($value as $key => $entry) {
            if (is_string($key)) {
                $result[$key] = $entry;
            }
        }

        return $result;
    }
}
