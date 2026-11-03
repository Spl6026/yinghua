<?php

declare(strict_types=1);

use Yinghua\Adapters\IdentitySeeder;
use Yinghua\Adapters\PdoTransactions;
use Yinghua\Adapters\SchemaMigrator;
use Yinghua\Adapters\SystemClock;
use Yinghua\Config;
use Yinghua\Composition;
use Yinghua\Adapters\AttractionSeeder;
use Yinghua\Adapters\FilmSeeder;
use Yinghua\Repositories\FilmRepository;
use Yinghua\Repositories\FilmScreeningRepository;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\ResetRepository;
use Yinghua\Repositories\SecurityRecordRepository;
use Yinghua\Repositories\SessionRepository;
use Yinghua\Services\PasswordHasher;
use Yinghua\Services\RootInitializer;
use Yinghua\DTO\ClientContext;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\EventRepository;
use Yinghua\Support\Secrets;

require dirname(__DIR__) . '/vendor/autoload.php';

function prompt(string $label, bool $secret = false): string
{
    $interactive = stream_isatty(STDIN);
    if ($interactive) {
        fwrite(STDOUT, $label . ': ');
        if ($secret) {
            system('stty -echo');
        }
    }
    try {
        $line = fgets(STDIN);
        if ($line === false) {
            throw new RuntimeException('Missing input.');
        }

        return rtrim($line, "\r\n");
    } finally {
        if ($interactive && $secret) {
            system('stty echo');
            fwrite(STDOUT, "\n");
        }
    }
}

/** CLI 寫入內容時以固定管理者為操作者，稽核紀錄才能追到是誰執行。 */
function fixedAdmin(Database $database, bool $requireActive, string $message): \Yinghua\Models\Account
{
    $actor = (new AccountRepository($database))->byLogin(IdentityKind::Admin, 'admin');
    if ($actor === null || !$actor->isRoot || ($requireActive && !$actor->isActive())) {
        throw new RuntimeException($message);
    }

    return $actor;
}

function cliClient(Secrets $secrets, string $agent): ClientContext
{
    return new ClientContext('cli', $secrets->keyed('ip', 'cli'), $secrets->keyed('agent', $agent), bin2hex(random_bytes(16)));
}

