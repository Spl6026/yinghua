<?php

declare(strict_types=1);

namespace Yinghua;

use Closure;
use Yinghua\Adapters\CurlGoogleHttp;
use Yinghua\Adapters\GoogleOpeningHours;
use Yinghua\Adapters\GooglePlaceMapper;
use Yinghua\Adapters\GoogleSummaryProvider;
use Yinghua\Adapters\HtmlPurifierSanitizer;
use Yinghua\Adapters\LocalImageStorage;
use Yinghua\Adapters\NodeContentRenderer;
use Yinghua\Adapters\PdoDatabaseHealthProbe;
use Yinghua\Adapters\PdoTransactions;
use Yinghua\Adapters\PhpMailerAdapter;
use Yinghua\Adapters\PublicContentCommands;
use Yinghua\Adapters\PublicContentSeeder;
use Yinghua\Adapters\SeoCatalog;
use Yinghua\Adapters\SystemClock;
use Yinghua\Contracts\Clock;
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
use Yinghua\Http\Csrf;
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
use Yinghua\Repositories\ChallengeRepository;
use Yinghua\Repositories\ConsentRepository;
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
use Yinghua\Repositories\RateLimitRepository;
use Yinghua\Repositories\RecommendedRouteRepository;
use Yinghua\Repositories\ResetRepository;
use Yinghua\Repositories\SearchRepository;
use Yinghua\Repositories\SeoOverrideRepository;
use Yinghua\Repositories\SeoSettingsRepository;
use Yinghua\Repositories\SeoTargetRepository;
use Yinghua\Repositories\SessionRepository;
use Yinghua\Services\AdminManagement;
use Yinghua\Services\AdminContentManagement;
use Yinghua\Services\AdminPreview;
use Yinghua\Services\ApplicationHealth;
use Yinghua\Services\AttractionManagement;
use Yinghua\Services\AttractionSummary;
use Yinghua\Services\Authentication;
use Yinghua\Services\Authorization;
use Yinghua\Services\Captcha;
use Yinghua\Services\ContentImporter;
use Yinghua\Services\ContentPublication;
use Yinghua\Services\CurrentItinerary;
use Yinghua\Services\FilmManagement;
use Yinghua\Services\GeneralSiteSettings;
use Yinghua\Services\DetailUrlResolver;
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
use Yinghua\Services\PasswordHasher;
use Yinghua\Services\PasswordReset;
use Yinghua\Services\PermissionManagement;
use Yinghua\Services\PublicAttractions;
use Yinghua\Services\PublicFilms;
use Yinghua\Services\PublicHome;
use Yinghua\Services\PublicNeighbors;
use Yinghua\Services\PublicNews;
use Yinghua\Services\PublicOffers;
use Yinghua\Services\PublicRecommendedRoutes;
use Yinghua\Services\PublicSearch;
use Yinghua\Services\PublicSiteSettings;
use Yinghua\Services\PublishedPolicies;
use Yinghua\Services\RateLimiter;
use Yinghua\Services\RecommendedRoutes;
use Yinghua\Services\RoleTemplates;
use Yinghua\Services\SeoPublication;
use Yinghua\Services\SeoSettings;
use Yinghua\Support\Secrets;

