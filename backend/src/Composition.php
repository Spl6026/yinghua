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

/** [E-01] 唯一組裝位置；業務類別不查容器，也不自行 new 資料庫／SMTP。 */
readonly class Composition
{
    public function __construct(private Config $config) {}

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
        $authentication = new Authentication($accounts, $sessions, $passwords, $limiter, $events, $transactions, $captcha, $this->config, new ConsentRepository($database));
        $passwordReset = new PasswordReset($accounts, $resets, $sessions, $passwords, $limiter, $events, $transactions, $clock, $this->config, $mailer);
        $csrf = new Csrf($this->config, $clock, $secrets);
        $authController = new AuthController($authentication, $authorization, $captcha, $passwordReset, $csrf, $this->config, new AdminPreview($accounts, $sessions, $events, $transactions));
        $roles = new RoleTemplates($permissions, $authorization);
        $permissionController = new PermissionController(new PermissionManagement($accounts, $permissions, $authorization, $policy, $transactions, $events), $roles);
        $adminController = new AdminController(new AdminManagement($accounts, $permissions, $roles, $authorization, $policy, $passwords, $transactions, $events, $sessions, $resets));
        $publication = $this->publication($database);
        $attractionController = new AttractionController(new AttractionManagement(new AttractionRepository($database), $authorization, $transactions, $events, $publication, new \Yinghua\Policies\GooglePlaceLinkPolicy()));
        $publicAttractions = new PublicAttractionController(new PublicAttractions($publication), $publication);
        $mediaController = new MediaController(new MediaManagement(new MediaRepository($database), new LocalImageStorage($this->config), $authorization, $transactions, $events), new MediaAccess($publication, $authentication, $authorization, new MediaRepository($database)));
        $mapPolicy = new GoogleUrlPolicy();
        $mapController = new MapController(new MapResolver(new CurlGoogleHttp($mapPolicy), $mapPolicy, $this->config, $authorization, $limiter, new \Yinghua\Adapters\GooglePlaceMapper(new \Yinghua\Adapters\GoogleOpeningHours())));
        $filmController = new \Yinghua\Controllers\PublicFilmController(new \Yinghua\Services\PublicFilms($publication), $clock);
        $filmScreens = new \Yinghua\Repositories\FilmScreeningRepository($database);
        $filmAdmin = new \Yinghua\Controllers\FilmController(new \Yinghua\Services\FilmManagement(new \Yinghua\Repositories\FilmRepository($database, $filmScreens), new MediaRepository($database), $authorization, $transactions, $events, $publication, new \Yinghua\Policies\FilmPosterPolicy(), $filmScreens, new \Yinghua\Policies\ScreeningStartPolicy()));
        $router = new Router();
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

        $memberAccess = new \Yinghua\Services\MemberAccess($accounts);
        $memberAttractions = new \Yinghua\Services\MemberAttractions($publication, new AttractionRepository($database), $clock);
        $favoriteController = new \Yinghua\Controllers\FavoriteController(new \Yinghua\Services\MemberFavorites(new \Yinghua\Repositories\FavoriteRepository($database), $memberAccess, $memberAttractions, $transactions));
        $itineraryController = new \Yinghua\Controllers\CurrentItineraryController(new \Yinghua\Services\CurrentItinerary(new \Yinghua\Repositories\ItineraryRepository($database), $memberAccess, $memberAttractions, $transactions));
        /** @var Closure(Router,\Yinghua\Controllers\FavoriteController,\Yinghua\Controllers\CurrentItineraryController):void $memberRoutes */
        $memberRoutes = require dirname(__DIR__) . '/config/member-routes.php';
        $memberRoutes($router, $favoriteController, $itineraryController);

        $seoCatalog = new \Yinghua\Adapters\SeoCatalog($this->config->string('FRONTEND_SOURCE_PATH', '/var/www/frontend-source'));
        $seoController = new \Yinghua\Controllers\SeoSettingsController(new \Yinghua\Services\SeoSettings(new \Yinghua\Repositories\SiteSettingsRepository($database), new \Yinghua\Repositories\ContentSeoRepository($database), new \Yinghua\Repositories\SeoContentRepository($database), $authorization, $transactions, $events, $publication, $seoCatalog, $this->config), $seoCatalog->policy());
        /** @var Closure(Router,\Yinghua\Controllers\SeoSettingsController,\Yinghua\Controllers\PublicSiteSettingsController):void $seoRoutes */
        $seoRoutes = require dirname(__DIR__) . '/config/seo-routes.php';
        $seoRoutes($router, $seoController, new \Yinghua\Controllers\PublicSiteSettingsController(new \Yinghua\Services\PublicSiteSettings($publication)));

        return new Kernel($router, $csrf, $authentication, $authorization, $secrets, $events);
    }

    public function publication(?Database $database = null): ContentPublication
    {
        $database ??= Database::connect($this->config);

        return new ContentPublication(new AttractionRepository($database), new PublicationRepository($database), new NodeContentRenderer($this->config), $this->config, new \Yinghua\Repositories\FilmRepository($database, new \Yinghua\Repositories\FilmScreeningRepository($database)), new SystemClock(), new \Yinghua\Repositories\NewsRepository($database), new \Yinghua\Repositories\OfferRepository($database), new \Yinghua\Services\SeoPublication(new \Yinghua\Repositories\SiteSettingsRepository($database), new \Yinghua\Repositories\ContentSeoRepository($database)));
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
