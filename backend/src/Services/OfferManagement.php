<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Clock;
use Yinghua\Contracts\ContentImportService;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\ImportItem;
use Yinghua\DTO\OfferInput;
use Yinghua\DTO\PreparedImport;
use Yinghua\DTO\PreparedContentChange;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\MediaModule;
use Yinghua\Policies\ContentCover;
use Yinghua\Policies\ImportVersion;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\MediaRepository;
use Yinghua\Repositories\MerchantRepository;
use Yinghua\Repositories\OfferRepository;
use Yinghua\Exceptions\ErrorCode;

/** 優惠的寫入流程，統一處理店家、條款與活動期間的規則。 */
class OfferManagement implements ContentImportService
{
    public function __construct(
        private readonly OfferRepository $offers,
        private readonly MerchantRepository $merchants,
        private readonly MediaRepository $media,
        private readonly Authorization $authorization,
        private readonly EventRepository $events,
        private readonly ContentCover $cover,
        private readonly Clock $clock,
    ) {}

    public function prepare(Account $actor, ImportItem $item, bool $lock): PreparedImport
    {
        $change = $this->prepareValues($actor, $item->id, $item->values, $lock);
        return new PreparedImport($item, $change->input, $change->creating, $change->unchanged, $change->version, $change->warnings);
    }

    /** @param array<string,mixed> $values */
    public function prepareValues(Account $actor, string $id, array $values, bool $lock, ?bool $creating = null): PreparedContentChange
    {
        $before = $this->offers->find($id, $lock);
        if ($creating === false && ($before === null || ($before->fields['deleted_at'] ?? null) !== null)) {
            throw new ApplicationError(ErrorCode::NotFound, '找不到此內容。');
        }
        if ($creating === true && $before !== null) {
            throw new ApplicationError(ErrorCode::Conflict, '此識別已存在。');
        }
        $this->authorization->require($actor, 'offers', $before === null ? 'create' : 'edit', $lock);
        if (($before?->fields['deleted_at'] ?? null) !== null) {
            throw new ApplicationError(ErrorCode::Conflict, '此識別已刪除，不能以匯入重新使用。');
        }
        $input = OfferInput::fromArray($values, $before === null, $this->clock->now());
        $fields = $input->fields;
        if ($fields['slug'] === '') {
            $fields['slug'] = $before?->fields['slug'] ?? 'offer-' . str_replace('-', '', $id);
        }
        $this->offers->assertSlugAvailable((string) $fields['slug'], $id);
        $merchant = $this->merchants->find((string) $fields['merchant_id'], $lock);
        if ($merchant === null || ($merchant->fields['deleted_at'] ?? null) !== null) {
            throw ApplicationError::invalid('merchantId', '關聯店家不存在或已刪除。');
        }
        $coverId = $fields['cover_media_id'] ?? null;
        $fields = $this->cover->apply($fields, is_string($coverId) ? $this->media->find($coverId, MediaModule::Offers) : null);
        if ($before !== null) {
            foreach (['status','publish_at'] as $key) {
                if (!array_key_exists($key, $fields)) {
                    $fields[$key] = $before->fields[$key] ?? null;
                }
            }
        }
        $unchanged = $before !== null && ImportVersion::unchanged($before->fields, $fields);
        if ($before !== null) {
            ImportVersion::assertCurrent($input->version, $before->version, $unchanged);
        }
        $version = $before === null ? 0 : $before->version;
        return new PreparedContentChange(
            $id,
            new OfferInput($fields, $input->version),
            $before === null,
            $unchanged,
            $version,
            ($merchant->fields['status'] ?? '') === 'active' ? [] : ['merchant_inactive'],
        );
    }

    public function save(Account $actor, PreparedImport $change, ClientContext $client): void
    {
        $this->saveChange(
            $actor,
            new PreparedContentChange(
                $change->item->id,
                $change->input,
                $change->creating,
                $change->unchanged,
                $change->version,
                $change->warnings,
            ),
            $client,
        );
    }

    public function saveChange(Account $actor, PreparedContentChange $change, ClientContext $client): void
    {
        if (!$change->input instanceof OfferInput) {
            throw new \LogicException('Offer input required.');
        }
        $fresh = $this->authorization->require($actor, 'offers', $change->creating ? 'create' : 'edit', true);
        $this->offers->save($change->id, $change->input, $change->creating, $fresh->id);
        $this->events->content(
            $fresh->id,
            $change->creating ? 'offer-create' : 'offer-update',
            'offers',
            $change->id,
            $change->creating ? null : $change->version,
            $change->version + 1,
            (string) $change->input->fields['status'],
            $client,
        );
    }
}
