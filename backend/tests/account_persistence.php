<?php

declare(strict_types=1);

use Yinghua\Adapters\PdoTransactions;
use Yinghua\Config;
use Yinghua\DTO\AdminWriteInput;
use Yinghua\DTO\RegisterInput;
use Yinghua\Exceptions\AccountConflict;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\Database;
use Yinghua\Support\Secrets;

/** 在真實 MariaDB 驗證例外轉換與交易行為；只允許 yinghua_test，不會留下任何帳號。 */
require dirname(__DIR__) . '/vendor/autoload.php';
$config = new Config();
if ($config->string('APP_ENV') !== 'test' || $config->string('DB_DATABASE') !== 'yinghua_test') {
    throw new RuntimeException('Persistence checks require the isolated test database.');
}
$database = Database::connect($config);
$accounts = new AccountRepository($database);
$transactions = new PdoTransactions($database);
$run = 'layer' . bin2hex(random_bytes(5));
$checks = 0;
$check = static function (string $name, bool $passed) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException($name);
    }
    ++$checks;
    fwrite(STDOUT, 'PASS ' . $name . "\n");
};
$input = static fn(string $account, string $email, string $role = 'viewer'): AdminWriteInput => new AdminWriteInput(
    $account,
    '分層驗證',
    $email,
    $role,
    'active',
    null,
    1,
);

$database->pdo->beginTransaction();
try {
    $id = Secrets::uuid();
    $accounts->createAdmin($id, $input($run, $run . '@example.test'), 'test-only-hash');
    foreach ([$input($run, $run . '-other@example.test'), $input($run . 'other', $run . '@example.test')] as $duplicate) {
        try {
            $accounts->createAdmin(Secrets::uuid(), $duplicate, 'test-only-hash');
            throw new RuntimeException('Duplicate admin was accepted.');
        } catch (AccountConflict $error) {
            $check('admin duplicate account/email becomes application exception', $error->getMessage() === 'Account unique value already exists.');
        }
    }
    $secondId = Secrets::uuid();
    $accounts->createAdmin($secondId, $input($run . 'second', $run . '-second@example.test'), 'test-only-hash');
    try {
        $accounts->updateAdmin($secondId, $input($run . 'second', $run . '@example.test'));
        throw new RuntimeException('Duplicate update was accepted.');
    } catch (AccountConflict) {
        $check('duplicate email update preserves original row', $accounts->find(IdentityKind::Admin, $secondId)?->email === $run . '-second@example.test');
    }
    $member = new RegisterInput($run . '-member@example.test', '會員', 'test-only-password', 'challenge', 'answer', 'terms');
    $accounts->createMember(Secrets::uuid(), $member, 'test-only-hash');
    try {
        $accounts->createMember(Secrets::uuid(), $member, 'test-only-hash');
        throw new RuntimeException('Duplicate member was accepted.');
    } catch (AccountConflict) {
        $check('member duplicate becomes application exception', true);
    }
    try {
        $accounts->createAdmin(Secrets::uuid(), $input($run . 'invalid', $run . '-invalid@example.test', 'missing-role'), 'test-only-hash');
        throw new RuntimeException('Foreign key violation was accepted.');
    } catch (PDOException $error) {
        $check('foreign key violation is not reported as account conflict', ($error->errorInfo[1] ?? null) === 1452);
    }
} finally {
    $database->pdo->rollBack();
}

// 失敗前已寫入的帳號也必須回滾；與實際 Service 使用同一個 transaction adapter。
try {
    $transactions->run(function () use ($accounts, $input, $run): void {
        $accounts->createAdmin(Secrets::uuid(), $input($run, $run . '@example.test'), 'test-only-hash');
        $accounts->createAdmin(Secrets::uuid(), $input($run, $run . '-other@example.test'), 'test-only-hash');
    });
    throw new RuntimeException('Transaction unexpectedly committed.');
} catch (AccountConflict) {
    $check('translated exception rolls back the entire transaction', $accounts->byLogin(IdentityKind::Admin, $run) === null);
}
fwrite(STDOUT, $checks . " persistence checks passed.\n");
