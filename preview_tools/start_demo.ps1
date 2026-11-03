$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
$runtime = Join-Path (Split-Path -Parent $repo) 'SURGERY-APP-preview-runtime'
$phpExe = Join-Path $runtime 'php\php.exe'
$dbExe = Join-Path $runtime 'mariadb\mariadb-11.8.9-winx64\bin\mariadbd.exe'
$data = Join-Path $runtime 'data'
$demoScript = Join-Path $PSScriptRoot 'demo_anesthesia.php'
$screenshotFixture = Join-Path $PSScriptRoot 'seed_screenshot_cases.php'
if (-not (Test-Path -LiteralPath $phpExe) -or -not (Test-Path -LiteralPath $dbExe) -or -not (Test-Path -LiteralPath $data)) {
    throw "Preview runtime missing: $runtime. Follow preview_tools/README.md to recreate it."
}
function Assert-Preview-Port([int]$port, [string]$expectedExe) {
    $listener = Get-NetTCPConnection -LocalAddress '127.0.0.1' -LocalPort $port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($listener) {
        $process = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)"
        if (-not $process -or -not [string]::Equals($process.ExecutablePath, $expectedExe, [StringComparison]::OrdinalIgnoreCase)) {
            throw "Port $port is in use by another process. Stop that process or change the preview port."
        }
        return $true
    }
    return $false
}
function Get-Demo-Worker {
    Get-CimInstance Win32_Process | Where-Object {
        [string]::Equals($_.ExecutablePath, $phpExe, [StringComparison]::OrdinalIgnoreCase) -and
        $_.CommandLine -like '*demo_anesthesia.php*stream*'
    } | Select-Object -First 1
}
if (-not (Assert-Preview-Port 3308 $dbExe)) {
    $dbArgs = @("--defaults-file=$data\my.ini", "--datadir=$data", '--port=3308', '--bind-address=127.0.0.1', '--skip-name-resolve')
    Start-Process -FilePath $dbExe -ArgumentList $dbArgs -WorkingDirectory (Split-Path -Parent $dbExe) -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime 'mariadb.stdout.log') -RedirectStandardError (Join-Path $runtime 'mariadb.stderr.log')
    Start-Sleep -Seconds 2
    if (-not (Assert-Preview-Port 3308 $dbExe)) { throw 'MariaDB did not start. Check the runtime logs.' }
}
$worker = Get-Demo-Worker
if (-not $worker) {
    & $phpExe $demoScript seed
    if ($LASTEXITCODE -ne 0) { throw 'Could not seed the synthetic anesthesia case.' }
    $env:SURGERY_PREVIEW_FIXTURES = '1'
    try {
        & $phpExe $screenshotFixture
        if ($LASTEXITCODE -ne 0) { throw 'Could not seed the other synthetic screenshot cases.' }
    } finally {
        Remove-Item Env:\SURGERY_PREVIEW_FIXTURES -ErrorAction SilentlyContinue
    }
}
if (-not (Assert-Preview-Port 8000 $phpExe)) {
    Start-Process -FilePath $phpExe -ArgumentList @('-S', '127.0.0.1:8000', '-t', '.') -WorkingDirectory $repo -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime 'php.stdout.log') -RedirectStandardError (Join-Path $runtime 'php.stderr.log')
    Start-Sleep -Seconds 2
    if (-not (Assert-Preview-Port 8000 $phpExe)) { throw 'PHP did not start. Check the runtime logs.' }
}
if (-not $worker) {
    Start-Process -FilePath $phpExe -ArgumentList @($demoScript, 'stream') -WorkingDirectory $repo -WindowStyle Hidden -RedirectStandardOutput (Join-Path $runtime 'demo-stream.stdout.log') -RedirectStandardError (Join-Path $runtime 'demo-stream.stderr.log')
    Start-Sleep -Seconds 1
    if (-not (Get-Demo-Worker)) { throw 'Synthetic vital-sign stream did not start. Check the runtime logs.' }
}
$response = Invoke-WebRequest -Uri 'http://127.0.0.1:8000/login.php' -UseBasicParsing
if ($response.StatusCode -ne 200) { throw "Login page returned HTTP $($response.StatusCode)" }
Write-Host 'Local preview: http://127.0.0.1:8000/login.php'
Write-Host 'Anesthesia live demo: http://127.0.0.1:8000/views/anesthesiologist/live_cockpit.php?id=900001'
Write-Host 'Sign in using the Anesthesiologist demo role before opening the live demo.'
Write-Host 'To regenerate PNGs: python .\preview_tools\capture_linkedin.py'
