$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $projectRoot '.env'
if (-not (Test-Path -LiteralPath $envPath)) { throw 'Run setup-env.ps1 first.' }
$settings = @{}
foreach ($line in [IO.File]::ReadAllLines($envPath)) {
    if ($line -match '^([A-Z_]+)=(.*)$') { $settings[$Matches[1]] = $Matches[2] }
}
$adminEmail = $settings['ADMIN_EMAIL']
$initialPassword = $settings['ADMIN_INITIAL_PASSWORD']
if ([string]::IsNullOrWhiteSpace($adminEmail) -or [string]::IsNullOrWhiteSpace($initialPassword)) {
    throw 'Set ADMIN_EMAIL and a unique ADMIN_INITIAL_PASSWORD in your ignored .env.'
}
# 密碼只透過 stdin 傳遞，不會出現在命令參數、終端輸出或版本庫中。
"$adminEmail`n$initialPassword`n$initialPassword" | docker compose exec -T app php bin/console.php admin:init
if ($LASTEXITCODE -ne 0) { throw 'Admin initialization failed.' }
