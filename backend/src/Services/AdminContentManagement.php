<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Contracts\AdminContentRecords;
use Yinghua\Contracts\ContentEditor;
use Yinghua\Contracts\Transactions;
use Yinghua\Contracts\Clock;
use Yinghua\DTO\{AdminContentQuery, ClientContext, ContentFields, ContentPage, ContentWriteResult, Input, NewsPost, Offer};
use Yinghua\Exceptions\{ApplicationError, ContentConflict};
use Yinghua\Models\{Account, ContentStatus};
use Yinghua\Repositories\EventRepository;
use Yinghua\Support\Secrets;
use Yinghua\Policies\OfferValidity;
use Yinghua\Exceptions\ErrorCode;

/**
 * 後台 HTTP 用的消息與優惠管理；兩者的列表、讀取、儲存與刪除流程相同，只差模組名稱與資料表。
 * 與 CLI 匯入共用同一個 Editor，Controller 不必自行組裝匯入資料。
 */
class AdminContentManagement
{
    /**
     * @param string $module 權限與稽核紀錄使用的模組名稱（news／offers）
     * @param string $deleteAction 刪除時寫入稽核紀錄的動作名稱
     */
    public function __construct(
        private readonly string $module,
        private readonly string $deleteAction,
        private readonly AdminContentRecords $records,
        private readonly ContentEditor $editor,
        private readonly Authorization $authorization,
        private readonly Transactions $transactions,
        private readonly ContentPublication $publication,
        private readonly EventRepository $events,
        private readonly Clock $clock,
    ) {}

    public function listing(Account $actor, AdminContentQuery $query): ContentPage
    {
        $this->authorization->require($actor, $this->module, 'view');
        if ($query->status !== '' && ContentStatus::tryFrom($query->status) === null) {
            throw ApplicationError::invalid('status', '發布狀態無效。');
        }
        return $this->records->listing($query, OfferValidity::day($this->clock->now()));
    }

    public function get(Account $actor, string $id): NewsPost|Offer
    {
        $this->authorization->requireAny($actor, $this->module, ['view','edit']);
        return $this->target($id);
    }

    /** @param array<string,mixed> $values */
    public function save(Account $actor, ?string $id, array $values, ClientContext $client): ContentWriteResult
    {
        $this->authorization->require($actor, $this->module, $id === null ? 'create' : 'edit');
        if ($id !== null) {
            (new Input($values))->positiveInt('version');
        }
        return $this->publication->exclusive(function () use ($actor, $id, $values, $client): ContentWriteResult {
            $target = $id ?? Secrets::uuid();
            try {
                $record = $this->transactions->run(function () use ($actor, $id, $target, $values, $client): NewsPost|Offer {
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

    public function delete(Account $actor, string $id, int $version, ClientContext $client): ContentWriteResult
    {
        return $this->publication->exclusive(function () use ($actor, $id, $version, $client): ContentWriteResult {
            $this->transactions->run(function () use ($actor, $id, $version, $client): void {
                $fresh = $this->authorization->require($actor, $this->module, 'delete', true);
                $before = $this->target($id, true);
                if ($before->version !== $version) {
                    throw new ApplicationError(ErrorCode::Conflict, '資料已變更，請重新載入。');
                }
                $this->records->delete($id);
                $this->events->content($fresh->id, $this->deleteAction, $this->module, $id, $version, $version + 1, 'archived', $client);
            });
            return new ContentWriteResult(null, $this->publication->synchronize());
        });
    }


    private function target(string $id, bool $lock = false): NewsPost|Offer
    {
        ContentFields::uuid($id, 'id');
        $record = $this->records->find($id, $lock);
        if ($record === null || ($record->fields['deleted_at'] ?? null) !== null) {
            throw new ApplicationError(ErrorCode::NotFound, '找不到此內容。');
        }
        return $record;
    }
}
