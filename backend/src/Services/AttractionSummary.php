<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\PlaceSummaryProvider;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\GoogleSummary;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\ExternalRequestSlot;
use Yinghua\Exceptions\ErrorCode;

class AttractionSummary
{
    public function __construct(
        private readonly PublicAttractions $public,
        private readonly AttractionRepository $records,
        private readonly PlaceSummaryProvider $provider,
        private readonly RateLimiter $limiter,
        private readonly ExternalRequestSlot $slot,
    ) {}

    public function get(string $slug, ClientContext $client): GoogleSummary
    {
        $item = $this->public->get($slug)['attraction'];
        if (!is_array($item)) {
            return new GoogleSummary();
        }
        $id = $item['id'] ?? null;
        $record = is_string($id) ? $this->records->find($id) : null;
        $source = $item['contentSource'] ?? null;
        if ($record === null || $record->deletedAt !== null || $record->status !== 'published'
            || $record->version !== ($item['version'] ?? null)
            || !is_array($source) || ($source['description'] ?? '') !== 'google-pending') {
            return new GoogleSummary();
        }
        $placeId = $record->values['googlePlaceId'] ?? null;
        if (!is_string($placeId) || !preg_match('/^[A-Za-z0-9_-]{5,255}$/D', $placeId)) {
            return new GoogleSummary();
        }
        // 這支 API 匿名就能呼叫，要限流以免耗盡 Google 配額：
        // 每個來源每分鐘 10 次、每個地點每分鐘 6 次、全站每分鐘 30 次、每日 100 次。
        $this->limiter->check('google-summary-client', $client->ipHash, 10, 60);
        $this->limiter->check('google-summary-place', $id, 6, 60);
        $this->limiter->check('google-summary-global', 'all', 30, 60);
        $this->limiter->check('google-summary-daily', 'all', 100, 86400);
        if (!$this->slot->acquire()) {
            throw new ApplicationError(ErrorCode::RateLimited, '簡介服務忙碌中，請稍後再試。', [], 5);
        }
        try {
            $result = $this->provider->fetch($placeId);
            $fresh = $this->public->get($slug)['attraction'];
            $current = $this->records->find($record->id);
            return $current !== null && $current->deletedAt === null && $current->status === 'published'
                && $current->version === $record->version && is_array($fresh) && ($fresh['version'] ?? null) === $record->version
                ? $result : new GoogleSummary();
        } finally {
            $this->slot->release();
        }
    }
}
