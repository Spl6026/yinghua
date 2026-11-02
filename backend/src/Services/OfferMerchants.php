<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\{AdminContentQuery, ClientContext, ContentFields, ContentPage, ContentWriteResult, Input, Merchant};
use Yinghua\Exceptions\{ApplicationError, ContentConflict};
use Yinghua\Models\{Account};
use Yinghua\Repositories\{MerchantRepository};
use Yinghua\Support\Secrets;
use Yinghua\Exceptions\ErrorCode;

/** 後台 HTTP 用的店家管理服務；與 CLI 匯入共用同一個 Editor，Controller 不必自行組裝匯入資料。 */
class OfferMerchants
{
    public function __construct(
        private readonly MerchantRepository $records,
        private readonly MerchantManagement $editor,
        private readonly Authorization $authorization,
        private readonly Transactions $transactions,
        private readonly ContentPublication $publication,
    ) {}

    public function listing(Account $actor, AdminContentQuery $query): ContentPage
    {
        $this->authorization->requireAny($actor, 'offers', ['view','create','edit']);
        if (!in_array($query->status, ['', 'active', 'inactive'], true)) {
            throw ApplicationError::invalid('status', '店家狀態無效。');
        }
        return $this->records->listing($query);
    }

    public function get(Account $actor, string $id): Merchant
    {
        $this->authorization->requireAny($actor, 'offers', ['view','edit']);
        return $this->target($id);
    }

    /** @param array<string,mixed> $values */
    public function save(Account $actor, ?string $id, array $values, ClientContext $client): ContentWriteResult
    {
        $this->authorization->require($actor, 'offers', $id === null ? 'create' : 'edit');
        if ($id !== null) {
            (new Input($values))->positiveInt('version');
        }
        return $this->publication->exclusive(function () use ($actor, $id, $values, $client): ContentWriteResult {
            $target = $id ?? Secrets::uuid();
            try {
                $record = $this->transactions->run(function () use ($actor, $id, $target, $values, $client): Merchant {
                    $change = $this->editor->prepareValues($actor, $target, $values, true, $id === null);
                    // 後台編輯必須帶著相符的版本號，避免覆蓋別人的修改；CLI 的重複匯入則維持原本的冪等行為。
                    if ($id !== null && $change->version !== $change->input->version) {
                        throw new ApplicationError(ErrorCode::Conflict, '資料已被其他人修改，請重新載入。');
                    }
                    if (!$change->unchanged) {
                        $this->editor->saveChange($actor, $change, $client);
                    }
                    return $this->target($target);
                });
            } catch (ContentConflict) {
                throw new ApplicationError(ErrorCode::Conflict, '網址或關聯識別已使用，請重新載入或改用其他識別。');
            }
            return new ContentWriteResult($record, $this->publication->synchronize());
        });
    }


    private function target(string $id, bool $lock = false): Merchant
    {
        ContentFields::uuid($id, 'id');
        $record = $this->records->find($id, $lock);
        if ($record === null || ($record->fields['deleted_at'] ?? null) !== null) {
            throw new ApplicationError(ErrorCode::NotFound, '找不到此內容。');
        }
        return $record;
    }
}
