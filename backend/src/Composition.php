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
use Yinghua\Controllers\AdminController;
use Yinghua\Controllers\AdminNewsController;
use Yinghua\Controllers\AdminOfferController;
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
use Yinghua\Http\Route;
use Yinghua\Http\Router;
use Yinghua\Models\IdentityKind;
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
use Yinghua\Services\AdminNewsManagement;
use Yinghua\Services\AdminOfferManagement;
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
        $database = Database::connect($this->config);
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
        $publicAttractions = new PublicAttractionController($publicAttractionService, $publication);
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
        $filmController = new PublicFilmController(new PublicFilms($publication), $clock);
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
        $router = new Router();
        // 摘要補位預覽與即時取得的 Google 原文分開處理，不另存資料表。
        $content = new AttractionContentController(new AttractionSummary(
            $publicAttractionService,
            $attractionRecords,
            new GoogleSummaryProvider(new CurlGoogleHttp($mapPolicy), $this->config),
            $limiter,
            new ExternalRequestSlot($database),
        ), $authorization);
        $router->add(new Route(
            'POST',
            '/api/v1/admin/attractions/content-preview',
            IdentityKind::Admin,
            $content->preview(...),
            true,
        ));
        $router->add(new Route(
            'GET',
            '/api/v1/attractions/{slug}/google-summary',
            IdentityKind::Member,
            $content->summary(...),
        ));
        /** @var Closure(Router,AuthController,AdminController,PermissionController,AttractionController,PublicAttractionController,MediaController,MapController,PublicFilmController,FilmController,PublicNewsController,PublicOfferController,PublicHomeController):void $routes */
        $routes = require dirname(__DIR__) . '/config/routes.php';
        $routes(
            $router,
            $authController,
            $adminController,
            $permissionController,
            $attractionController,
            $publicAttractions,
            $mediaController,
            $mapController,
            $filmController,
            $filmAdmin,
            new PublicNewsController(new PublicNews($publication)),
            new PublicOfferController(new PublicOffers($publication)),
            new PublicHomeController(new PublicHome($publication)),
        );

        // 消息管理：HTTP 與 CLI 共用同一個 editor。
        $contentCover = new ContentCover();
        $newsRecords = new NewsRepository($database);
        $newsEditor = new NewsManagement(
            $newsRecords,
            $mediaRecords,
            $authorization,
            $events,
            new HtmlPurifierSanitizer(),
            $contentCover,
            $clock,
        );
        /** @var Closure(Router,AdminNewsController):void $newsRoutes */
        $newsRoutes = require dirname(__DIR__) . '/config/news-admin-routes.php';
        $newsRoutes(
            $router,
            new AdminNewsController(new AdminNewsManagement(
                $newsRecords,
                $newsEditor,
                $authorization,
                $transactions,
                $publication,
                $events,
                $clock,
            )),
        );

        // 優惠與店家分開管理；路由檔會先註冊靜態的 merchants 路徑，避免被 {id} 吃掉。
        $offerRecords = new OfferRepository($database);
        $merchantRecords = new MerchantRepository($database);
        $offerEditor = new OfferManagement(
            $offerRecords,
            $merchantRecords,
            $mediaRecords,
            $authorization,
            $events,
            $contentCover,
            $clock,
        );
        $merchantEditor = new MerchantManagement($merchantRecords, $authorization, $events);
        /** @var Closure(Router,AdminOfferController,OfferMerchantController):void $offerRoutes */
        $offerRoutes = require dirname(__DIR__) . '/config/offer-admin-routes.php';
        $offerRoutes(
            $router,
            new AdminOfferController(new AdminOfferManagement(
                $offerRecords,
                $offerEditor,
                $authorization,
                $transactions,
                $publication,
                $events,
                $clock,
            )),
            new OfferMerchantController(new OfferMerchants(
                $merchantRecords,
                $merchantEditor,
                $authorization,
                $transactions,
                $publication,
            )),
        );

        /** @var Closure(Router,RecommendedRouteController):void $recommendedRoutes */
        $recommendedRoutes = require dirname(__DIR__) . '/config/recommended-routes.php';
        $recommendedRoutes(
            $router,
            new RecommendedRouteController(
                new RecommendedRoutes(
                    $routeRecords,
                    $authorization,
                    $transactions,
                    $publication,
                    $events,
                ),
                new PublicRecommendedRoutes($publication),
            ),
        );

        $neighborController = new PublicNeighborController(new PublicNeighbors($publication));
        foreach (['attractions','films','news','offers'] as $topic) {
            $router->add(new Route(
                'GET',
                '/api/v1/' . $topic . '/{slug}/neighbors',
                IdentityKind::Member,
                $neighborController->{$topic}(...),
            ));
        }

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
        /** @var Closure(Router,FavoriteController,CurrentItineraryController):void $favoriteRoutes */
        $favoriteRoutes = require dirname(__DIR__) . '/config/favorite-routes.php';
        $favoriteRoutes($router, $favoriteController, $itineraryController);

        /** @var Closure(Router,MemberItineraryController):void $itineraryRoutes */
        $itineraryRoutes = require dirname(__DIR__) . '/config/itinerary-routes.php';
        $itineraryRoutes(
            $router,
            new MemberItineraryController(new MemberItineraries(
                $itineraryRecords,
                $memberAccess,
                $memberAttractions,
                $transactions,
                $publication,
                $routeRecords,
            )),
        );

        $seoCatalog = new SeoCatalog($this->config->string('FRONTEND_SOURCE_PATH', '/var/www/frontend-source'));
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
        /** @var Closure(Router,SeoSettingsController,PublicSiteSettingsController):void $seoRoutes */
        $seoRoutes = require dirname(__DIR__) . '/config/seo-routes.php';
        $seoRoutes(
            $router,
            $seoController,
            new PublicSiteSettingsController(new PublicSiteSettings($publication)),
        );

        $search = new SearchController(new PublicSearch(
            $publication,
            new SearchRepository($database, $transactions),
        ));
        $router->add(new Route('GET', '/api/v1/search', IdentityKind::Member, $search->listing(...)));
        $directory = new MemberDirectoryController(new MemberDirectory(
            new MemberDirectoryRepository($database),
            $authorization,
        ));
        $router->add(new Route(
            'GET',
            '/api/v1/admin/members',
            IdentityKind::Admin,
            $directory->listing(...),
            true,
            'members',
            'view',
        ));
        $general = new GeneralSettingsController(new GeneralSiteSettings(
            $generalRecords,
            $seoSettingsRecords,
            $authorization,
            $transactions,
            $events,
            $publication,
        ));
        $router->add(new Route(
            'GET',
            '/api/v1/admin/settings',
            IdentityKind::Admin,
            $general->read(...),
            true,
            'settings',
            'view',
        ));
        $router->add(new Route(
            'PUT',
            '/api/v1/admin/settings',
            IdentityKind::Admin,
            $general->save(...),
            true,
            'settings',
            'edit',
        ));
        $operations = new OperationsController(new OperationsOverview(
            new OperationsRepository($database),
            $authorization,
        ));
        $router->add(new Route(
            'GET',
            '/api/v1/admin/dashboard',
            IdentityKind::Admin,
            $operations->read(...),
            true,
            'dashboard',
            'view',
        ));
        $router->add(new Route(
            'GET',
            '/api/v1/admin/audit-logs',
            IdentityKind::Admin,
            $operations->audits(...),
            true,
            'dashboard',
            'view',
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
        $contentCover = new ContentCover();
        $newsRecords = new NewsRepository($database);
        $offerRecords = new OfferRepository($database);
        $news = new NewsManagement(
            $newsRecords,
            $media,
            $authorization,
            $events,
            new HtmlPurifierSanitizer(),
            $contentCover,
            $clock,
        );
        $merchants = new MerchantRepository($database);
        $offers = new OfferManagement(
            $offerRecords,
            $merchants,
            $media,
            $authorization,
            $events,
            $contentCover,
            $clock,
        );
        $merchantService = new MerchantManagement($merchants, $authorization, $events);
        return new PublicContentCommands(
            $accounts,
            new ContentImporter($transactions, $publication),
            $news,
            new MediaManagement($media, new LocalImageStorage($this->config), $authorization, $transactions, $events),
            $secrets,
            $offers,
            $merchantService,
            new PublicContentSeeder(
                $merchants,
                $newsRecords,
                $offerRecords,
                $merchantService,
                $news,
                $offers,
                $transactions,
                $publication,
            ),
        );
    }
}
