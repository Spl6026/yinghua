param([switch]$Stop, [switch]$BuildOnly)
$ErrorActionPreference = 'Stop'
$readerCompose = Join-Path $PSScriptRoot 'compose.yaml'
$readerArgs = @('compose', '--project-directory', $PSScriptRoot, '-f', $readerCompose)
if ($Stop) {
    & docker @readerArgs stop
    if ($LASTEXITCODE -ne 0) { throw 'Isolated desktop stop failed' }
    exit
}
$readerPasswordFile = Join-Path $PSScriptRoot '.env.vnc-password'
if (-not (Test-Path -LiteralPath $readerPasswordFile)) {
    # VNC 密碼只取前八個字元；這是只綁定 loopback、用完即丟的密碼。
    $readerPassword = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(6))
    [IO.File]::WriteAllText($readerPasswordFile, $readerPassword + "`n")
}
& docker @readerArgs build
if ($LASTEXITCODE -ne 0) { throw 'Isolated desktop build failed' }
if ($BuildOnly) { exit }
& docker @readerArgs up -d --no-build --force-recreate --wait --wait-timeout 60
if ($LASTEXITCODE -ne 0) { throw 'Isolated desktop start failed' }
Write-Output 'Isolated reader desktop: http://127.0.0.1:6091/vnc.html'
Write-Output 'Credentials stay in the ignored tools/accessibility/.env.vnc-password file.'
