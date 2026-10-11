# SPDX-License-Identifier: PolyForm-Shield-1.0.0
# Client Work Hub, home edition: the uninstaller. Settings > Apps > Client Work Hub > Uninstall runs it.
#
# Removes the program folder, its shortcuts and its Settings > Apps entry. YOUR DATA IS KEPT:
# %APPDATA%\Client Work Hub (your clients, jobs, invoices, settings) is not touched, so installing again
# later carries on where you left off. The app window's browser profile and the logs in
# %LOCALAPPDATA%\Client Work Hub are removed (they hold nothing of yours).
#
#   -Yes   no questions (tests)
# The same CWH_* overrides as install.ps1.

param([switch]$Yes, [string]$From = '')

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2

# This script lives inside the folder it removes: run from a copy in %TEMP%.
if (-not $From) {
    $tmp = Join-Path $env:TEMP ('cwh-uninstall-' + [guid]::NewGuid().ToString('n') + '.ps1')
    Copy-Item -Force $PSCommandPath $tmp
    $passArgs = @('-NoProfile', '-ExecutionPolicy', 'Bypass', '-File', $tmp, '-From', (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)))
    if ($Yes) { $passArgs += '-Yes' }
    & (Join-Path ([Environment]::SystemDirectory) 'WindowsPowerShell\v1.0\powershell.exe') @passArgs
    exit $LASTEXITCODE
}

$Host.UI.RawUI.WindowTitle = 'Uninstall Client Work Hub'
Set-Location $env:TEMP
$name = 'Client Work Hub'
$target = $From
$startMenu = if ($env:CWH_START_MENU_DIR) { $env:CWH_START_MENU_DIR } else { Join-Path $env:APPDATA "Microsoft\Windows\Start Menu\Programs\$name" }
$desktop = if ($env:CWH_DESKTOP_DIR) { $env:CWH_DESKTOP_DIR } else { [Environment]::GetFolderPath('Desktop') }
$regKey = if ($env:CWH_UNINSTALL_KEY) { $env:CWH_UNINSTALL_KEY } else { 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\ClientWorkHub' }
$dataDir = if ($env:CWH_HOME_DATA) { $env:CWH_HOME_DATA } else { Join-Path $env:APPDATA $name }
$localDir = if ($env:CWH_HOME_LOCAL) { $env:CWH_HOME_LOCAL } else { Join-Path $env:LOCALAPPDATA $name }

function Say([string]$s) { Write-Host " $s" }
function Done([int]$code) {
    if (-not $Yes) { Write-Host ''; Read-Host ' Press Enter to close this window' | Out-Null }
    Remove-Item -Force $PSCommandPath -ErrorAction SilentlyContinue
    exit $code
}

Write-Host ''
Say 'Uninstall Client Work Hub'
Say '-------------------------'
if (-not (Test-Path (Join-Path $target 'app\windows\start.ps1'))) { Say "Client Work Hub is not in $target."; Done 1 }
Say "This removes the program from $target."
Say "Your data in $dataDir is KEPT."
if (-not $Yes) {
    $a = (Read-Host ' Uninstall? [Y/N]').Trim().ToLower()
    if ($a -notin @('y', 'yes')) { Say 'Nothing was changed.'; Done 0 }
}

# It must not be running.
while ($true) {
    $busy = $false
    try { $m = [System.Threading.Mutex]::OpenExisting('Local\ClientWorkHubHome'); $m.Dispose(); $busy = $true } catch { }
    if (-not $busy) { break }
    if ($Yes) { Say 'Client Work Hub is open; close it first.'; Done 1 }
    Read-Host ' Client Work Hub is open. Close its window, then press Enter' | Out-Null
}

# Shortcuts that open THIS install only.
$shell = New-Object -ComObject WScript.Shell
foreach ($lnk in @((Join-Path $desktop "$name.lnk"), (Join-Path $startMenu "$name.lnk"), (Join-Path $startMenu 'Reset my password.lnk'))) {
    if (Test-Path $lnk) {
        $s = $shell.CreateShortcut($lnk)
        if ($s.TargetPath.StartsWith($target, [StringComparison]::OrdinalIgnoreCase) -or
            $s.Arguments.IndexOf($target, [StringComparison]::OrdinalIgnoreCase) -ge 0) {
            Remove-Item -Force $lnk
        }
    }
}
if ((Test-Path $startMenu) -and -not (Get-ChildItem $startMenu)) { Remove-Item -Force $startMenu }

Remove-Item -Recurse -Force -Path $regKey -ErrorAction SilentlyContinue
Remove-Item -Recurse -Force -Path $target -ErrorAction SilentlyContinue
foreach ($try in 1..3) {
    foreach ($sub in @('browser', 'logs', 'running.json')) {
        Remove-Item -Recurse -Force -Path (Join-Path $localDir $sub) -ErrorAction SilentlyContinue
    }
    if (-not (Test-Path (Join-Path $localDir 'browser'))) { break }
    Start-Sleep -Seconds 2
}
if ((Test-Path $localDir) -and -not (Get-ChildItem $localDir)) { Remove-Item -Force $localDir }
if (Test-Path (Join-Path $localDir 'browser')) {
    Say "The app window's browser files in $localDir could not all be removed. They hold nothing of yours; delete the folder by hand if you like."
}

if (Test-Path $target) { Say "Some files in $target could not be removed (one may be open). Delete the folder by hand." }
else { Say 'Client Work Hub has been uninstalled.' }
Say "Your data is still in $dataDir. Installing again later carries on from it."
Done 0
