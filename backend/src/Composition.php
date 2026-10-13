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
use Yinghua\Services\RoleTemplates;
use Yinghua\Services\Authentication;
use Yinghua\Services\Authorization;
use Yinghua\Services\Captcha;
use Yinghua\Services\PasswordHasher;
use Yinghua\Services\PasswordReset;
use Yinghua\Services\RateLimiter;
use Yinghua\Support\Secrets;

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
        $authController = new AuthController($authentication, $authorization, $captcha, $passwordReset, $csrf, $this->config);
        $roles = new RoleTemplates($permissions, $authorization);
        $permissionController = new PermissionController($roles);
        $adminController = new AdminController(new AdminManagement($accounts, $permissions, $roles, $authorization, $policy, $passwords, $transactions, $events, $sessions, $resets));
        $router = new Router();
        /** @var Closure(Router,AuthController,AdminController,PermissionController):void $routes */
        $routes = require dirname(__DIR__) . '/config/routes.php';
        $routes($router, $authController, $adminController, $permissionController);

        return new Kernel($router, $csrf, $authentication, $authorization, $secrets, $events);
    }
}