try {
    $config = new Config();
    $database = Database::connect($config);
    $arguments = $argv ?? [];
    $command = $arguments[1] ?? 'help';
    $composition = new Composition($config);
    $resources = dirname(__DIR__) . '/resources';
    $publicContent = static fn(): int => $composition->publicContentCommands($database)->run($arguments);
    $publishing = static function (string $mode) use ($composition, $database): Closure {
        return static function () use ($composition, $database, $mode): int {
            $publication = $composition->publication($database);
            if (!($mode === 'publish' ? $publication->retry() : $publication->tick())) {
                throw new RuntimeException('Publication pending.');
            }
            fwrite(STDOUT, "Published content synchronized.\n");

            return 0;
        };
    };
    $rootAccount = static function (string $mode) use ($config, $database): Closure {
        return static function () use ($config, $database, $mode): int {
            $email = $mode === 'init' ? prompt('Email') : '';
            $password = prompt('Password', true);
            $confirmation = prompt('Confirm password', true);
            if ($confirmation === '' || $password !== $confirmation) {
                throw new RuntimeException('Passwords do not match.');
            }
            $initializer = new RootInitializer(
                new AccountRepository($database),
                new PasswordHasher(),
                new PdoTransactions($database),
                new SessionRepository($database, $config, new SystemClock()),
                new ResetRepository($database),
            );
            if ($mode === 'init') {
                $initializer->initialize($email, $password);
                fwrite(STDOUT, "Fixed admin initialized; existing credentials are never reset by this command.\n");
            } else {
                $initializer->recover($password);
                fwrite(STDOUT, "Fixed admin password recovered; previous sessions revoked.\n");
            }

            return 0;
        };
    };

    // 每個指令一個處理函式（Command）；說明文字由這份清單產生，新增指令只要加一筆。
    /** @var array<string,Closure():int> $commands */
    $commands = [
        'db:migrate' => static function () use ($database): int {
            (new SchemaMigrator($database))->migrate(dirname(__DIR__) . '/database/migrations');
            fwrite(STDOUT, "Migrations applied.\n");

            return 0;
        },
        'db:seed' => static function () use ($database, $resources): int {
            (new IdentitySeeder($database))->seed($resources . '/role-templates.json');
            fwrite(STDOUT, "Role templates initialized without overwriting existing values.\n");

            return 0;
        },
        'admin:init' => $rootAccount('init'),
        'admin:recover' => $rootAccount('recover'),
        'content:seed-demo' => static function () use ($composition, $database, $resources): int {
            $publication = $composition->publication($database);
            $publication->exclusive(function () use ($database, $publication, $resources): void {
                (new AttractionSeeder($database, new AttractionRepository($database), new PdoTransactions($database)))
                    ->seed($resources . '/attractions-demo.json');
                if (!$publication->synchronize()) {
                    throw new RuntimeException('Demo saved; publication pending.');
                }
            });
            fwrite(
                STDOUT,
                "Original demo imported; existing content was not overwritten. Replace unlicensed demonstration material before launch.\n",
            );

            return 0;
        },
        'film:seed-demo' => static function () use ($composition, $config, $database, $resources): int {
            $actor = fixedAdmin($database, true, 'Initialize the active fixed administrator before importing films.');
            $secrets = new Secrets($config);
            $client = cliClient($secrets, 'film-demo');
            $publication = $composition->publication($database);
            $publication->exclusive(function () use ($database, $publication, $actor, $secrets, $client, $resources): void {
                (new FilmSeeder(
                    $database,
                    new FilmRepository($database, new FilmScreeningRepository($database)),
                    new PdoTransactions($database),
                    new EventRepository($database, $secrets),
                ))
                    ->seed($resources . '/films-demo.json', $actor->id, $client);
                if (!$publication->synchronize()) {
                    throw new RuntimeException('Films saved; publication pending.');
                }
            });
            fwrite(STDOUT, "Original film metadata imported without inventing screening years. Existing content was not overwritten.\n");

            return 0;
        },
        'content:seed-public-demo' => $publicContent,
        'content:import' => $publicContent,
        'media:import' => $publicContent,
        'content:backfill-demo-labels' => static function () use ($arguments, $composition, $config, $database, $resources): int {
            if (count($arguments) > 3 || (isset($arguments[2]) && $arguments[2] !== '--apply')) {
                throw new RuntimeException('Only --apply is supported; omitted means preview.');
            }
            $apply = ($arguments[2] ?? '') === '--apply';
            $actor = fixedAdmin($database, false, 'Initialize the fixed administrator before maintenance.');
            $secrets = new Secrets($config);
            $client = cliClient($secrets, 'content-maintenance');
            $publication = $composition->publication($database);
            $changes = $publication->exclusive(function () use ($database, $publication, $apply, $actor, $secrets, $client, $resources): array {
                $changes = (new AttractionSeeder($database, new AttractionRepository($database), new PdoTransactions($database)))
                    ->backfillLabels(
                        $resources . '/attractions-demo.json',
                        $apply,
                        $actor->id,
                        new EventRepository($database, $secrets),
                        $client,
                    );
                if ($apply && !$publication->synchronize()) {
                    throw new RuntimeException('Labels saved; publication pending. Retry content:publish.');
                }
                return $changes;
            });
            fwrite(STDOUT, json_encode(['applied' => $apply,'changes' => $changes], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n");

            return 0;
        },
        'content:publish' => $publishing('publish'),
        'content:tick' => $publishing('tick'),
        'content:watch' => static function () use ($composition, $database): never {
            $publication = $composition->publication($database);
            // 常駐的發布程序順便每小時清一次過期的登入與驗證碼紀錄，不必另外設排程。
            $securityRecords = new SecurityRecordRepository($database);
            $lastPrune = 0;
            for (;;) {
                // 每一輪更新心跳檔；容器健康檢查看這個檔案多久沒更新，就知道發布程序是否卡住。
                @touch(sys_get_temp_dir() . '/yinghua-publisher-heartbeat');
                try {
                    if (!$publication->tick()) {
                        fwrite(STDERR, "Publication pending; retrying.\n");
                    }
                    if (time() - $lastPrune >= 3600) {
                        $lastPrune = time();
                        $removed = $securityRecords->pruneExpired(time());
                        if (array_sum($removed) > 0) {
                            fwrite(STDOUT, 'Expired security records removed: ' . json_encode($removed, JSON_THROW_ON_ERROR) . "\n");
                        }
                    }
                } catch (PDOException) {
                    // 資料庫重新啟動後，這個長駐程序手上的連線就失效了，之後每一輪都會失敗。
                    // 直接結束，讓容器的 restart 策略重新啟動並建立新連線。
                    fwrite(STDERR, "Publication worker lost its database connection; exiting so it restarts.\n");
                    exit(1);
                } catch (Throwable $error) {
                    fwrite(STDERR, 'Publication worker (' . $error::class . ").\n");
                }
                sleep(10);
            }
        },
        'security:prune' => static function () use ($database): int {
            $removed = (new SecurityRecordRepository($database))->pruneExpired(time());
            fwrite(STDOUT, json_encode(['removed' => $removed], JSON_THROW_ON_ERROR) . "\n");

            return 0;
        },
    ];

    $handler = $commands[$command] ?? null;
    if ($handler === null) {
        $names = array_map(static fn(string $name): string => $name === 'content:backfill-demo-labels' ? $name . ' [--apply]' : $name, array_keys($commands));
        fwrite(STDOUT, 'Commands: ' . implode(', ', $names) . "\n");
        exit(0);
    }
    exit($handler());
} catch (Throwable $error) {
    fwrite(STDERR, 'Command failed (' . $error::class . "). Check configuration and the documented input requirements.\n");
    exit(1);
}
