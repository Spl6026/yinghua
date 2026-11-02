<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use PDO;
use RuntimeException;
use Yinghua\Config;
use Yinghua\Contracts\DatabaseHealthProbe;

/** 健康檢查用獨立的短連線，不執行 DDL 或 seed；也不能先組裝需要資料庫的認證 Kernel。 */
readonly class PdoDatabaseHealthProbe implements DatabaseHealthProbe
{
    public function __construct(private Config $config, private string $migrations) {}

    public function ready(): bool
    {
        $name = $this->config->string('DB_DATABASE', 'yinghua');
        $host = $this->config->string('DB_HOST', 'db');
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name) || !preg_match('/^[a-zA-Z0-9.:-]+$/D', $host)) {
            throw new RuntimeException('Invalid health database configuration.');
        }
        // mysqlnd 的連線與讀取要分開設逾時；finally 會還原這個 FPM 請求的 ini 設定。
        $previous = ini_set('mysqlnd.net_read_timeout', '2');
        try {
            $pdo = new PDO(
                'mysql:host=' . $host . ';port=' . $this->config->integer('DB_PORT', 3306) . ';dbname=' . $name . ';charset=utf8mb4',
                $this->config->string('DB_USERNAME', 'spl'),
                $this->config->string('DB_PASSWORD'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 2, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
            );
            $pdo->exec('SET SESSION max_statement_time=2,lock_wait_timeout=2');
            $query = $pdo->query('SELECT 1');
            if ($query === false || (int) $query->fetchColumn() !== 1) {
                return false;
            }
            $files = glob($this->migrations . '/*.sql') ?: [];
            if ($files === []) {
                return false;
            }
            $query = $pdo->query('SELECT version,checksum,applied_at FROM schema_migrations');
            if ($query === false) {
                return false;
            }
            $rows = $query->fetchAll();
            if (count($rows) !== count($files)) {
                return false;
            }
            $applied = [];
            foreach ($rows as $row) {
                if (!is_array($row) || !is_string($row['version'] ?? null) || !is_string($row['checksum'] ?? null)
                    || !is_string($row['applied_at'] ?? null)) {
                    return false;
                }
                $applied[$row['version']] = $row['checksum'];
            }
            foreach ($files as $file) {
                $checksum = hash_file('sha256', $file);
                if ($checksum === false || ($applied[basename($file)] ?? null) !== $checksum) {
                    return false;
                }
            }

            return true;
        } finally {
            if ($previous !== false) {
                ini_set('mysqlnd.net_read_timeout', $previous);
            }
        }
    }
}
