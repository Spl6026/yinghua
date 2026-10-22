<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Clock;
use Yinghua\Contracts\ContentImportService;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\ImportItem;
use Yinghua\DTO\OfferInput;
use Yinghua\DTO\PreparedImport;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Models\MediaModule;
use Yinghua\Policies\ContentCover;
use Yinghua\Policies\ImportVersion;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\MediaRepository;
use Yinghua\Repositories\MerchantRepository;
use Yinghua\Repositories\OfferRepository;

/** [S：功能規格 G11／G23；E-17／26] 優惠的店家／條款／期間共用寫入規則。 */
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
        $before = $this->offers->find($item->id, $lock);
        $this->authorization->require($actor, 'offers', $before === null ? 'create' : 'edit', $lock);
        if (($before?->fields['deleted_at'] ?? null) !== null) {
            throw new ApplicationError('CONFLICT', '此識別已刪除，不能以匯入重新使用。');
        }
        $input = OfferInput::fromArray($item->values, $before === null, $this->clock->now());
        $fields = $input->fields;
        if ($fields['slug'] === '') {
            $fields['slug'] = $before?->fields['slug'] ?? 'offer-' . str_replace('-', '', $item->id);
        }
        $this->offers->assertSlugAvailable((string) $fields['slug'], $item->id);
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
        return new PreparedImport(
            $item,
            new OfferInput($fields, $input->version),
            $before === null,
            $unchanged,
            $version,
            ($merchant->fields['status'] ?? '') === 'active' ? [] : ['merchant_inactive'],
        );
    }

    public function save(Account $actor, PreparedImport $change, ClientContext $client): void
    {
        if (!$change->input instanceof OfferInput) {
            throw new \LogicException('Offer input required.');
        }
        $fresh = $this->authorization->require($actor, 'offers', $change->creating ? 'create' : 'edit', true);
        $this->offers->save($change->item->id, $change->input, $change->creating, $fresh->id);
        $this->events->content(
            $fresh->id,
            $change->creating ? 'offer-create' : 'offer-update',
            'offers',
            $change->item->id,
            $change->creating ? null : $change->version,
            $change->version + 1,
            (string) $change->input->fields['status'],
            $client,
        );
    }
}
