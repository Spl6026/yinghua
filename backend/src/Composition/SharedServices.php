<?php

declare(strict_types=1);

namespace Yinghua\Composition;

use Yinghua\Adapters\NodeContentRenderer;
use Yinghua\Adapters\PdoTransactions;
use Yinghua\Adapters\PhpMailerAdapter;
use Yinghua\Adapters\SystemClock;
use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\Contracts\Mailer;
use Yinghua\Contracts\Transactions;
use Yinghua\Http\Csrf;
use Yinghua\Policies\PermissionPolicy;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\ChallengeRepository;
use Yinghua\Repositories\ConsentRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\GeneralSettingsRepository;
use Yinghua\Repositories\MediaRepository;
use Yinghua\Repositories\PermissionRepository;
use Yinghua\Repositories\RateLimitRepository;
use Yinghua\Repositories\RecommendedRouteRepository;
use Yinghua\Repositories\SeoSettingsRepository;
use Yinghua\Repositories\SessionRepository;
use Yinghua\Services\Authentication;
use Yinghua\Services\Authorization;
use Yinghua\Services\Captcha;
use Yinghua\Services\ContentPublication;
use Yinghua\Services\ContentWriter;
use Yinghua\Services\DetailUrlResolver;
use Yinghua\Services\PasswordHasher;
use Yinghua\Services\PublishedPolicies;
use Yinghua\Services\RateLimiter;
use Yinghua\Support\Secrets;

/**
 * 一次請求內跨模組共用的物件：資料庫連線、時鐘、權限、稽核、發布與登入狀態。
 * 這些物件不保存請求間的狀態，同一個請求只建立一份；各區塊的 Controller 從這裡取用，
 * 不必把十幾個參數一路傳下去，也確保大家拿到的是同一個交易與同一份發布快照。
 */
final readonly class SharedServices
{
    public Clock $clock;
    public Secrets $secrets;
    public Transactions $transactions;
    public AccountRepository $accounts;
    public SessionRepository $sessions;
    public PermissionRepository $permissions;
    public EventRepository $events;
    public MediaRepository $mediaRecords;
    public AttractionRepository $attractionRecords;
    public RecommendedRouteRepository $routeRecords;
    public SeoSettingsRepository $seoSettingsRecords;
    public GeneralSettingsRepository $generalRecords;
    public RateLimiter $limiter;
    public PasswordHasher $passwords;
    public PermissionPolicy $permissionPolicy;
    public Authorization $authorization;
    public Mailer $mailer;
    public Captcha $captcha;
    public PublishedPolicies $policies;
    public Authentication $authentication;
    public Csrf $csrf;
    public ContentWriter $writer;
    public DetailUrlResolver $detailUrls;

    public function __construct(
        public Config $config,
        public Database $database,
        public ContentPublication $publication,
    ) {
        $this->clock = new SystemClock();
        $this->secrets = new Secrets($config);
        $this->transactions = new PdoTransactions($database);
        $this->accounts = new AccountRepository($database);
        $this->sessions = new SessionRepository($database, $config, $this->clock);
        $this->permissions = new PermissionRepository($database);
        $this->events = new EventRepository($database, $this->secrets);
        $this->mediaRecords = new MediaRepository($database);
        $this->attractionRecords = new AttractionRepository($database);
        $this->routeRecords = new RecommendedRouteRepository($database);
        $this->seoSettingsRecords = new SeoSettingsRepository($database);
        $this->generalRecords = new GeneralSettingsRepository($database);
        $this->limiter = new RateLimiter(new RateLimitRepository($database), $this->secrets, $this->clock);
        $this->passwords = new PasswordHasher();
        $this->permissionPolicy = new PermissionPolicy();
        $this->authorization = new Authorization($this->accounts, $this->permissions, $this->permissionPolicy);
        $this->mailer = new PhpMailerAdapter($config);
        $this->captcha = new Captcha(
            new ChallengeRepository($database),
            $this->transactions,
            $this->limiter,
            $this->secrets,
            $this->clock,
            $config,
            $this->mailer,
        );
        $this->policies = new PublishedPolicies($this->generalRecords, new NodeContentRenderer($config), $config);
        // 登入狀態除了認證 API，也供 Kernel 判斷身分及媒體預覽權限使用。
        $this->authentication = new Authentication(
            $this->accounts,
            $this->sessions,
            $this->passwords,
            $this->limiter,
            $this->events,
            $this->transactions,
            $this->captcha,
            new ConsentRepository($database),
            $this->policies,
        );
        $this->csrf = new Csrf($config, $this->clock, $this->secrets);
        $this->writer = new ContentWriter($this->authorization, $this->transactions, $publication, $this->events);
        $this->detailUrls = new DetailUrlResolver($publication);
    }
}
