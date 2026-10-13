$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$settings = @{}
foreach ($line in [IO.File]::ReadAllLines((Join-Path $projectRoot '.env'))) {
    if ($line -match '^([A-Z_]+)=(.*)$') { $settings[$Matches[1]] = $Matches[2] }
}
$dbUser = $settings['DB_USERNAME']
if ($dbUser -notmatch '^[a-zA-Z][a-zA-Z0-9_]*$') { throw 'Unsupported database username.' }
# [E-15] 固定獨立測試庫；不 DROP、TRUNCATE 或清空 yinghua。
$testSql = "CREATE DATABASE IF NOT EXISTS yinghua_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON yinghua_test.* TO '$dbUser'@'%';"
$testSql | docker compose exec -T db sh -c 'MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot'
if ($LASTEXITCODE -ne 0) { throw 'Test database setup failed.' }
docker compose --profile test --profile mail up -d web-test mailpit
if ($LASTEXITCODE -ne 0) { throw 'Test containers failed to start.' }
docker compose exec -T app-test php bin/console.php db:migrate
if ($LASTEXITCODE -ne 0) { throw 'Test schema setup failed.' }
docker compose exec -T app-test php bin/console.php db:seed
if ($LASTEXITCODE -ne 0) { throw 'Test role seed failed.' }
