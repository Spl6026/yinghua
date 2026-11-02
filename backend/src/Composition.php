<?php

declare(strict_types=1);

namespace Yinghua;

use Closure;
use Yinghua\Adapters\CurlGoogleHttp;
use Yinghua\Composition\SharedServices;
use Yinghua\Adapters\GoogleOpeningHours;
use Yinghua\Adapters\GooglePlaceMapper;
use Yinghua\Adapters\GoogleSummaryProvider;
use Yinghua\Adapters\HtmlPurifierSanitizer;
use Yinghua\Adapters\LocalImageStorage;
use Yinghua\Adapters\NodeContentRenderer;
use Yinghua\Adapters\PdoDatabaseHealthProbe;
use Yinghua\Adapters\PdoTransactions;
use Yinghua\Adapters\PublicContentCommands;
use Yinghua\Adapters\PublicContentSeeder;
use Yinghua\Adapters\SeoCatalog;
use Yinghua\Adapters\SystemClock;
use Yinghua\Contracts\AdminContentRecords;
use Yinghua\Contracts\Clock;
use Yinghua\Contracts\ContentEditor;
use Yinghua\Controllers\AdminController;
use Yinghua\Controllers\ApiControllers;
use Yinghua\Controllers\AdminContentController;
use Yinghua\Controllers\AttractionContentController;
use Yinghua\Controllers\AttractionController;
use Yinghua\Controllers\AuthController;
use Yinghua\Controllers\CurrentItineraryController;
use Yinghua\Controllers\FavoriteController;
use Yinghua\Controllers\FilmController;
use Yinghua\Controllers\GeneralSettingsController;
use Yinghua\Controllers\HealthController;
use Yinghua\Controllers\MapController;
use Yinghua\Controllers\MediaController;
use Yinghua\Controllers\MemberDirectoryController;
use Yinghua\Controllers\MemberItineraryController;
use Yinghua\Controllers\OfferMerchantController;
use Yinghua\Controllers\OperationsController;
use Yinghua\Controllers\PermissionController;
use Yinghua\Controllers\PublicAttractionController;
use Yinghua\Controllers\PublicFilmController;
use Yinghua\Controllers\PublicHomeController;
use Yinghua\Controllers\PublicNeighborController;
use Yinghua\Controllers\PublicNewsController;
use Yinghua\Controllers\PublicOfferController;
use Yinghua\Controllers\PublicSiteSettingsController;
use Yinghua\Controllers\RecommendedRouteController;
use Yinghua\Controllers\SearchController;
use Yinghua\Controllers\SeoSettingsController;
use Yinghua\Http\Kernel;
use Yinghua\Http\Router;
use Yinghua\Policies\ContentCover;
use Yinghua\Policies\FilmPosterPolicy;
use Yinghua\Policies\GooglePlaceLinkPolicy;
use Yinghua\Policies\GoogleUrlPolicy;
use Yinghua\Policies\PermissionPolicy;
use Yinghua\Policies\ScreeningStartPolicy;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\ExternalRequestSlot;
use Yinghua\Repositories\FavoriteRepository;
use Yinghua\Repositories\FilmRepository;
use Yinghua\Repositories\FilmScreeningRepository;
use Yinghua\Repositories\GeneralSettingsRepository;
use Yinghua\Repositories\ItineraryRepository;
use Yinghua\Repositories\MediaRepository;
use Yinghua\Repositories\MemberDirectoryRepository;
use Yinghua\Repositories\MerchantRepository;
use Yinghua\Repositories\NewsRepository;
use Yinghua\Repositories\OfferRepository;
use Yinghua\Repositories\OperationsRepository;
use Yinghua\Repositories\PermissionRepository;
use Yinghua\Repositories\PublicationRepository;
use Yinghua\Repositories\RecommendedRouteRepository;
use Yinghua\Repositories\ResetRepository;
use Yinghua\Repositories\SearchRepository;
use Yinghua\Repositories\SeoOverrideRepository;
use Yinghua\Repositories\SeoSettingsRepository;
use Yinghua\Repositories\SeoTargetRepository;
use Yinghua\Services\AdminManagement;
use Yinghua\Services\AdminContentManagement;
use Yinghua\Services\AdminPreview;
use Yinghua\Services\ApplicationHealth;
use Yinghua\Services\AttractionManagement;
use Yinghua\Services\AttractionSummary;
use Yinghua\Services\Authorization;
use Yinghua\Services\ContentImporter;
use Yinghua\Services\ContentPublication;
use Yinghua\Services\CurrentItinerary;
use Yinghua\Services\FilmManagement;
use Yinghua\Services\GeneralSiteSettings;
use Yinghua\Services\MapResolver;
use Yinghua\Services\MediaAccess;
use Yinghua\Services\MediaManagement;
use Yinghua\Services\MemberAccess;
use Yinghua\Services\MemberAttractions;
use Yinghua\Services\MemberDirectory;
use Yinghua\Services\MemberFavorites;
use Yinghua\Services\MemberItineraries;
use Yinghua\Services\MerchantManagement;
use Yinghua\Services\NewsManagement;
use Yinghua\Services\OfferManagement;
use Yinghua\Services\OfferMerchants;
use Yinghua\Services\OperationsOverview;
use Yinghua\Services\PasswordReset;
use Yinghua\Services\PermissionManagement;
use Yinghua\Services\PublicAttractions;
use Yinghua\Services\PublicFilms;
use Yinghua\Services\PublicHome;
use Yinghua\Services\PublicationSnapshotBuilder;
use Yinghua\Services\PublicNeighbors;
use Yinghua\Services\PublicNews;
use Yinghua\Services\PublicOffers;
use Yinghua\Services\PublicRecommendedRoutes;
use Yinghua\Services\PublicSearch;
use Yinghua\Services\PublicSiteSettings;
use Yinghua\Services\RecommendedRoutes;
use Yinghua\Services\RoleTemplates;
use Yinghua\Services\SeoPublication;
use Yinghua\Services\SeoSettings;
use Yinghua\Support\Secrets;

