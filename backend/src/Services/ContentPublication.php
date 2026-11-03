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

/**
 * 資料庫是編輯用的來源；公開頁面與 API 一律讀取最近一次成功產生、不再變動的快照。
 *
 * 新鮮度採一般網站的做法：後台儲存時立即重新發布；排程時間到、跨日、換週，以及直接改資料庫等
 * 沒有經過儲存流程的變化，由發布程序每 10 秒 tick() 比對後重新產生。讀取時不再逐次比對，
 * 所以這類變化最多延遲約 10 秒；發布失敗時繼續提供上一版，後台會提示並可重試（使用者 2026-11-03 決定）。
 */
class ContentPublication
{
    /**
     * 同一個請求中已讀取的快照，多個服務各自讀取時不必重讀檔案；重新發布後清除。
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

    /** 由發布程序每 10 秒呼叫；與資料庫重新比對，內容沒有變更時不重新產生。 */
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
        // 只有從未成功發布過（例如全新安裝）時才沒有可提供的版本。
        $current = $this->renderer->current();
        if ($current === null) {
            throw new PublicationPending();
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
