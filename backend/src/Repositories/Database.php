<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use PDO;
use PDOStatement;
use RuntimeException;
use Yinghua\Config;

/** 共用一個 PDO；每個 Repository 的寫入參與同一個交易。 */
class Database
{
    public function __construct(public readonly PDO $pdo) {}

    public static function connect(Config $config): self
    {
        $name = $config->string('DB_DATABASE', 'yinghua');
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $name)) {
            throw new RuntimeException('Invalid database name.');
        }
        $pdo = new PDO(
            'mysql:host=' . $config->string('DB_HOST', 'db') . ';port=' . $config->integer('DB_PORT', 3306)
            . ';dbname=' . $name . ';charset=utf8mb4',
            $config->string('DB_USERNAME', 'spl'),
            $config->string('DB_PASSWORD'),
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
        );
        $pdo->exec("SET time_zone = '+00:00'");

        return new self($pdo);
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
