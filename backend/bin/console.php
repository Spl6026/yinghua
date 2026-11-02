<?php

declare(strict_types=1);

use Yinghua\Adapters\IdentitySeeder;
use Yinghua\Adapters\PdoTransactions;
use Yinghua\Adapters\SchemaMigrator;
use Yinghua\Adapters\SystemClock;
use Yinghua\Config;
use Yinghua\Composition;
use Yinghua\Adapters\AttractionSeeder;
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

try {
    $config = new Config();
    $database = Database::connect($config);
    $arguments = $argv ?? [];
    $command = $arguments[1] ?? 'help';
    if (in_array($command, ['content:import','media:import','content:seed-public-demo'], true)) {
        exit((new Composition($config))->publicContentCommands($database)->run($arguments));
    } elseif ($command === 'db:migrate') {
        (new SchemaMigrator($database))->migrate(dirname(__DIR__) . '/database/migrations');
        fwrite(STDOUT, "Migrations applied.\n");
    } elseif ($command === 'db:seed') {
        (new IdentitySeeder($database))->seed(dirname(__DIR__) . '/resources/role-templates.json');
        fwrite(STDOUT, "Role templates initialized without overwriting existing values.\n");
    } elseif ($command === 'film:seed-demo') {
        $actor = (new AccountRepository($database))->byLogin(IdentityKind::Admin, 'admin');
        if ($actor === null || !$actor->isRoot || !$actor->isActive()) {
            throw new RuntimeException('Initialize the active fixed administrator before importing films.');
        }
        $secrets = new Secrets($config);
        $client = new ClientContext('cli', $secrets->keyed('ip', 'cli'), $secrets->keyed('agent', 'film-demo'), bin2hex(random_bytes(16)));
        $publication = (new Composition($config))->publication($database);
        $publication->exclusive(function () use ($database, $publication, $actor, $secrets, $client): void {
            $screenings = new \Yinghua\Repositories\FilmScreeningRepository($database);
            (new \Yinghua\Adapters\FilmSeeder(
                $database,
                new \Yinghua\Repositories\FilmRepository($database, $screenings),
                new PdoTransactions($database),
                new EventRepository($database, $secrets),
            ))
                ->seed(dirname(__DIR__) . '/resources/films-demo.json', $actor->id, $client);
            if (!$publication->synchronize()) {
                throw new RuntimeException('Films saved; publication pending.');
            }
        });
        fwrite(STDOUT, "Original film metadata imported without inventing screening years. Existing content was not overwritten.\n");
    } elseif ($command === 'content:seed-demo') {
        $publication = (new Composition($config))->publication($database);
        $publication->exclusive(function () use ($database, $publication): void {
            (new AttractionSeeder($database, new AttractionRepository($database), new PdoTransactions($database)))
                ->seed(dirname(__DIR__) . '/resources/attractions-demo.json');
            if (!$publication->synchronize()) {
                throw new RuntimeException('Demo saved; publication pending.');
            }
        });
        fwrite(
            STDOUT,
            "Original demo imported; existing content was not overwritten. Replace unlicensed demonstration material before launch.\n",
        );
    } elseif ($command === 'content:backfill-demo-labels') {
        if (count($arguments) > 3 || (isset($arguments[2]) && $arguments[2] !== '--apply')) {
            throw new RuntimeException('Only --apply is supported; omitted means preview.');
        }
        $apply = ($arguments[2] ?? '') === '--apply';
        $actor = (new AccountRepository($database))->byLogin(IdentityKind::Admin, 'admin');
        if ($actor === null || !$actor->isRoot) {
            throw new RuntimeException('Initialize the fixed administrator before maintenance.');
        }
        $secrets = new Secrets($config);
        $client = new ClientContext(
            'cli',
            $secrets->keyed('ip', 'cli'),
            $secrets->keyed('agent', 'content-maintenance'),
            bin2hex(random_bytes(16)),
        );
        $publication = (new Composition($config))->publication($database);
        $changes = $publication->exclusive(function () use ($database, $publication, $apply, $actor, $secrets, $client): array {
            $changes = (new AttractionSeeder($database, new AttractionRepository($database), new PdoTransactions($database)))
                ->backfillLabels(
                    dirname(__DIR__) . '/resources/attractions-demo.json',
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
    } elseif (in_array($command, ['content:publish','content:tick','content:watch'], true)) {
        $publication = (new Composition($config))->publication($database);
        if ($command === 'content:watch') {
            // 常駐的發布程序順便每小時清一次過期的登入與驗證碼紀錄，不必另外設排程。
            $securityRecords = new SecurityRecordRepository($database);
            $lastPrune = 0;
            for (;;) {
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
                } catch (PDOException $error) {
                    // 資料庫重新啟動後，這個長駐程序手上的連線就失效了，之後每一輪都會失敗。
                    // 直接結束，讓容器的 restart 策略重新啟動並建立新連線。
                    fwrite(STDERR, "Publication worker lost its database connection; exiting so it restarts.\n");
                    exit(1);
                } catch (Throwable $error) {
                    fwrite(STDERR, 'Publication worker (' . $error::class . ").\n");
                }
                sleep(10);
            }
        }
        if (!($command === 'content:publish' ? $publication->retry() : $publication->tick())) {
            throw new RuntimeException('Publication pending.');
        }
        fwrite(STDOUT, "Published content synchronized.\n");
    } elseif ($command === 'security:prune') {
        $removed = (new SecurityRecordRepository($database))->pruneExpired(time());
        fwrite(STDOUT, json_encode(['removed' => $removed], JSON_THROW_ON_ERROR) . "\n");
    } elseif (in_array($command, ['admin:init', 'admin:recover'], true)) {
        $email = $command === 'admin:init' ? prompt('Email') : '';
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
        if ($command === 'admin:init') {
            $initializer->initialize($email, $password);
            fwrite(STDOUT, "Fixed admin initialized; existing credentials are never reset by this command.\n");
        } else {
            $initializer->recover($password);
            fwrite(STDOUT, "Fixed admin password recovered; previous sessions revoked.\n");
        }
    } else {
        fwrite(
            STDOUT,
            "Commands: db:migrate, db:seed, admin:init, admin:recover, content:seed-demo, film:seed-demo, content:seed-public-demo, content:import, media:import, content:backfill-demo-labels [--apply], content:publish, content:tick, content:watch, security:prune\n",
        );
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Command failed (' . $error::class . "). Check configuration and the documented input requirements.\n");
    exit(1);
}
