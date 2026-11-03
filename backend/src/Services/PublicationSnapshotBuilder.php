<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\DTO\Attraction;
use Yinghua\DTO\Film;
use Yinghua\DTO\NewsPost;
use Yinghua\DTO\Offer;
use Yinghua\DTO\PublishedRecord;
use Yinghua\DTO\ScreeningWindow;
use Yinghua\Policies\HomeSelection;
use Yinghua\Policies\NeighborSelection;
use Yinghua\Policies\OfferValidity;
use Yinghua\Policies\RecommendedRouteSelection;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\FilmRepository;
use Yinghua\Repositories\GeneralSettingsRepository;
use Yinghua\Repositories\NewsRepository;
use Yinghua\Repositories\OfferRepository;
use Yinghua\Repositories\RecommendedRouteRepository;
use Yinghua\Support\Json;

/**
 * 從編輯資料組出一份公開快照（Builder）：讀取所有已發布內容、套用公開規則，最後算出 generation。
 * ContentPublication 只管何時發布、鎖定與寫入結果，不必知道快照由哪些資料組成。
 */
class PublicationSnapshotBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly Clock $clock,
        private readonly AttractionRepository $attractions,
        private readonly FilmRepository $films,
        private readonly NewsRepository $news,
        private readonly OfferRepository $offers,
        private readonly RecommendedRouteRepository $routes,
        private readonly SeoPublication $seo,
        private readonly GeneralSettingsRepository $general,
    ) {}

    /**
     * @param string $rendererVersion 產生頁面的程式版本；版本改變時即使內容沒變也要重新發布
     * @return array<string,mixed>
     */
    public function build(string $rendererVersion): array
    {
        $now = $this->clock->now();
        $window = ScreeningWindow::current($now);
        $general = $this->general->read();
        // 欄位順序會影響 generation 雜湊，新增或調整欄位時要注意順序。
        $data = ['origin' => $this->config->origin(),
            'rendererVersion' => $rendererVersion,
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
