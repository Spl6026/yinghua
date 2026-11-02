<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use PDO;
use PDOStatement;
use PDOException;
use RuntimeException;
use Yinghua\Config;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Support\ErrorLog;

/** 所有 Repository 共用同一個 PDO，寫入才能參與同一筆交易。 */
class Database
{
    public function __construct(public readonly PDO $pdo) {}

    /** 一般請求與健康檢查共用同一組連線參數，避免兩邊設定不一致。 */
    public static function open(Config $config): PDO
    {
        $name = $config->string('DB_DATABASE', 'yinghua');
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name)) {
            throw new RuntimeException('Invalid database name.');
        }

        return new PDO(
            'mysql:host=' . $config->string('DB_HOST', 'db') . ';port=' . $config->integer('DB_PORT', 3306)
            . ';dbname=' . $name . ';charset=utf8mb4',
            $config->string('DB_USERNAME', 'spl'),
            $config->string('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 2],
        );
    }

    public static function connect(Config $config): self
    {
        try {
            $pdo = self::open($config);
            $pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $error) {
            // 資料庫暫停或斷線時，不讓請求卡在預設的 30 秒逾時，也不把連線細節洩漏給使用者。
            ErrorLog::exception('database_connect', $error);
            throw new ApplicationError('SERVICE_UNAVAILABLE', '服務暫時無法使用。');
        }

        return new self($pdo);
    }

    /**
     * 網頁請求用的連線：等鎖與單一查詢都設上限。
     * Nginx 15 秒就放棄等待 PHP，但 PHP-FPM 的 worker 仍會卡在 InnoDB 預設 50 秒的等鎖上；
     * worker 數量有限，幾個卡住的請求就會讓整個網站排隊。命令列工具（發布、migration）不套用。
     */
    public static function forRequest(Config $config): self
    {
        $database = self::connect($config);
        try {
            $database->pdo->exec('SET SESSION innodb_lock_wait_timeout = 5, max_statement_time = 10');
        } catch (PDOException $error) {
            ErrorLog::exception('database_session_limits', $error);
            throw new ApplicationError('SERVICE_UNAVAILABLE', '服務暫時無法使用。');
        }

        return $database;
    }

    /** @param array<string, bool|int|string|null> $parameters */
    public function execute(string $sql, array $parameters = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        foreach ($parameters as $name => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue(':' . $name, $value, $type);
        }
        $statement->execute();

        return $statement;
    }

    /** @param array<string, bool|int|string|null> $parameters
     * @return array<string, mixed>|null
     */
    public function row(string $sql, array $parameters = []): ?array
    {
        $result = $this->execute($sql, $parameters)->fetch();
        if (!is_array($result)) {
            return null;
        }
        $row = [];
        foreach ($result as $key => $value) {
            if (is_string($key)) {
                $row[$key] = $value;
            }
        }

        return $row;
    }

    /** @param array<string, bool|int|string|null> $parameters
     * @return list<array<string, mixed>>
     */
    public function rows(string $sql, array $parameters = []): array
    {
        $result = [];
        foreach ($this->execute($sql, $parameters)->fetchAll() as $row) {
            if (is_array($row)) {
                $mapped = [];
                foreach ($row as $key => $value) {
                    if (is_string($key)) {
                        $mapped[$key] = $value;
                    }
                }
                $result[] = $mapped;
            }
        }

        return $result;
    }
}
