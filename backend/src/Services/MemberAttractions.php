<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Clock;
use Yinghua\DTO\MemberAttractionSnapshot;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Repositories\AttractionRepository;

/** [E-27] 會員私人資料只疊上成功公開投影；不從草稿補名稱、介紹或圖片。 */
class MemberAttractions
{
    public function __construct(private readonly ContentPublication $publication, private readonly AttractionRepository $attractions, private readonly Clock $clock) {}

    public function snapshot(): MemberAttractionSnapshot
    {
        try {
            $snapshot = $this->publication->read();
        } catch (ApplicationError $error) {
            if ($error->errorCode !== 'PUBLICATION_PENDING') {
                throw $error;
            }

            return new MemberAttractionSnapshot(false, []);
        }
        $items = $snapshot['items'] ?? null;
        if (!is_array($items)) {
            throw new \RuntimeException('Invalid published attractions.');
        }
        $byId = [];
        foreach ($items as $value) {
            if (!is_array($value) || !is_string($value['id'] ?? null)) {
                throw new \RuntimeException('Invalid published attraction.');
            }
            $item = [];
            foreach ($value as $key => $field) {
                if (is_string($key)) {
                    $item[$key] = $field;
                }
            }
            $byId[$value['id']] = $item;
        }

        return new MemberAttractionSnapshot(true, $byId);
    }

    /** Must run inside the member write transaction, after locking its parent rows. */
    public function requireAvailable(string $id): string
    {
        return $this->requireAvailableNames([$id])[$id];
    }

    /** [E-33] 整份行程只讀一次公開投影；固定ID次序鎖景點。
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
            throw new ApplicationError('PUBLICATION_PENDING', '公開資訊正在更新，請稍後再加入。', retryAfter: 5);
        }
        $names = [];
        sort($ids);
        foreach (array_unique($ids) as $id) {
            $public = $snapshot->items[$id] ?? null;
            $current = $this->attractions->find($id, true);
            $stamp = $current?->values['publishAt'] ?? null;
            if ($public === null || $current === null || $current->deletedAt !== null || $current->status !== 'published' || !is_string($stamp) || strtotime($stamp) > $this->clock->now()) {
                throw new ApplicationError('NOT_FOUND', '此地點目前無法加入，請重新選擇。');
            }
            if (($public['version'] ?? null) !== $current->version) {
                throw new ApplicationError('PUBLICATION_PENDING', '公開資訊正在更新，請稍後再加入。', retryAfter: 5);
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
