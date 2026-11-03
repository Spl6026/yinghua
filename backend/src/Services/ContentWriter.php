<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Closure;
use Yinghua\Contracts\Transactions;
use Yinghua\Contracts\VersionedContent;
use Yinghua\DTO\ClientContext;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\ContentConflict;
use Yinghua\Exceptions\ErrorCode;
use Yinghua\Models\Account;
use Yinghua\Repositories\EventRepository;
use Yinghua\Support\Secrets;

/**
 * 後台內容（電影、景點、推薦路線）共同的儲存與刪除流程：
 * 發布鎖 → 交易 → 加鎖確認權限 → 讀出原資料並比對版本號 → 寫入 → 稽核紀錄 → 重新產生公開頁。
 * 各模組只提供自己的檢查與寫入，順序與錯誤訊息在這裡統一。
 */
final class ContentWriter
{
    public function __construct(
        private readonly Authorization $authorization,
        private readonly Transactions $transactions,
        private readonly ContentPublication $publication,
        private readonly EventRepository $events,
    ) {}

    /**
     * @template T of VersionedContent
     * @param string $module 權限模組（films／attractions／routes）
     * @param array{0:string,1:string} $audit 稽核紀錄的動作前綴與資源名稱，例如 ['route', 'routes']；
     *   沿用既有紀錄的寫法，新舊紀錄才不會混用兩種名稱
     * @param ?int $version 修改時用戶端帶來的版本號；新增時為 null
     * @param Closure(string, bool): T $find 依編號讀取，找不到時丟出 404
     * @param Closure(?T, string, Account): T $write 模組自己的檢查與寫入，回傳寫入後的資料
     * @return array{0:T,1:bool} 寫入後的資料，以及公開頁是否已同步
     */
    public function save(
        Account $actor,
        string $module,
        array $audit,
        ?string $id,
        ?int $version,
        Closure $find,
        Closure $write,
        ClientContext $client,
    ): array {
        // 交易外先確認一次權限，沒有權限時不必等待發布鎖。
        $creating = $id === null;
        $this->authorization->require($actor, $module, $creating ? 'create' : 'edit');

        return $this->publication->exclusive(function () use ($actor, $module, $audit, $id, $version, $find, $write, $client, $creating): array {
            $target = $id ?? Secrets::uuid();
            try {
                $record = $this->transactions->run(function () use ($actor, $module, $audit, $target, $version, $find, $write, $client, $creating): VersionedContent {
                    $fresh = $this->authorization->require($actor, $module, $creating ? 'create' : 'edit', true);
                    $before = $creating ? null : $find($target, true);
                    if ($before !== null && $before->version !== $version) {
                        throw new ApplicationError(ErrorCode::Conflict, '資料已被其他人修改，請重新載入。');
                    }
                    $updated = $write($before, $target, $fresh);
                    $this->events->content(
                        $fresh->id,
                        $audit[0] . '-' . ($creating ? 'create' : 'update'),
                        $audit[1],
                        $target,
                        $before?->version,
                        $updated->version,
                        $updated->status,
                        $client,
                    );

                    return $updated;
                });
            } catch (ContentConflict) {
                throw self::slugConflict();
            }

            return [$record, $this->publication->synchronize()];
        });
    }

    /**
     * @template T of VersionedContent
     * @param array{0:string,1:string} $audit 稽核紀錄的動作前綴與資源名稱
     * @param Closure(string, bool): T $find
     * @param Closure(T): void $remove 模組自己的刪除前檢查與刪除
     * @return bool 公開頁是否已同步
     */
    public function delete(
        Account $actor,
        string $module,
        array $audit,
        string $id,
        int $version,
        Closure $find,
        Closure $remove,
        ClientContext $client,
    ): bool {
        return $this->publication->exclusive(function () use ($actor, $module, $audit, $id, $version, $find, $remove, $client): bool {
            $this->transactions->run(function () use ($actor, $module, $audit, $id, $version, $find, $remove, $client): void {
                $fresh = $this->authorization->require($actor, $module, 'delete', true);
                $before = $find($id, true);
                if ($before->version !== $version) {
                    throw new ApplicationError(ErrorCode::Conflict, '資料已變更，請重新載入。');
                }
                $remove($before);
                $this->events->content($fresh->id, $audit[0] . '-delete', $audit[1], $id, $version, $version + 1, 'archived', $client);
            });

            return $this->publication->synchronize();
        });
    }

    /** 網址識別已被其他內容使用或保留為舊網址；錯誤標在網址識別欄位旁。 */
    public static function slugConflict(): ApplicationError
    {
        return new ApplicationError(
            ErrorCode::Conflict,
            '網址識別已使用或保留於歷史網址，請改用其他識別。',
            ['slug' => '此識別已使用。'],
        );
    }
}
