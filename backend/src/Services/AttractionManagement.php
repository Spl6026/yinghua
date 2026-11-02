<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\Attraction;
use Yinghua\DTO\AttractionInput;
use Yinghua\DTO\AttractionPage;
use Yinghua\DTO\AttractionWriteResult;
use Yinghua\DTO\ClientContext;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\ContentConflict;
use Yinghua\Models\Account;
use Yinghua\Models\ContentStatus;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\EventRepository;
use Yinghua\Support\Secrets;
use Yinghua\Policies\GooglePlaceLinkPolicy;
use Yinghua\Exceptions\ErrorCode;

/** 景點的新增、編輯、刪除流程，負責權限檢查與交易；Controller 不直接處理 SQL。 */
class AttractionManagement
{
    public function __construct(
        private readonly AttractionRepository $attractions,
        private readonly Authorization $authorization,
        private readonly Transactions $transactions,
        private readonly EventRepository $events,
        private readonly ContentPublication $publication,
        private readonly GooglePlaceLinkPolicy $placeLinks,
    ) {}

    public function listing(Account $actor, string $q, string $status, int $page, int $size): AttractionPage
    {
        $this->authorization->require($actor, 'attractions', 'view');
        if ($status !== '' && ContentStatus::tryFrom($status) === null) {
            throw ApplicationError::invalid('status', '不支援的狀態。');
        }

        return $this->attractions->listing($q, $status, $page, $size);
    }

    /** 只有新增權限也能取得範例資料試填，但不會因此看到管理列表或草稿。
     * @return list<array<string,mixed>>
     */
    public function samples(Account $actor): array
    {
        $this->authorization->require($actor, 'attractions', 'create');

        return array_map(static fn(Attraction $item): array => $item->sampleArray(), $this->attractions->samples());
    }

    public function get(Account $actor, string $id): Attraction
    {
        $this->authorization->require($actor, 'attractions', 'view');

        return $this->target($id);
    }

    public function save(Account $actor, ?string $id, AttractionInput $input, ClientContext $client): AttractionWriteResult
    {
        return $this->publication->exclusive(function () use ($actor, $id, $input, $client): AttractionWriteResult {
            $creating = $id === null;
            $targetId = $id ?? Secrets::uuid();
            try {
                $item = $this->transactions->run(function () use ($actor, $targetId, $input, $client, $creating): Attraction {
                    $fresh = $this->authorization->require($actor, 'attractions', $creating ? 'create' : 'edit', true);
                    $before = $creating ? null : $this->target($targetId, true);
                    $input = $input->withDefaultSlug($before === null ? 'place-' . str_replace('-', '', $targetId) : $before->slug);
                    if ($before !== null && $before->version !== $input->version) {
                        throw new ApplicationError(ErrorCode::Conflict, '資料已被其他人修改，請重新載入。');
                    }
                    $this->placeLinks->assertConfirmed($before, $input);
                    $media = $input->fields['media_id'];
                    if (is_string($media) && !$this->attractions->mediaExists($media)) {
                        throw ApplicationError::invalid('mediaId', '找不到可用的景點圖片。');
                    }
                    if ($input->fields['is_museum'] === true && $this->attractions->hasMuseum($targetId)) {
                        throw ApplicationError::invalid('isMuseum', '已設定博物館介紹景點，請先取消原設定。');
                    }
                    $this->attractions->save($targetId, $input, $creating, $fresh->id);
                    $updated = $this->target($targetId);
                    $this->events->content(
                        $fresh->id,
                        'attraction-' . ($creating ? 'create' : 'update'),
                        'attraction',
                        $targetId,
                        $before?->version,
                        $updated->version,
                        $input->status->value,
                        $client,
                    );

                    return $updated;
                });
            } catch (ContentConflict) {
                throw new ApplicationError(ErrorCode::Conflict, '網址識別已使用或保留於歷史網址，請改用其他識別。', ['slug' => '此識別已使用。']);
            }

            return new AttractionWriteResult($item, $this->publication->synchronize());
        });
    }

    public function delete(Account $actor, string $id, int $version, ClientContext $client): AttractionWriteResult
    {
        return $this->publication->exclusive(function () use ($actor, $id, $version, $client): AttractionWriteResult {
            $this->transactions->run(function () use ($actor, $id, $version, $client): void {
                $fresh = $this->authorization->require($actor, 'attractions', 'delete', true);
                $target = $this->target($id, true);
                if ($target->version !== $version) {
                    throw new ApplicationError(ErrorCode::Conflict, '資料已變更，請重新載入。');
                }
                if ($this->attractions->referenced($id)) {
                    throw new ApplicationError(ErrorCode::Conflict, '此景點仍被推薦路線或店家優惠引用，請先調整關聯再刪除。');
                }
                $this->attractions->delete($id);
                $this->events->content($fresh->id, 'attraction-delete', 'attraction', $id, $version, $version + 1, 'archived', $client);
            });

            return new AttractionWriteResult(null, $this->publication->synchronize());
        });
    }

    public function retry(Account $actor): bool
    {
        $this->authorization->require($actor, 'attractions', 'edit');

        return $this->publication->retry();
    }

    private function target(string $id, bool $lock = false): Attraction
    {
        $item = $this->attractions->find($id, $lock);
        if ($item === null || $item->deletedAt !== null) {
            throw new ApplicationError(ErrorCode::NotFound, '找不到此景點。');
        }

        return $item;
    }
}
