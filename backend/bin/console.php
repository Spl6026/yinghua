<?php

declare(strict_types=1);

use Yinghua\Adapters\IdentitySeeder;
use Yinghua\Adapters\SchemaMigrator;
use Yinghua\Config;
use Yinghua\Repositories\Database;

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

    } else {
        fwrite(STDOUT, "Commands: db:migrate, db:seed\n");
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'Command failed (' . $error::class . "). Check configuration and the documented input requirements.\n");
    exit(1);
}
