<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use PDO;
use PDOStatement;
use PDOException;
use RuntimeException;
use Yinghua\Config;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\ContentConflict;
use Yinghua\Support\ErrorLog;
use Yinghua\Exceptions\ErrorCode;

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
            throw new ApplicationError(ErrorCode::ServiceUnavailable, '服務暫時無法使用。');
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
            throw new ApplicationError(ErrorCode::ServiceUnavailable, '服務暫時無法使用。');
        }

        return $database;
    }

    /**
     * 新增一筆以 UUID 為主鍵的資料。欄位名稱一律來自 DTO 的欄位白名單，不會是使用者輸入。
     *
     * @param array<string, bool|int|string|null> $values
     */
    public function insert(string $table, string $id, array $values): void
    {
        $columns = array_keys($values);
        $this->execute(
            'INSERT INTO ' . $table . ' (id,' . implode(',', $columns) . ') VALUES (:id,'
            . implode(',', array_map(static fn(string $column): string => ':' . $column, $columns)) . ')',
            ['id' => $id, ...$values],
        );
    }

    /**
     * 更新資料並把版本號加一，供樂觀鎖判斷其他人是否已修改過。
     * $expressions 可為個別欄位指定 SQL 運算式取代單純的 :欄位（參數名稱仍是欄位名稱）。
     *
     * @param array<string, bool|int|string|null> $values
     * @param array<string, string> $expressions
     */
    public function updateVersioned(string $table, string $id, array $values, array $expressions = []): void
    {
        $assignments = array_map(
            static fn(string $column): string => $column . '=' . ($expressions[$column] ?? ':' . $column),
            array_keys($values),
        );
        $this->execute(
            'UPDATE ' . $table . ' SET ' . implode(',', $assignments) . ',version=version+1,updated_at=UTC_TIMESTAMP() WHERE id=:id',
            ['id' => $id, ...$values],
        );
    }

    /**
     * 保留舊網址：同一網址只能屬於一筆內容，改名後舊網址仍導向原內容。
     * 已屬於其他內容時丟出衝突，避免把別人的舊網址搶走。
     */
    public function reserveAlias(string $aliasTable, string $ownerColumn, string $path, string $id): void
    {
        $owner = $this->row('SELECT ' . $ownerColumn . ' AS owner FROM ' . $aliasTable . ' WHERE path=:path', ['path' => $path]);
        if ($owner !== null && (new Row($owner))->string('owner') !== $id) {
            throw new ContentConflict('Historical URL already reserved.');
        }
        $this->execute(
            'INSERT IGNORE INTO ' . $aliasTable . ' (path,' . $ownerColumn . ') VALUES (:path,:id)',
            ['path' => $path, 'id' => $id],
        );
    }

    /** 唯一鍵重複（網址代稱、電子郵件等已被使用），呼叫端會轉成較具體的衝突錯誤。 */
    public static function isDuplicateKey(PDOException $error): bool
    {
        return $error->getCode() === '23000' && ($error->errorInfo[1] ?? null) === 1062;
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