/**
 * 整個應用程式唯一組裝物件的地方；業務類別只透過建構子取得依賴，不自行建立資料庫或 SMTP 連線。
 * 共用物件集中在 SharedServices，各區塊（帳號、景點與電影、消息優惠、會員、全站設定）
 * 各有一個工廠方法；新增模組時只需改動所屬區塊，不必在一個長函式裡找位置。
 */
readonly class Composition
{
    /** 入口檔用這個路徑判斷是否要在連線資料庫之前先處理健康檢查。 */
    public const string HEALTH_PATH = '/api/v1/health';

    public function __construct(private Config $config) {}

    /** 健康檢查要在資料庫掛掉時仍能回應，所以在建立任何資料庫連線之前單獨組裝。 */
    public function health(): HealthController
    {
        return new HealthController(new ApplicationHealth(
            $this->config,
            new PdoDatabaseHealthProbe($this->config, dirname(__DIR__) . '/database/migrations'),
        ));
    }

    public function kernel(): Kernel
    {
        $database = Database::forRequest($this->config);
        $shared = new SharedServices($this->config, $database, $this->publication($database));

        // 這裡只組裝物件；所有路由（含順序）都在 config/routes.php 一個地方宣告。
        $router = new Router();
        /** @var Closure(Router,ApiControllers):void $routes */
        $routes = require dirname(__DIR__) . '/config/routes.php';
        $routes($router, new ApiControllers(...[
            ...$this->accountControllers($shared),
            ...$this->placeControllers($shared),
            ...$this->editorialControllers($shared),
            ...$this->memberControllers($shared),
            ...$this->siteControllers($shared),
        ]));

        return new Kernel($router, $shared->csrf, $shared->authentication, $shared->authorization, $shared->secrets, $shared->events);
    }

    /**
     * 登入、密碼重設、管理者帳號與角色權限。
     *
     * @return array{auth:AuthController,admins:AdminController,permissions:PermissionController}
     */
    private function accountControllers(SharedServices $s): array
    {
        $roles = new RoleTemplates($s->permissions, $s->authorization);

        return [
            'auth' => new AuthController(
                $s->authentication,
                $s->authorization,
                $s->captcha,
                new PasswordReset(
                    $s->accounts,
                    new ResetRepository($s->database),
                    $s->sessions,
                    $s->passwords,
                    $s->limiter,
                    $s->events,
                    $s->transactions,
                    $s->clock,
                    $this->config,
                    $s->mailer,
                ),
                $s->csrf,
                $this->config,
                new AdminPreview($s->accounts, $s->sessions, $s->events, $s->transactions),
                $s->policies,
            ),
            'permissions' => new PermissionController(
                new PermissionManagement($s->accounts, $s->permissions, $s->authorization, $s->permissionPolicy, $s->transactions, $s->events),
                $roles,
            ),
            'admins' => new AdminController(new AdminManagement(
                $s->accounts,
                $s->permissions,
                $roles,
                $s->authorization,
                $s->permissionPolicy,
                $s->passwords,
                $s->transactions,
                $s->events,
                $s->sessions,
                new ResetRepository($s->database),
            )),
        ];
    }

    /**
     * 景點、電影、推薦路線、媒體與地圖：後台管理與對應的公開讀取。
     *
     * @return array{attractions:AttractionController,attractionContent:AttractionContentController,publicAttractions:PublicAttractionController,media:MediaController,maps:MapController,films:PublicFilmController,filmAdmin:FilmController,recommendedRoutes:RecommendedRouteController,neighbors:PublicNeighborController}
     */
    private function placeControllers(SharedServices $s): array
    {
        $publicAttractions = new PublicAttractions($s->publication);
        $mapPolicy = new GoogleUrlPolicy();
        $filmScreens = new FilmScreeningRepository($s->database);

        return [
            'attractions' => new AttractionController(new AttractionManagement(
                $s->attractionRecords,
                $s->authorization,
                $s->writer,
                $s->publication,
                new GooglePlaceLinkPolicy(),
            )),
            // 摘要補位預覽與即時取得的 Google 原文分開處理，不另存資料表。
            'attractionContent' => new AttractionContentController(new AttractionSummary(
                $publicAttractions,
                $s->attractionRecords,
                new GoogleSummaryProvider(new CurlGoogleHttp($mapPolicy), $this->config),
                $s->limiter,
                new ExternalRequestSlot($s->database),
            ), $s->authorization),
            'publicAttractions' => new PublicAttractionController($publicAttractions, $s->publication, $s->detailUrls),
            'media' => new MediaController(
                new MediaManagement(
                    $s->mediaRecords,
                    new LocalImageStorage($this->config),
                    $s->authorization,
                    $s->transactions,
                    $s->events,
                ),
                new MediaAccess($s->publication, $s->authentication, $s->authorization, $s->mediaRecords),
            ),
            'maps' => new MapController(new MapResolver(
                new CurlGoogleHttp($mapPolicy),
                $mapPolicy,
                $this->config,
                $s->authorization,
                $s->limiter,
                new GooglePlaceMapper(new GoogleOpeningHours()),
            )),
            'films' => new PublicFilmController(new PublicFilms($s->publication), $s->clock, $s->detailUrls),
            'filmAdmin' => new FilmController(new FilmManagement(
                new FilmRepository($s->database, $filmScreens),
                $s->mediaRecords,
                $s->authorization,
                $s->writer,
                $s->publication,
                new FilmPosterPolicy(),
                $filmScreens,
                new ScreeningStartPolicy(),
            )),
            'recommendedRoutes' => new RecommendedRouteController(
                new RecommendedRoutes($s->routeRecords, $s->authorization, $s->writer),
                new PublicRecommendedRoutes($s->publication),
            ),
            'neighbors' => new PublicNeighborController(new PublicNeighbors($s->publication)),
        ];
    }

    /**
     * 消息、優惠與合作店家；後台編輯沿用 CLI 匯入的同一組 editor。
     *
     * @return array{news:PublicNewsController,offers:PublicOfferController,newsAdmin:AdminContentController,offerAdmin:AdminContentController,merchants:OfferMerchantController}
     */
    private function editorialControllers(SharedServices $s): array
    {
        $editors = $this->contentEditors($s->database, $s->authorization, $s->events, $s->mediaRecords, $s->clock);
        // 消息與優惠的後台流程相同，只差模組名稱與刪除動作。
        $admin = fn(string $module, string $deleteAction, AdminContentRecords $records, ContentEditor $editor): AdminContentController => new AdminContentController(new AdminContentManagement(
            $module,
            $deleteAction,
            $records,
            $editor,
            $s->authorization,
            $s->transactions,
            $s->publication,
            $s->events,
            $s->clock,
        ));

        return [
            'news' => new PublicNewsController(new PublicNews($s->publication), $s->detailUrls),
            'offers' => new PublicOfferController(new PublicOffers($s->publication), $s->detailUrls),
            'newsAdmin' => $admin('news', 'news-delete', $editors['newsRecords'], $editors['news']),
            'offerAdmin' => $admin('offers', 'offer-delete', $editors['offerRecords'], $editors['offers']),
            'merchants' => new OfferMerchantController(new OfferMerchants(
                $editors['merchantRecords'],
                $editors['merchants'],
                $s->authorization,
                $s->transactions,
                $s->publication,
            )),
        ];
    }

    /**
     * 會員的收藏、目前行程與多份行程，以及後台會員名單。
     *
     * @return array{favorites:FavoriteController,currentItinerary:CurrentItineraryController,itineraries:MemberItineraryController,members:MemberDirectoryController}
     */
    private function memberControllers(SharedServices $s): array
    {
        $access = new MemberAccess($s->accounts);
        $attractions = new MemberAttractions($s->publication, $s->attractionRecords, $s->clock);
        $itineraries = new ItineraryRepository($s->database);

        return [
            'favorites' => new FavoriteController(new MemberFavorites(
                new FavoriteRepository($s->database),
                $access,
                $attractions,
                $s->transactions,
            )),
            'currentItinerary' => new CurrentItineraryController(new CurrentItinerary(
                $itineraries,
                $access,
                $attractions,
                $s->transactions,
            )),
            'itineraries' => new MemberItineraryController(new MemberItineraries(
                $itineraries,
                $access,
                $attractions,
                $s->transactions,
                $s->publication,
                $s->routeRecords,
            )),
            'members' => new MemberDirectoryController(new MemberDirectory(
                new MemberDirectoryRepository($s->database),
                $s->authorization,
            )),
        ];
    }

    /**
     * 全站設定、SEO、首頁、搜尋與營運總覽。
     *
     * @return array{home:PublicHomeController,seo:SeoSettingsController,siteSettings:PublicSiteSettingsController,search:SearchController,settings:GeneralSettingsController,operations:OperationsController}
     */
    private function siteControllers(SharedServices $s): array
    {
        $seoCatalog = new SeoCatalog($this->config->frontendSourcePath());

        return [
            'home' => new PublicHomeController(new PublicHome($s->publication)),
            'seo' => new SeoSettingsController(
                new SeoSettings(
                    $s->seoSettingsRecords,
                    new SeoOverrideRepository($s->database),
                    new SeoTargetRepository($s->database, $s->clock),
                    $s->authorization,
                    $s->transactions,
                    $s->events,
                    $s->publication,
                    $seoCatalog,
                    $this->config,
                ),
                $seoCatalog->policy(),
            ),
            'siteSettings' => new PublicSiteSettingsController(new PublicSiteSettings($s->publication)),
            'search' => new SearchController(new PublicSearch(
                $s->publication,
                new SearchRepository($s->database, $s->transactions),
            )),
            'settings' => new GeneralSettingsController(new GeneralSiteSettings(
                $s->generalRecords,
                $s->seoSettingsRecords,
                $s->authorization,
                $s->transactions,
                $s->events,
                $s->publication,
            )),
            'operations' => new OperationsController(new OperationsOverview(
                new OperationsRepository($s->database),
                $s->authorization,
            )),
        ];
    }

    public function publication(?Database $database = null): ContentPublication
    {
        $database ??= Database::connect($this->config);

        $transactions = new PdoTransactions($database);

        return new ContentPublication(
            new PublicationRepository($database),
            new NodeContentRenderer($this->config),
            new SearchRepository($database, $transactions),
            new PublicationSnapshotBuilder(
                $this->config,
                new SystemClock(),
                new AttractionRepository($database),
                new FilmRepository($database, new FilmScreeningRepository($database)),
                new NewsRepository($database),
                new OfferRepository($database),
                new RecommendedRouteRepository($database),
                new SeoPublication(new SeoSettingsRepository($database), new SeoOverrideRepository($database)),
                new GeneralSettingsRepository($database),
            ),
        );
    }

    public function publicContentCommands(Database $database): PublicContentCommands
    {
        $secrets = new Secrets($this->config);
        $clock = new SystemClock();
        $transactions = new PdoTransactions($database);
        $publication = $this->publication($database);
        $accounts = new AccountRepository($database);
        $events = new EventRepository($database, $secrets);
        $authorization = new Authorization($accounts, new PermissionRepository($database), new PermissionPolicy());
        $media = new MediaRepository($database);
        $editors = $this->contentEditors($database, $authorization, $events, $media, $clock);
        return new PublicContentCommands(
            $accounts,
            new ContentImporter($transactions, $publication),
            $editors['news'],
            new MediaManagement($media, new LocalImageStorage($this->config), $authorization, $transactions, $events),
            $secrets,
            $editors['offers'],
            $editors['merchants'],
            new PublicContentSeeder(
                $editors['merchantRecords'],
                $editors['newsRecords'],
                $editors['offerRecords'],
                $editors['merchants'],
                $editors['news'],
                $editors['offers'],
                $transactions,
                $publication,
            ),
        );
    }

    /**
     * 消息、優惠與店家的編輯服務；後台 API 與 CLI 匯入都從這裡取得，兩邊的驗證與寫入規則不會分歧。
     *
     * @return array{news:NewsManagement,offers:OfferManagement,merchants:MerchantManagement,newsRecords:NewsRepository,offerRecords:OfferRepository,merchantRecords:MerchantRepository}
     */
    private function contentEditors(Database $database, Authorization $authorization, EventRepository $events, MediaRepository $media, Clock $clock): array
    {
        $cover = new ContentCover();
        $newsRecords = new NewsRepository($database);
        $offerRecords = new OfferRepository($database);
        $merchantRecords = new MerchantRepository($database);

        return [
            'news' => new NewsManagement($newsRecords, $media, $authorization, $events, new HtmlPurifierSanitizer(), $cover, $clock),
            'offers' => new OfferManagement($offerRecords, $merchantRecords, $media, $authorization, $events, $cover, $clock),
            'merchants' => new MerchantManagement($merchantRecords, $authorization, $events),
            'newsRecords' => $newsRecords,
            'offerRecords' => $offerRecords,
            'merchantRecords' => $merchantRecords,
        ];
    }
}
