<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Throwable;
use Yinghua\Config;
use Yinghua\Contracts\ContentRenderer;
use Yinghua\Exceptions\PublicationPending;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\PublicationRepository;

/** 資料庫是編輯用的來源；公開頁面與 API 一律讀取最近一次成功產生、不再變動的快照。 */
class ContentPublication
{
    public function __construct(
        private readonly AttractionRepository $attractions,
        private readonly PublicationRepository $publications,
        private readonly ContentRenderer $renderer,
        private readonly Config $config,
        private readonly ?\Yinghua\Repositories\FilmRepository $films = null,
        private readonly ?\Yinghua\Contracts\Clock $clock = null,
        private readonly ?\Yinghua\Repositories\NewsRepository $news = null,
        private readonly ?\Yinghua\Repositories\OfferRepository $offers = null,
        private readonly ?SeoPublication $seo = null,
        private readonly ?\Yinghua\Repositories\RecommendedRouteRepository $routes = null,
        private readonly ?\Yinghua\Repositories\SearchRepository $search = null,
        private readonly ?\Yinghua\Repositories\GeneralSettingsRepository $general = null,
    ) {}

    /** @template T
     * @param \Closure():T $callback
     * @return T
     */
    public function exclusive(\Closure $callback): mixed
    {
        return $this->renderer->exclusive($callback);
    }

    /** 呼叫時內容已提交。發布過程任何一步失敗都只回傳 false，讓呼叫端照樣回應「已保存」，避免使用者以為失敗而重複新增。 */
    public function synchronize(): bool
    {
        $id = null;
        try {
            $snapshot = $this->projection();
            $id = $this->publications->begin($this->generation($snapshot));
            // 搜尋索引先整批寫入新版本，等 HTML 產生成功後才清掉舊版本，確保搜尋結果與頁面一致。
            $this->search?->replace($this->generation($snapshot), \Yinghua\DTO\SearchDocument::project($snapshot));
            $this->renderer->render($snapshot);
            $this->search?->prune($this->generation($snapshot));
            $this->publications->finish($id, true);

            return true;
        } catch (Throwable $error) {
            // 連記錄發布結果的資料庫都可能故障；這時只記 log，不能讓它蓋過「內容已保存」的事實。
            if ($id !== null) {
                try {
                    $this->publications->finish($id, false);
                } catch (Throwable $recordError) {
                    error_log('publication=' . $id . ' record_exception=' . $recordError::class);
                }
            }
            error_log('publication=' . ($id ?? 'unrecorded') . ' exception=' . $error::class);

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
        // 產生失敗、內容下架或排程切換期間，不能繼續對外提供過期的舊版本。
        $current = $this->renderer->current();
        if ($current === null) {
            throw new PublicationPending(null, []);
        }
        $expected = $this->projection();
        if (($current['generation'] ?? null) !== $this->generation($expected)) {
            throw new PublicationPending($current, $expected);
        }

        return $current;
    }

    /** @return array<string,mixed> */
    private function projection(): array
    {
        $now = $this->clock?->now() ?? time();
        $window = \Yinghua\DTO\ScreeningWindow::current($now);
        $data = ['origin' => $this->config->origin(),
            'rendererVersion' => $this->renderer->version(),
            'policyVersion' => $this->config->string('TERMS_VERSION', '2026-11-02'),
            'indexModifiedAt' => [],
            'items' => array_map(
                static fn(\Yinghua\DTO\Attraction $item): array => $item->publicArray(),
                $this->attractions->published($now),
            ),
            'aliases' => $this->attractions->aliases($now)];
        // 景點與電影共用同一個 generation；投影包含本週範圍，所以即使沒人編輯，換週時也會重新發布。
        if ($this->films !== null) {
            $data['films'] = array_map(static fn(\Yinghua\DTO\Film $film): array => $film->publicArray(), $this->films->published($now));
            $data['filmAliases'] = $this->films->aliases($now);
            $data['filmPeriod'] = $window->toArray();
        }
        if ($this->news !== null) {
            $posts = $this->news->published($now);
            $data['news'] = array_map(static fn(\Yinghua\DTO\NewsPost $post): array => $post->publicArray(), $posts);
            $data['indexModifiedAt'] = $this->indexDates($posts);
            $data['newsAliases'] = $this->news->aliases($now);
        }
        if ($this->offers !== null) {
            $today = \Yinghua\Policies\OfferValidity::day($now);
            $data['offerDate'] = $today;
            $offers = $this->offers->published($now);
            $data['indexModifiedAt'] = [...$data['indexModifiedAt'], ...$this->indexDates($offers)];
            $data['offers'] = array_map(static function (\Yinghua\DTO\Offer $offer) use ($today): array {
                $item = $offer->publicArray();
                $from = is_string($item['validFrom'] ?? null) ? $item['validFrom'] : null;
                $to = is_string($item['validTo'] ?? null) ? $item['validTo'] : null;
                $state = \Yinghua\Policies\OfferValidity::state($from, $to, $today);
                return [...$item,'validity' => $state,'active' => $state === 'active'];
            }, $offers);
            $data['offerAliases'] = $this->offers->aliases($now);
        }
        if ($this->routes !== null) {
            $data['recommendedRoutes'] = \Yinghua\Policies\RecommendedRouteSelection::project($this->routes->published(), $data['items']);
        }
        $data['detailNeighbors'] = \Yinghua\Policies\NeighborSelection::project($data);
        $data['home'] = \Yinghua\Policies\HomeSelection::fromSnapshot($data, $window)->toArray();
        if ($this->seo !== null) {
            $data['seo'] = $this->seo->project($data);
        }
        if ($this->general !== null) {
            $general = $this->general->read();
            $data['general'] = $general->toArray();
            $data['policyVersion'] = $general->values->consentVersion($this->config->string('TERMS_VERSION', '2026-11-02'));
        }
        $data['generation'] = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $data;
    }

    /** @param array<string,mixed> $snapshot */
    private function generation(array $snapshot): string
    {
        $generation = $snapshot['generation'] ?? null;
        if (!is_string($generation)) {
            throw new \RuntimeException('Invalid publication generation.');
        }

        return $generation;
    }

    /** 只採用已發布記錄的實際修改時間；不為此新增公開 JSON API 欄位或額外查詢。
     * @param list<\Yinghua\DTO\PublishedRecord> $records
     * @return array<string,string>
     */
    private function indexDates(array $records): array
    {
        $dates = [];
        foreach ($records as $record) {
            $url = $record->publicArray()['url'] ?? null;
            if (is_string($url) && $record->updatedAt !== null) {
                $dates[$url] = $record->updatedAt;
            }
        }
        return $dates;
    }
}