/** 整個應用程式唯一組裝物件的地方；業務類別只透過建構子取得依賴，不自行建立資料庫或 SMTP 連線。 */
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
        $clock = new SystemClock();
        $secrets = new Secrets($this->config);
        $transactions = new PdoTransactions($database);
        // 以下 Repository 只包裝同一個資料庫連線、不保存請求狀態，因此整個請求共用同一個實例。
        $accounts = new AccountRepository($database);
        $sessions = new SessionRepository($database, $this->config, $clock);
        $permissions = new PermissionRepository($database);
        $events = new EventRepository($database, $secrets);
        $resets = new ResetRepository($database);
        $mediaRecords = new MediaRepository($database);
        $attractionRecords = new AttractionRepository($database);
        $routeRecords = new RecommendedRouteRepository($database);
        $itineraryRecords = new ItineraryRepository($database);
        $seoSettingsRecords = new SeoSettingsRepository($database);
        $generalRecords = new GeneralSettingsRepository($database);
        $limiter = new RateLimiter(new RateLimitRepository($database), $secrets, $clock);
        $passwords = new PasswordHasher();
        $policy = new PermissionPolicy();
        $authorization = new Authorization($accounts, $permissions, $policy);
        $mailer = new PhpMailerAdapter($this->config);
        $captcha = new Captcha(new ChallengeRepository($database), $transactions, $limiter, $secrets, $clock, $this->config, $mailer);
        $policies = new PublishedPolicies($generalRecords, new NodeContentRenderer($this->config), $this->config);
        $authentication = new Authentication(
            $accounts,
            $sessions,
            $passwords,
            $limiter,
            $events,
            $transactions,
            $captcha,
            new ConsentRepository($database),
            $policies,
        );
        $passwordReset = new PasswordReset(
            $accounts,
            $resets,
            $sessions,
            $passwords,
            $limiter,
            $events,
            $transactions,
            $clock,
            $this->config,
            $mailer,
        );
        $csrf = new Csrf($this->config, $clock, $secrets);
        $authController = new AuthController(
            $authentication,
            $authorization,
            $captcha,
            $passwordReset,
            $csrf,
            $this->config,
            new AdminPreview($accounts, $sessions, $events, $transactions),
            $policies,
        );
        $roles = new RoleTemplates($permissions, $authorization);
        $permissionController = new PermissionController(
            new PermissionManagement($accounts, $permissions, $authorization, $policy, $transactions, $events),
            $roles,
        );
        $adminController = new AdminController(new AdminManagement(
            $accounts,
            $permissions,
            $roles,
            $authorization,
            $policy,
            $passwords,
            $transactions,
            $events,
            $sessions,
            $resets,
        ));
        $publication = $this->publication($database);
        $attractionController = new AttractionController(new AttractionManagement(
            $attractionRecords,
            $authorization,
            $transactions,
            $events,
            $publication,
            new GooglePlaceLinkPolicy(),
        ));
        $publicAttractionService = new PublicAttractions($publication);
        $detailUrls = new DetailUrlResolver($publication);
        $mediaController = new MediaController(
            new MediaManagement(
                $mediaRecords,
                new LocalImageStorage($this->config),
                $authorization,
                $transactions,
                $events,
            ),
            new MediaAccess($publication, $authentication, $authorization, $mediaRecords),
        );
        $mapPolicy = new GoogleUrlPolicy();
        $mapController = new MapController(new MapResolver(
            new CurlGoogleHttp($mapPolicy),
            $mapPolicy,
            $this->config,
            $authorization,
            $limiter,
            new GooglePlaceMapper(new GoogleOpeningHours()),
        ));
        $filmScreens = new FilmScreeningRepository($database);
        $filmAdmin = new FilmController(new FilmManagement(
            new FilmRepository($database, $filmScreens),
            $mediaRecords,
            $authorization,
            $transactions,
            $events,
            $publication,
            new FilmPosterPolicy(),
            $filmScreens,
            new ScreeningStartPolicy(),
        ));
        // 摘要補位預覽與即時取得的 Google 原文分開處理，不另存資料表。
        $content = new AttractionContentController(new AttractionSummary(
            $publicAttractionService,
            $attractionRecords,
            new GoogleSummaryProvider(new CurlGoogleHttp($mapPolicy), $this->config),
            $limiter,
            new ExternalRequestSlot($database),
        ), $authorization);

        // 消息、優惠與店家：HTTP 與 CLI 共用同一組 editor；消息與優惠的後台流程相同，只差模組名稱。
        $editors = $this->contentEditors($database, $authorization, $events, $mediaRecords, $clock);
        $contentAdmin = fn(string $module, string $deleteAction, NewsRepository|OfferRepository $records, NewsManagement|OfferManagement $editor): AdminContentController => new AdminContentController(new AdminContentManagement(
            $module,
            $deleteAction,
            $records,
            $editor,
            $authorization,
            $transactions,
            $publication,
            $events,
            $clock,
        ));
        $newsAdmin = $contentAdmin('news', 'news-delete', $editors['newsRecords'], $editors['news']);
        $offerAdmin = $contentAdmin('offers', 'offer-delete', $editors['offerRecords'], $editors['offers']);
        $merchantRecords = $editors['merchantRecords'];
        $merchantEditor = $editors['merchants'];
        $merchants = new OfferMerchantController(new OfferMerchants(
            $merchantRecords,
            $merchantEditor,
            $authorization,
            $transactions,
            $publication,
        ));

        $recommendedRoutes = new RecommendedRouteController(
            new RecommendedRoutes(
                $routeRecords,
                $authorization,
                $transactions,
                $publication,
                $events,
            ),
            new PublicRecommendedRoutes($publication),
        );

        $memberAccess = new MemberAccess($accounts);
        $memberAttractions = new MemberAttractions($publication, $attractionRecords, $clock);
        $favoriteController = new FavoriteController(new MemberFavorites(
            new FavoriteRepository($database),
            $memberAccess,
            $memberAttractions,
            $transactions,
        ));
        $itineraryController = new CurrentItineraryController(new CurrentItinerary(
            $itineraryRecords,
            $memberAccess,
            $memberAttractions,
            $transactions,
        ));
        $trips = new MemberItineraryController(new MemberItineraries(
            $itineraryRecords,
            $memberAccess,
            $memberAttractions,
            $transactions,
            $publication,
            $routeRecords,
        ));

        $seoCatalog = new SeoCatalog($this->config->frontendSourcePath());
        $seoController = new SeoSettingsController(
            new SeoSettings(
                $seoSettingsRecords,
                new SeoOverrideRepository($database),
                new SeoTargetRepository($database),
                $authorization,
                $transactions,
                $events,
                $publication,
                $seoCatalog,
                $this->config,
            ),
            $seoCatalog->policy(),
        );

        $search = new SearchController(new PublicSearch(
            $publication,
            new SearchRepository($database, $transactions),
        ));
        $directory = new MemberDirectoryController(new MemberDirectory(
            new MemberDirectoryRepository($database),
            $authorization,
        ));
        $general = new GeneralSettingsController(new GeneralSiteSettings(
            $generalRecords,
            $seoSettingsRecords,
            $authorization,
            $transactions,
            $events,
            $publication,
        ));
        $operations = new OperationsController(new OperationsOverview(
            new OperationsRepository($database),
            $authorization,
        ));

        // 這裡只組裝物件；所有路由（含順序）都在 config/routes.php 一個地方宣告。
        $router = new Router();
        /** @var Closure(Router,ApiControllers):void $routes */
        $routes = require dirname(__DIR__) . '/config/routes.php';
        $routes($router, new ApiControllers(
            auth: $authController,
            admins: $adminController,
            permissions: $permissionController,
            attractions: $attractionController,
            attractionContent: $content,
            publicAttractions: new PublicAttractionController($publicAttractionService, $publication, $detailUrls),
            media: $mediaController,
            maps: $mapController,
            films: new PublicFilmController(new PublicFilms($publication), $clock, $detailUrls),
            filmAdmin: $filmAdmin,
            news: new PublicNewsController(new PublicNews($publication), $detailUrls),
            offers: new PublicOfferController(new PublicOffers($publication), $detailUrls),
            home: new PublicHomeController(new PublicHome($publication)),
            newsAdmin: $newsAdmin,
            offerAdmin: $offerAdmin,
            merchants: $merchants,
            recommendedRoutes: $recommendedRoutes,
            neighbors: new PublicNeighborController(new PublicNeighbors($publication)),
            favorites: $favoriteController,
            currentItinerary: $itineraryController,
            itineraries: $trips,
            seo: $seoController,
            siteSettings: new PublicSiteSettingsController(new PublicSiteSettings($publication)),
            search: $search,
            members: $directory,
            settings: $general,
            operations: $operations,
        ));

        return new Kernel($router, $csrf, $authentication, $authorization, $secrets, $events);
    }

    public function publication(?Database $database = null): ContentPublication
    {
        $database ??= Database::connect($this->config);

        return new ContentPublication(
            new AttractionRepository($database),
            new PublicationRepository($database),
            new NodeContentRenderer($this->config),
            $this->config,
            new FilmRepository($database, new FilmScreeningRepository($database)),
            new SystemClock(),
            new NewsRepository($database),
            new OfferRepository($database),
            new SeoPublication(
                new SeoSettingsRepository($database),
                new SeoOverrideRepository($database),
            ),
            new RecommendedRouteRepository($database),
            new SearchRepository($database, new PdoTransactions($database)),
            new GeneralSettingsRepository($database),
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
