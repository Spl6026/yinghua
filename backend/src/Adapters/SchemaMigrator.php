<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use RuntimeException;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\Row;

class SchemaMigrator
{
    public function __construct(private readonly Database $database) {}

    public function migrate(string $directory): void
    {
        $this->database->pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(100) PRIMARY KEY,checksum CHAR(64) NOT NULL,applied_statements INT UNSIGNED NOT NULL DEFAULT 0,applied_at DATETIME NULL) ENGINE=InnoDB');
        $lock = $this->database->row("SELECT GET_LOCK(CONCAT(DATABASE(),':migration'),10) AS acquired");
        if ((new Row($lock ?? []))->integer('acquired') !== 1) {
            throw new RuntimeException('Another migration is running.');
        }
        try {
            foreach (glob($directory . '/*.sql') ?: [] as $file) {
                $this->apply($file);
            }
        } finally {
            $this->database->row("SELECT RELEASE_LOCK(CONCAT(DATABASE(),':migration')) AS released");
        }
    }

    private function apply(string $file): void
    {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException('Cannot read migration.');
        }
        $version = basename($file);
        $checksum = hash('sha256', $sql);
        $record = $this->database->row('SELECT * FROM schema_migrations WHERE version=:version', ['version' => $version]);
        if ($record !== null && (new Row($record))->string('checksum') !== $checksum) {
            throw new RuntimeException('Migration checksum changed: ' . $version);
        }
        $this->database->execute('INSERT IGNORE INTO schema_migrations (version,checksum) VALUES (:version,:checksum)', ['version' => $version, 'checksum' => $checksum]);
        $done = $record === null ? 0 : (new Row($record))->integer('applied_statements');
        $parts = preg_split('/^-- @statement\s*$/m', $sql);
        if ($parts === false) {
            throw new RuntimeException('Invalid migration format.');
        }
        foreach (array_slice($parts, 1) as $index => $statement) {
            if ($index < $done) {
                continue;
            }
            // MariaDB DDL 自動提交：以冪等 DDL＋進度 journal 支援中斷後重跑。
            $this->database->pdo->exec(trim($statement));
            $this->database->execute('UPDATE schema_migrations SET applied_statements=:done WHERE version=:version', ['done' => $index + 1, 'version' => $version]);
        }
        $this->database->execute('UPDATE schema_migrations SET applied_at=COALESCE(applied_at,UTC_TIMESTAMP()) WHERE version=:version', ['version' => $version]);
    }
}
