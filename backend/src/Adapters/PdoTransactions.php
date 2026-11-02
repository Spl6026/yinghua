<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Throwable;
use PDOException;
use Yinghua\Contracts\Transactions;
use Yinghua\Repositories\Database;

class PdoTransactions implements Transactions
{
    public function __construct(private readonly Database $database) {}

    public function run(callable $operation): mixed
    {
        // 交易內只放資料庫異動，所以遇到死鎖重試時不會重複寄信。
        for ($attempt = 0; ; $attempt++) {
            $this->database->pdo->beginTransaction();
            try {
                $result = $operation();
                $this->database->pdo->commit();
                return $result;
            } catch (Throwable $error) {
                if ($this->database->pdo->inTransaction()) {
                    $this->database->pdo->rollBack();
                }
                if ($attempt >= 2 || !$error instanceof PDOException || $error->getCode() !== '40001') {
                    throw $error;
                }
                usleep(random_int(10000, 50000));
            }
        }
    }
}
