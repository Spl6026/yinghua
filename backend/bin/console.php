<?php

declare(strict_types=1);

use Yinghua\Adapters\IdentitySeeder;
use Yinghua\Adapters\PdoTransactions;
use Yinghua\Adapters\SchemaMigrator;
use Yinghua\Adapters\SystemClock;
use Yinghua\Config;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\ResetRepository;
use Yinghua\Repositories\SessionRepository;
use Yinghua\Services\PasswordHasher;
use Yinghua\Services\RootInitializer;

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
    $command = $argv[1] ?? 'help';
    if ($command === 'db:migrate') {
        (new SchemaMigrator($database))->migrate(dirname(__DIR__) . '/database/migrations');
        fwrite(STDOUT, "Migrations applied.\n");
    } elseif ($command === 'db:seed') {
        (new IdentitySeeder($database))->seed(dirname(__DIR__) . '/resources/role-templates.json');
        fwrite(STDOUT, "Role templates initialized without overwriting existing values.\n");
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
        fwrite(STDOUT, "Commands: db:migrate, db:seed, admin:init, admin:recover\n");
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Command failed (' . $error::class . "). Check configuration and the documented input requirements.\n");
    exit(1);
}
