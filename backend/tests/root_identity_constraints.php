<?php

declare(strict_types=1);

/** 在真實 MariaDB 逐位元組驗證 root 管理員的保護限制，所有探測都會回滾。 */
require dirname(__DIR__) . '/vendor/autoload.php';
$config = new Yinghua\Config();
if ($config->string('APP_ENV') !== 'test' || $config->string('DB_DATABASE') !== 'yinghua_test') {
    throw new RuntimeException('Isolated test database required.');
}
$database = Yinghua\Repositories\Database::connect($config);
$checks = 0;
$mutations = [
    "id=UPPER(id)", "account='ADMIN'", "account=CONCAT(account,' ')", "account='ａｄｍｉｎ'",
    'email=UPPER(email)', "email=CONCAT(email,' ')", "display_name=CONCAT(display_name,' ')",
    "role_id='ROOT'", "role_id=CONCAT(role_id,' ')", "status='disabled'", 'is_root=0',
    'deleted_at=UTC_TIMESTAMP()', 'version=version+1', 'permissions_version=permissions_version+1',
];
foreach ($mutations as $mutation) {
    $database->pdo->beginTransaction();
    try {
        $blocked = false;
        try {
            $database->execute('UPDATE admin_users SET ' . $mutation . ' WHERE is_root=1');
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) !== 1644) {
                throw $error;
            }
            $blocked = true;
        }
        if (!$blocked) {
            throw new RuntimeException('Root identity mutation was accepted: ' . $mutation);
        }
        ++$checks;
    } finally {
        $database->pdo->rollBack();
    }
}
foreach (['last_login_at=UTC_TIMESTAMP()', 'password_hash=password_hash'] as $mutation) {
    $database->pdo->beginTransaction();
    try {
        $database->execute('UPDATE admin_users SET ' . $mutation . ' WHERE is_root=1');
        ++$checks;
    } finally {
        $database->pdo->rollBack();
    }
}
fwrite(STDOUT, $checks . " root identity checks passed; all mutations rolled back.\n");
