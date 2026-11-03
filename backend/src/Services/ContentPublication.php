<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Closure;
use RuntimeException;
use Throwable;
use Yinghua\Contracts\ContentRenderer;
use Yinghua\DTO\SearchDocument;
use Yinghua\Exceptions\PublicationPending;
use Yinghua\Repositories\PublicationRepository;
use Yinghua\Repositories\SearchRepository;
use Yinghua\Support\ErrorLog;

/** 資料庫是編輯用的來源；公開頁面與 API 一律讀取最近一次成功產生、不再變動的快照。 */
class ContentPublication
{
    /**
     * 同一個請求中已驗證過的快照。驗證要查遍所有公開內容並計算雜湊，
     * 同一請求裡多個服務各自讀取時不必重做；重新發布後清除。
     *
     * @var array<string,mixed>|null
     */
    private ?array $verified = null;

    public function __construct(
        private readonly PublicationRepository $publications,
        private readonly ContentRenderer $renderer,
        private readonly SearchRepository $search,
        private readonly PublicationSnapshotBuilder $snapshots,
    ) {}

    /** @template T
     * @param Closure():T $callback
     * @return T
     */
    public function exclusive(Closure $callback): mixed
    {
        return $this->renderer->exclusive($callback);
    }

    /** 呼叫時內容已提交。發布過程任何一步失敗都只回傳 false，讓呼叫端照樣回應「已保存」，避免使用者以為失敗而重複新增。 */
    public function synchronize(): bool
    {
        $this->verified = null;
        $id = null;
        try {
            $snapshot = $this->projection();
            $generation = $this->generation($snapshot);
            $id = $this->publications->begin($generation);
            // 搜尋索引先整批寫入新版本，等 HTML 產生成功後才清掉舊版本，確保搜尋結果與頁面一致。
            $this->search->replace($generation, SearchDocument::project($snapshot));
            $this->renderer->render($snapshot);
            $this->search->prune($generation);
            $this->publications->finish($id, true);

            return true;
        } catch (Throwable $error) {
            // 連記錄發布結果的資料庫都可能故障；這時只記 log，不能讓它蓋過「內容已保存」的事實。
            if ($id !== null) {
                try {
                    $this->publications->finish($id, false);
                } catch (Throwable $recordError) {
                    ErrorLog::exception('publication=' . $id . ' record', $recordError);
                }
            }
            ErrorLog::exception('publication=' . ($id ?? 'unrecorded'), $error);

            return false;
        }
    }

    public function retry(): bool
    {
        return $this->exclusive($this->synchronize(...));
    }

    /** 由 CLI 排程定期呼叫；內容沒有變更時不重新產生。 */
    public function tick(): bool
    {
        return $this->exclusive(function (): bool {
            return $this->ready() || $this->synchronize();
        });
    }

    public function ready(): bool
    {
        return ($this->renderer->current()['generation'] ?? null) === $this->generation($this->projection());
    }

    /** @return array<string,mixed> */
    public function read(): array
    {
        if ($this->verified !== null) {
            return $this->verified;
        }
        // 產生失敗、內容下架或排程切換期間，不能繼續對外提供過期的舊版本。
        $current = $this->renderer->current();
        if ($current === null) {
            throw new PublicationPending(null, []);
        }
        $expected = $this->projection();
        if (($current['generation'] ?? null) !== $this->generation($expected)) {
            throw new PublicationPending($current, $expected);
        }

        return $this->verified = $current;
    }

    /** @return array<string,mixed> */
    private function projection(): array
    {
        return $this->snapshots->build($this->renderer->version());
    }

    /** @param array<string,mixed> $snapshot */
    private function generation(array $snapshot): string
    {
        $generation = $snapshot['generation'] ?? null;
        if (!is_string($generation)) {
            throw new RuntimeException('Invalid publication generation.');
        }

        return $generation;
    }
}
