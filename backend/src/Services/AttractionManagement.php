<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\Attraction;
use Yinghua\DTO\AttractionInput;
use Yinghua\DTO\AttractionPage;
use Yinghua\DTO\AttractionWriteResult;
use Yinghua\DTO\ClientContext;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\ContentStatus;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Policies\GooglePlaceLinkPolicy;
use Yinghua\Exceptions\ErrorCode;
use Yinghua\Support\Uuid;

/** 景點的新增、編輯、刪除流程，負責權限檢查與交易；Controller 不直接處理 SQL。 */
class AttractionManagement
{
    public function __construct(
        private readonly AttractionRepository $attractions,
        private readonly Authorization $authorization,
        private readonly ContentWriter $writer,
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

    /** 有檢視或編輯權限即可讀取單筆；規格的編輯頁對象是「具 create／edit 權限者」，不另外要求列表檢視權限。 */
    public function get(Account $actor, string $id): Attraction
    {
        $this->authorization->requireAny($actor, 'attractions', ['view', 'edit']);

        return $this->target($id);
    }

    public function save(Account $actor, ?string $id, AttractionInput $input, ClientContext $client): AttractionWriteResult
    {
        [$item, $synchronized] = $this->writer->save(
            $actor,
            'attractions',
            ['attraction', 'attraction'],
            $id,
            $input->version,
            $this->target(...),
            function (?Attraction $before, string $targetId, Account $fresh) use ($input): Attraction {
                $input = $input->withDefaultSlug($before === null ? 'place-' . str_replace('-', '', $targetId) : $before->slug);
                $this->placeLinks->assertConfirmed($before, $input);
                $media = $input->fields['media_id'];
                if (is_string($media) && !$this->attractions->mediaExists($media)) {
                    throw ApplicationError::invalid('mediaId', '找不到可用的景點圖片。');
                }
                if ($input->fields['is_museum'] === true && $this->attractions->hasMuseum($targetId)) {
                    throw ApplicationError::invalid('isMuseum', '已設定博物館介紹景點，請先取消原設定。');
                }
                $this->attractions->save($targetId, $input, $before === null, $fresh->id);

                return $this->target($targetId);
            },
            $client,
        );

        return new AttractionWriteResult($item, $synchronized);
    }

    public function delete(Account $actor, string $id, int $version, ClientContext $client): AttractionWriteResult
    {
        $synchronized = $this->writer->delete(
            $actor,
            'attractions',
            ['attraction', 'attraction'],
            $id,
            $version,
            $this->target(...),
            function (Attraction $item): void {
                if ($this->attractions->referenced($item->id)) {
                    throw new ApplicationError(ErrorCode::Conflict, '此景點仍被推薦路線或店家優惠引用，請先調整關聯再刪除。');
                }
                $this->attractions->delete($item->id);
            },
            $client,
        );

        return new AttractionWriteResult(null, $synchronized);
    }

    public function retry(Account $actor): bool
    {
        $this->authorization->require($actor, 'attractions', 'edit');

        return $this->publication->retry();
    }

    private function target(string $id, bool $lock = false): Attraction
    {
        $item = Uuid::isValid($id) ? $this->attractions->find($id, $lock) : null;
        if ($item === null || $item->deletedAt !== null) {
            throw new ApplicationError(ErrorCode::NotFound, '找不到此景點。');
        }

        return $item;
    }
}
