$ErrorActionPreference = 'Stop'
$repo = Split-Path -Parent $PSScriptRoot
$runtime = Join-Path (Split-Path -Parent $repo) 'SURGERY-APP-preview-runtime'
$phpExe = Join-Path $runtime 'php\php.exe'
$dbExe = Join-Path $runtime 'mariadb\mariadb-11.8.9-winx64\bin\mariadbd.exe'
$worker = Get-CimInstance Win32_Process | Where-Object {
    [string]::Equals($_.ExecutablePath, $phpExe, [StringComparison]::OrdinalIgnoreCase) -and
    $_.CommandLine -like '*demo_anesthesia.php*stream*'
}
foreach ($process in $worker) {
    Stop-Process -Id $process.ProcessId
    Write-Host 'Stopped synthetic vital-sign stream.'
}
$targets = @(
    @{ Port = 8000; Executable = $phpExe },
    @{ Port = 3308; Executable = $dbExe }
)
foreach ($target in $targets) {
    $listener = Get-NetTCPConnection -LocalAddress '127.0.0.1' -LocalPort $target.Port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if (-not $listener) { continue }
    $process = Get-CimInstance Win32_Process -Filter "ProcessId=$($listener.OwningProcess)"
    if (-not $process -or -not [string]::Equals($process.ExecutablePath, $target.Executable, [StringComparison]::OrdinalIgnoreCase)) {
        Write-Warning "Port $($target.Port) belongs to another process; leaving it running."
        continue
    }
    Stop-Process -Id $process.ProcessId
    Write-Host "Stopped preview process on port $($target.Port)."
}
