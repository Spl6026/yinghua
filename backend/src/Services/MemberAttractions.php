<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Clock;
use Yinghua\DTO\MemberAttractionSnapshot;
use Yinghua\DTO\PublicCollection;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Exceptions\ErrorCode;

/** 會員收藏與行程顯示的景點資訊只取自已成功發布的公開版本，不會從草稿補上名稱、介紹或圖片。 */
class MemberAttractions
{
    public function __construct(
        private readonly ContentPublication $publication,
        private readonly AttractionRepository $attractions,
        private readonly Clock $clock,
    ) {}

    public function snapshot(): MemberAttractionSnapshot
    {
        try {
            $snapshot = $this->publication->read();
        } catch (ApplicationError $error) {
            if ($error->errorCode !== ErrorCode::PublicationPending) {
                throw $error;
            }

            return new MemberAttractionSnapshot(false, []);
        }
        $byId = [];
        foreach (PublicCollection::items($snapshot, 'items') as $item) {
            if (!is_string($item['id'] ?? null)) {
                throw new \RuntimeException('Invalid published attraction.');
            }
            $byId[$item['id']] = $item;
        }

        return new MemberAttractionSnapshot(true, $byId);
    }

    /** 必須在會員寫入交易內、鎖住上層資料列之後呼叫。 */
    public function requireAvailable(string $id): string
    {
        return $this->requireAvailableNames([$id])[$id];
    }

    /** 整份行程只讀一次公開投影；依 ID 排序後逐一鎖定景點，固定的鎖定順序可避免死結。
     * @param list<string> $ids
     * @return array<string,string>
     */
    public function requireAvailableNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $snapshot = $this->snapshot();
        if (!$snapshot->ready) {
            throw new ApplicationError(ErrorCode::PublicationPending, '公開資訊正在更新，請稍後再加入。', retryAfter: 5);
        }
        $names = [];
        sort($ids);
        foreach (array_unique($ids) as $id) {
            $public = $snapshot->items[$id] ?? null;
            $current = $this->attractions->find($id, true);
            $stamp = $current?->values['publishAt'] ?? null;
            if ($public === null || $current === null || $current->deletedAt !== null || $current->status !== 'published'
                || !is_string($stamp) || strtotime($stamp) > $this->clock->now()) {
                throw new ApplicationError(ErrorCode::NotFound, '此地點目前無法加入，請重新選擇。');
            }
            if (($public['version'] ?? null) !== $current->version) {
                throw new ApplicationError(ErrorCode::PublicationPending, '公開資訊正在更新，請稍後再加入。', retryAfter: 5);
            }
            $name = $public['name'] ?? null;
            if (!is_string($name)) {
                throw new \RuntimeException('Invalid public attraction name.');
            }

            $names[$id] = $name;
        }
        return $names;
    }
}
