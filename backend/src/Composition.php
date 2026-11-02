<?php

declare(strict_types=1);

namespace Yinghua;

use Closure;
use Yinghua\Adapters\PdoTransactions;
use Yinghua\Adapters\PhpMailerAdapter;
use Yinghua\Adapters\SystemClock;
use Yinghua\Controllers\AdminController;
use Yinghua\Controllers\AuthController;
use Yinghua\Controllers\PermissionController;
use Yinghua\Http\Csrf;
use Yinghua\Http\Kernel;
use Yinghua\Http\Router;
use Yinghua\Policies\PermissionPolicy;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\ChallengeRepository;
use Yinghua\Repositories\ConsentRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\PermissionRepository;
use Yinghua\Repositories\RateLimitRepository;
use Yinghua\Repositories\ResetRepository;
use Yinghua\Repositories\SessionRepository;
use Yinghua\Services\AdminManagement;
use Yinghua\Services\PermissionManagement;
use Yinghua\Services\RoleTemplates;
use Yinghua\Services\AdminPreview;
use Yinghua\Services\Authentication;
use Yinghua\Services\Authorization;
use Yinghua\Services\Captcha;
use Yinghua\Services\PasswordHasher;
use Yinghua\Services\PasswordReset;
use Yinghua\Services\RateLimiter;
use Yinghua\Support\Secrets;
use Yinghua\Adapters\NodeContentRenderer;
use Yinghua\Adapters\LocalImageStorage;
use Yinghua\Controllers\AttractionController;
use Yinghua\Controllers\PublicAttractionController;
use Yinghua\Controllers\MediaController;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\PublicationRepository;
use Yinghua\Repositories\MediaRepository;
use Yinghua\Services\AttractionManagement;
use Yinghua\Services\ContentPublication;
use Yinghua\Services\PublicAttractions;
use Yinghua\Services\MediaManagement;
use Yinghua\Services\MediaAccess;
use Yinghua\Adapters\CurlGoogleHttp;
use Yinghua\Controllers\MapController;
use Yinghua\Policies\GoogleUrlPolicy;
use Yinghua\Services\MapResolver;

