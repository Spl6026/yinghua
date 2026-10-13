$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $projectRoot '.env'
if (Test-Path -LiteralPath $envFile) {
    Write-Output 'Existing .env retained.'
    exit 0
}
function New-Secret {
    $secretBytes = New-Object byte[] 32
    $generator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($secretBytes) } finally { $generator.Dispose() }
    return [Convert]::ToBase64String($secretBytes)
}
$template = [IO.File]::ReadAllText((Join-Path $projectRoot '.env.example'))
$template = $template.Replace('APP_KEY=', ('APP_KEY=' + (New-Secret)))
$template = $template.Replace('DB_PASSWORD=', ('DB_PASSWORD=' + (New-Secret)))
$template = $template.Replace('DB_ROOT_PASSWORD=', ('DB_ROOT_PASSWORD=' + (New-Secret)))
[IO.File]::WriteAllText($envFile, $template, (New-Object Text.UTF8Encoding($false)))
Write-Output '.env created with random local secrets; no secrets were printed.'
