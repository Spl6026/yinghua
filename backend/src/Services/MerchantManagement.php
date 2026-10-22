<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\ContentImportService;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\ImportItem;
use Yinghua\DTO\MerchantInput;
use Yinghua\DTO\PreparedImport;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Policies\ImportVersion;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\MerchantRepository;

/** [D：資料表建議 15；E-26] 店家前置能力沿 offers 權限，不擴大既有權限矩陣。 */
class MerchantManagement implements ContentImportService
{
    public function __construct(private readonly MerchantRepository $merchants, private readonly Authorization $authorization, private readonly EventRepository $events) {}

    public function prepare(Account $actor, ImportItem $item, bool $lock): PreparedImport
    {
        $before = $this->merchants->find($item->id, $lock);
        $this->authorization->require($actor, 'offers', $before === null ? 'create' : 'edit', $lock);
        if (($before?->fields['deleted_at'] ?? null) !== null) {
            throw new ApplicationError('CONFLICT', '此店家已刪除，不能重新使用識別。');
        }
        $input = MerchantInput::fromArray($item->values, $before === null);
        $fields = $input->fields;
        $link = $fields['attraction_id'] ?? null;
        if (is_string($link)) {
            if (!$this->merchants->attractionExists($link)) {
                throw ApplicationError::invalid('attractionId', '找不到此景點連結。');
            }
            $this->merchants->assertLinkAvailable($link, $item->id);
        }
        if ($before !== null && !array_key_exists('status', $fields)) {
            $fields['status'] = $before->fields['status'] ?? 'inactive';
        }
        $unchanged = $before !== null && ImportVersion::unchanged($before->fields, $fields);
        if ($before !== null) {
            ImportVersion::assertCurrent($input->version, $before->version, $unchanged);
        }
        $version = $before === null ? 0 : $before->version;
        return new PreparedImport($item, new MerchantInput($fields, $input->version), $before === null, $unchanged, $version);
    }

    public function save(Account $actor, PreparedImport $change, ClientContext $client): void
    {
        if (!$change->input instanceof MerchantInput) {
            throw new \LogicException('Merchant input required.');
        }
        $fresh = $this->authorization->require($actor, 'offers', $change->creating ? 'create' : 'edit', true);
        $this->merchants->save($change->item->id, $change->input, $change->creating);
        $this->events->content(
            $fresh->id,
            $change->creating ? 'merchant-create' : 'merchant-update',
            'merchants',
            $change->item->id,
            $change->creating ? null : $change->version,
            $change->version + 1,
            (string) $change->input->fields['status'],
            $client,
        );
    }
}
