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
    if ($command === 'db:migrate') {
        (new SchemaMigrator($database))->migrate(dirname(__DIR__) . '/database/migrations');
        fwrite(STDOUT, "Migrations applied.\n");
    } elseif ($command === 'db:seed') {
        (new IdentitySeeder($database))->seed(dirname(__DIR__) . '/resources/role-templates.json');
        fwrite(STDOUT, "Role templates initialized without overwriting existing values.\n");
    } elseif ($command === 'content:seed-demo') {
        $publication = (new Composition($config))->publication($database);
        $publication->exclusive(function () use ($database, $publication): void {
            (new AttractionSeeder($database, new AttractionRepository($database), new PdoTransactions($database)))
                ->seed(dirname(__DIR__) . '/resources/attractions-demo.json');
            if (!$publication->synchronize()) {
                throw new RuntimeException('Demo saved; publication pending.');
            }
        });
        fwrite(STDOUT, "Original demo imported; existing content was not overwritten. Replace unlicensed demonstration material before launch.\n");
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
        $client = new ClientContext('cli', $secrets->keyed('ip', 'cli'), $secrets->keyed('agent', 'content-maintenance'), bin2hex(random_bytes(16)));
        $publication = (new Composition($config))->publication($database);
        $changes = $publication->exclusive(function () use ($database, $publication, $apply, $actor, $secrets, $client): array {
            $changes = (new AttractionSeeder($database, new AttractionRepository($database), new PdoTransactions($database)))
                ->backfillLabels(dirname(__DIR__) . '/resources/attractions-demo.json', $apply, $actor->id, new EventRepository($database, $secrets), $client);
            if ($apply && !$publication->synchronize()) {
                throw new RuntimeException('Labels saved; publication pending. Retry content:publish.');
            }
            return $changes;
        });
        fwrite(STDOUT, json_encode(['applied' => $apply,'changes' => $changes], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n");
    } elseif (in_array($command, ['content:publish','content:tick','content:watch'], true)) {
        $publication = (new Composition($config))->publication($database);
        if ($command === 'content:watch') {
            for (;;) {
                try {
                    if (!$publication->tick()) {
                        fwrite(STDERR, "Publication pending; retrying.\n");
                    }
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
        fwrite(STDOUT, "Commands: db:migrate, db:seed, admin:init, admin:recover, content:seed-demo, content:backfill-demo-labels [--apply], content:publish, content:tick, content:watch\n");
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Command failed (' . $error::class . "). Check configuration and the documented input requirements.\n");
    exit(1);
}