/** 整個應用程式唯一組裝物件的地方；業務類別只透過建構子取得依賴，不自行建立資料庫或 SMTP 連線。 */
readonly class Composition
{
    /** 入口檔用這個路徑判斷是否要在連線資料庫之前先處理健康檢查。 */
    public const string HEALTH_PATH = '/api/v1/health';

    public function __construct(private Config $config) {}

    /** 健康檢查要在資料庫掛掉時仍能回應，所以在建立任何資料庫連線之前單獨組裝。 */
    public function health(): \Yinghua\Controllers\HealthController
    {
        return new \Yinghua\Controllers\HealthController(new \Yinghua\Services\ApplicationHealth(
            $this->config,
            new \Yinghua\Adapters\PdoDatabaseHealthProbe($this->config, dirname(__DIR__) . '/database/migrations'),
        ));
    }

    public function kernel(): Kernel
    {
        $database = Database::connect($this->config);
        $clock = new SystemClock();
        $secrets = new Secrets($this->config);
        $transactions = new PdoTransactions($database);
        $accounts = new AccountRepository($database);
        $sessions = new SessionRepository($database, $this->config, $clock);
        $permissions = new PermissionRepository($database);
        $events = new EventRepository($database, $secrets);
        $resets = new ResetRepository($database);
        $limiter = new RateLimiter(new RateLimitRepository($database), $secrets, $clock);
        $passwords = new PasswordHasher();
        $policy = new PermissionPolicy();
        $authorization = new Authorization($accounts, $permissions, $policy);
        $mailer = new PhpMailerAdapter($this->config);
        $captcha = new Captcha(new ChallengeRepository($database), $transactions, $limiter, $secrets, $clock, $this->config, $mailer);
        $policies = new \Yinghua\Services\PublishedPolicies(
            new \Yinghua\Repositories\GeneralSettingsRepository($database),
            new NodeContentRenderer($this->config),
            $this->config,
        );
        $authentication = new Authentication(
            $accounts,
            $sessions,
            $passwords,
            $limiter,
            $events,
            $transactions,
            $captcha,
            $this->config,
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
            new AttractionRepository($database),
            $authorization,
            $transactions,
            $events,
            $publication,
            new \Yinghua\Policies\GooglePlaceLinkPolicy(),
        ));
        $publicAttractions = new PublicAttractionController(new PublicAttractions($publication), $publication);
        $mediaController = new MediaController(
            new MediaManagement(
                new MediaRepository($database),
                new LocalImageStorage($this->config),
                $authorization,
                $transactions,
                $events,
            ),
            new MediaAccess($publication, $authentication, $authorization, new MediaRepository($database)),
        );
        $mapPolicy = new GoogleUrlPolicy();
        $mapController = new MapController(new MapResolver(
            new CurlGoogleHttp($mapPolicy),
            $mapPolicy,
            $this->config,
            $authorization,
            $limiter,
            new \Yinghua\Adapters\GooglePlaceMapper(new \Yinghua\Adapters\GoogleOpeningHours()),
        ));
        $filmController = new \Yinghua\Controllers\PublicFilmController(new \Yinghua\Services\PublicFilms($publication), $clock);
        $filmScreens = new \Yinghua\Repositories\FilmScreeningRepository($database);
        $filmAdmin = new \Yinghua\Controllers\FilmController(new \Yinghua\Services\FilmManagement(
            new \Yinghua\Repositories\FilmRepository($database, $filmScreens),
            new MediaRepository($database),
            $authorization,
            $transactions,
            $events,
            $publication,
            new \Yinghua\Policies\FilmPosterPolicy(),
            $filmScreens,
            new \Yinghua\Policies\ScreeningStartPolicy(),
        ));
        $router = new Router();
        // 摘要補位預覽與即時取得的 Google 原文分開處理，不另存資料表。
        $content = new \Yinghua\Controllers\AttractionContentController(new \Yinghua\Services\AttractionSummary(
            new PublicAttractions($publication),
            new AttractionRepository($database),
            new \Yinghua\Adapters\GoogleSummaryProvider(new CurlGoogleHttp($mapPolicy), $this->config),
            $limiter,
            new \Yinghua\Repositories\ExternalRequestSlot($database),
        ), $authorization);
        $router->add(new \Yinghua\Http\Route(
            'POST',
            '/api/v1/admin/attractions/content-preview',
            \Yinghua\Models\IdentityKind::Admin,
            $content->preview(...),
            true,
        ));
        $router->add(new \Yinghua\Http\Route(
            'GET',
            '/api/v1/attractions/{slug}/google-summary',
            \Yinghua\Models\IdentityKind::Member,
            $content->summary(...),
        ));
        /** @var Closure(Router,AuthController,AdminController,PermissionController,AttractionController,PublicAttractionController,MediaController,MapController,\Yinghua\Controllers\PublicFilmController,\Yinghua\Controllers\FilmController,\Yinghua\Controllers\PublicNewsController,\Yinghua\Controllers\PublicOfferController,\Yinghua\Controllers\PublicHomeController):void $routes */
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
            new \Yinghua\Controllers\PublicNewsController(new \Yinghua\Services\PublicNews($publication)),
            new \Yinghua\Controllers\PublicOfferController(new \Yinghua\Services\PublicOffers($publication)),
            new \Yinghua\Controllers\PublicHomeController(new \Yinghua\Services\PublicHome($publication)),
        );

        // 消息管理：HTTP 與 CLI 共用同一個 editor。
        $contentMedia = new MediaRepository($database);
        $contentCover = new \Yinghua\Policies\ContentCover();
        $newsRecords = new \Yinghua\Repositories\NewsRepository($database);
        $newsEditor = new \Yinghua\Services\NewsManagement(
            $newsRecords,
            $contentMedia,
            $authorization,
            $events,
            new \Yinghua\Adapters\HtmlPurifierSanitizer(),
            $contentCover,
            $clock,
        );
        /** @var Closure(Router,\Yinghua\Controllers\AdminNewsController):void $newsRoutes */
        $newsRoutes = require dirname(__DIR__) . '/config/news-admin-routes.php';
        $newsRoutes(
            $router,
            new \Yinghua\Controllers\AdminNewsController(new \Yinghua\Services\AdminNewsManagement(
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
        $offerRecords = new \Yinghua\Repositories\OfferRepository($database);
        $merchantRecords = new \Yinghua\Repositories\MerchantRepository($database);
        $offerEditor = new \Yinghua\Services\OfferManagement(
            $offerRecords,
            $merchantRecords,
            $contentMedia,
            $authorization,
            $events,
            $contentCover,
            $clock,
        );
        $merchantEditor = new \Yinghua\Services\MerchantManagement($merchantRecords, $authorization, $events);
        /** @var Closure(Router,\Yinghua\Controllers\AdminOfferController,\Yinghua\Controllers\OfferMerchantController):void $offerRoutes */
        $offerRoutes = require dirname(__DIR__) . '/config/offer-admin-routes.php';
        $offerRoutes(
            $router,
            new \Yinghua\Controllers\AdminOfferController(new \Yinghua\Services\AdminOfferManagement(
                $offerRecords,
                $offerEditor,
                $authorization,
                $transactions,
                $publication,
                $events,
                $clock,
            )),
            new \Yinghua\Controllers\OfferMerchantController(new \Yinghua\Services\OfferMerchants(
                $merchantRecords,
                $merchantEditor,
                $authorization,
                $transactions,
                $publication,
            )),
        );

        /** @var Closure(Router,\Yinghua\Controllers\RecommendedRouteController):void $recommendedRoutes */
        $recommendedRoutes = require dirname(__DIR__) . '/config/recommended-route-routes.php';
        $recommendedRoutes(
            $router,
            new \Yinghua\Controllers\RecommendedRouteController(
                new \Yinghua\Services\RecommendedRoutes(
                    new \Yinghua\Repositories\RecommendedRouteRepository($database),
                    $authorization,
                    $transactions,
                    $publication,
                    $events,
                ),
                new \Yinghua\Services\PublicRecommendedRoutes($publication),
            ),
        );

        $neighborController = new \Yinghua\Controllers\PublicNeighborController(new \Yinghua\Services\PublicNeighbors($publication));
        foreach (['attractions','films','news','offers'] as $topic) {
            $router->add(new \Yinghua\Http\Route(
                'GET',
                '/api/v1/' . $topic . '/{slug}/neighbors',
                \Yinghua\Models\IdentityKind::Member,
                $neighborController->{$topic}(...),
            ));
        }

        $memberAccess = new \Yinghua\Services\MemberAccess($accounts);
        $memberAttractions = new \Yinghua\Services\MemberAttractions($publication, new AttractionRepository($database), $clock);
        $favoriteController = new \Yinghua\Controllers\FavoriteController(new \Yinghua\Services\MemberFavorites(
            new \Yinghua\Repositories\FavoriteRepository($database),
            $memberAccess,
            $memberAttractions,
            $transactions,
        ));
        $itineraryController = new \Yinghua\Controllers\CurrentItineraryController(new \Yinghua\Services\CurrentItinerary(
            new \Yinghua\Repositories\ItineraryRepository($database),
            $memberAccess,
            $memberAttractions,
            $transactions,
        ));
        /** @var Closure(Router,\Yinghua\Controllers\FavoriteController,\Yinghua\Controllers\CurrentItineraryController):void $memberRoutes */
        $memberRoutes = require dirname(__DIR__) . '/config/member-routes.php';
        $memberRoutes($router, $favoriteController, $itineraryController);

        /** @var Closure(Router,\Yinghua\Controllers\MemberItineraryController):void $itineraryRoutes */
        $itineraryRoutes = require dirname(__DIR__) . '/config/itinerary-routes.php';
        $itineraryRoutes(
            $router,
            new \Yinghua\Controllers\MemberItineraryController(new \Yinghua\Services\MemberItineraries(
                new \Yinghua\Repositories\ItineraryRepository($database),
                $memberAccess,
                $memberAttractions,
                $transactions,
                $publication,
                new \Yinghua\Repositories\RecommendedRouteRepository($database),
            )),
        );

        $seoCatalog = new \Yinghua\Adapters\SeoCatalog($this->config->string('FRONTEND_SOURCE_PATH', '/var/www/frontend-source'));
        $seoController = new \Yinghua\Controllers\SeoSettingsController(
            new \Yinghua\Services\SeoSettings(
                new \Yinghua\Repositories\SiteSettingsRepository($database),
                new \Yinghua\Repositories\ContentSeoRepository($database),
                new \Yinghua\Repositories\SeoContentRepository($database),
                $authorization,
                $transactions,
                $events,
                $publication,
                $seoCatalog,
                $this->config,
            ),
            $seoCatalog->policy(),
        );
        /** @var Closure(Router,\Yinghua\Controllers\SeoSettingsController,\Yinghua\Controllers\PublicSiteSettingsController):void $seoRoutes */
        $seoRoutes = require dirname(__DIR__) . '/config/seo-routes.php';
        $seoRoutes(
            $router,
            $seoController,
            new \Yinghua\Controllers\PublicSiteSettingsController(new \Yinghua\Services\PublicSiteSettings($publication)),
        );

        $search = new \Yinghua\Controllers\SearchController(new \Yinghua\Services\PublicSearch(
            $publication,
            new \Yinghua\Repositories\SearchRepository($database, $transactions),
        ));
        $router->add(new \Yinghua\Http\Route('GET', '/api/v1/search', \Yinghua\Models\IdentityKind::Member, $search->listing(...)));
        $directory = new \Yinghua\Controllers\MemberDirectoryController(new \Yinghua\Services\MemberDirectory(
            new \Yinghua\Repositories\MemberDirectoryRepository($database),
            $authorization,
        ));
        $router->add(new \Yinghua\Http\Route(
            'GET',
            '/api/v1/admin/members',
            \Yinghua\Models\IdentityKind::Admin,
            $directory->listing(...),
            true,
            'members',
            'view',
        ));
        $general = new \Yinghua\Controllers\GeneralSettingsController(new \Yinghua\Services\GeneralSiteSettings(
            new \Yinghua\Repositories\GeneralSettingsRepository($database),
            new \Yinghua\Repositories\SiteSettingsRepository($database),
            $authorization,
            $transactions,
            $events,
            $publication,
        ));
        $router->add(new \Yinghua\Http\Route(
            'GET',
            '/api/v1/admin/settings',
            \Yinghua\Models\IdentityKind::Admin,
            $general->read(...),
            true,
            'settings',
            'view',
        ));
        $router->add(new \Yinghua\Http\Route(
            'PUT',
            '/api/v1/admin/settings',
            \Yinghua\Models\IdentityKind::Admin,
            $general->save(...),
            true,
            'settings',
            'edit',
        ));
        $operations = new \Yinghua\Controllers\OperationsController(new \Yinghua\Services\OperationsOverview(
            new \Yinghua\Repositories\OperationsRepository($database),
            $authorization,
        ));
        $router->add(new \Yinghua\Http\Route(
            'GET',
            '/api/v1/admin/dashboard',
            \Yinghua\Models\IdentityKind::Admin,
            $operations->read(...),
            true,
            'dashboard',
            'view',
        ));
        $router->add(new \Yinghua\Http\Route(
            'GET',
            '/api/v1/admin/audit-logs',
            \Yinghua\Models\IdentityKind::Admin,
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
            new \Yinghua\Repositories\FilmRepository($database, new \Yinghua\Repositories\FilmScreeningRepository($database)),
            new SystemClock(),
            new \Yinghua\Repositories\NewsRepository($database),
            new \Yinghua\Repositories\OfferRepository($database),
            new \Yinghua\Services\SeoPublication(
                new \Yinghua\Repositories\SiteSettingsRepository($database),
                new \Yinghua\Repositories\ContentSeoRepository($database),
            ),
            new \Yinghua\Repositories\RecommendedRouteRepository($database),
            new \Yinghua\Repositories\SearchRepository($database, new PdoTransactions($database)),
            new \Yinghua\Repositories\GeneralSettingsRepository($database),
        );
    }

    public function publicContentCommands(Database $database): \Yinghua\Adapters\PublicContentCommands
    {
        $secrets = new Secrets($this->config);
        $accounts = new AccountRepository($database);
        $events = new EventRepository($database, $secrets);
        $authorization = new Authorization($accounts, new PermissionRepository($database), new PermissionPolicy());
        $media = new MediaRepository($database);
        $news = new \Yinghua\Services\NewsManagement(
            new \Yinghua\Repositories\NewsRepository($database),
            $media,
            $authorization,
            $events,
            new \Yinghua\Adapters\HtmlPurifierSanitizer(),
            new \Yinghua\Policies\ContentCover(),
            new SystemClock(),
        );
        $merchants = new \Yinghua\Repositories\MerchantRepository($database);
        $offers = new \Yinghua\Services\OfferManagement(
            new \Yinghua\Repositories\OfferRepository($database),
            $merchants,
            $media,
            $authorization,
            $events,
            new \Yinghua\Policies\ContentCover(),
            new SystemClock(),
        );
        $merchantService = new \Yinghua\Services\MerchantManagement($merchants, $authorization, $events);
        return new \Yinghua\Adapters\PublicContentCommands(
            $accounts,
            new \Yinghua\Services\ContentImporter(new PdoTransactions($database), $this->publication($database)),
            $news,
            new MediaManagement($media, new LocalImageStorage($this->config), $authorization, new PdoTransactions($database), $events),
            $secrets,
            $offers,
            $merchantService,
            new \Yinghua\Adapters\PublicContentSeeder(
                $merchants,
                new \Yinghua\Repositories\NewsRepository($database),
                new \Yinghua\Repositories\OfferRepository($database),
                $merchantService,
                $news,
                $offers,
                new PdoTransactions($database),
                $this->publication($database),
            ),
        );
    }
}
