<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Closure;
use RuntimeException;
use Throwable;
use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\Contracts\ContentRenderer;
use Yinghua\DTO\Attraction;
use Yinghua\DTO\Film;
use Yinghua\DTO\NewsPost;
use Yinghua\DTO\Offer;
use Yinghua\DTO\PublishedRecord;
use Yinghua\DTO\ScreeningWindow;
use Yinghua\DTO\SearchDocument;
use Yinghua\Exceptions\PublicationPending;
use Yinghua\Policies\HomeSelection;
use Yinghua\Policies\NeighborSelection;
use Yinghua\Policies\OfferValidity;
use Yinghua\Policies\RecommendedRouteSelection;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\FilmRepository;
use Yinghua\Repositories\GeneralSettingsRepository;
use Yinghua\Repositories\NewsRepository;
use Yinghua\Repositories\OfferRepository;
use Yinghua\Repositories\PublicationRepository;
use Yinghua\Repositories\RecommendedRouteRepository;
use Yinghua\Repositories\SearchRepository;
use Yinghua\Support\ErrorLog;
use Yinghua\Support\Json;

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
        private readonly AttractionRepository $attractions,
        private readonly PublicationRepository $publications,
        private readonly ContentRenderer $renderer,
        private readonly Config $config,
        private readonly FilmRepository $films,
        private readonly Clock $clock,
        private readonly NewsRepository $news,
        private readonly OfferRepository $offers,
        private readonly SeoPublication $seo,
        private readonly RecommendedRouteRepository $routes,
        private readonly SearchRepository $search,
        private readonly GeneralSettingsRepository $general,
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
            $id = $this->publications->begin($this->generation($snapshot));
            // 搜尋索引先整批寫入新版本，等 HTML 產生成功後才清掉舊版本，確保搜尋結果與頁面一致。
            $this->search->replace($this->generation($snapshot), SearchDocument::project($snapshot));
            $this->renderer->render($snapshot);
            $this->search->prune($this->generation($snapshot));
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
        $now = $this->clock->now();
        $window = ScreeningWindow::current($now);
        $general = $this->general->read();
        // 欄位順序會影響 generation 雜湊，新增或調整欄位時要注意順序。
        $data = ['origin' => $this->config->origin(),
            'rendererVersion' => $this->renderer->version(),
            'policyVersion' => $general->values->consentVersion($this->config->termsVersion()),
            'indexModifiedAt' => [],
            'items' => array_map(
                static fn(Attraction $item): array => $item->publicArray(),
                $this->attractions->published($now),
            ),
            'aliases' => $this->attractions->aliases($now)];
        // 景點與電影共用同一個 generation；投影包含本週範圍，所以即使沒人編輯，換週時也會重新發布。
        $data['films'] = array_map(static fn(Film $film): array => $film->publicArray(), $this->films->published($now));
        $data['filmAliases'] = $this->films->aliases($now);
        $data['filmPeriod'] = $window->toArray();
        $posts = $this->news->published($now);
        $data['news'] = array_map(static fn(NewsPost $post): array => $post->publicArray(), $posts);
        $data['indexModifiedAt'] = $this->indexDates($posts);
        $data['newsAliases'] = $this->news->aliases($now);
        $today = OfferValidity::day($now);
        $data['offerDate'] = $today;
        $offers = $this->offers->published($now);
        $data['indexModifiedAt'] = [...$data['indexModifiedAt'], ...$this->indexDates($offers)];
        $data['offers'] = array_map(static function (Offer $offer) use ($today): array {
            $item = $offer->publicArray();
            $from = is_string($item['validFrom'] ?? null) ? $item['validFrom'] : null;
            $to = is_string($item['validTo'] ?? null) ? $item['validTo'] : null;
            $state = OfferValidity::state($from, $to, $today);
            return [...$item,'validity' => $state,'active' => $state === 'active'];
        }, $offers);
        $data['offerAliases'] = $this->offers->aliases($now);
        $data['recommendedRoutes'] = RecommendedRouteSelection::project($this->routes->published(), $data['items']);
        $data['detailNeighbors'] = NeighborSelection::project($data);
        $data['home'] = HomeSelection::fromSnapshot($data, $window)->toArray();
        $data['seo'] = $this->seo->project($data);
        $data['general'] = $general->toArray();
        $data['generation'] = hash('sha256', Json::encode($data));

        return $data;
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

    /** 只採用已發布記錄的實際修改時間；不為此新增公開 JSON API 欄位或額外查詢。
     * @param list<PublishedRecord> $records
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
