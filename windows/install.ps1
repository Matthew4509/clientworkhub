# SPDX-License-Identifier: PolyForm-Shield-1.0.0
# Client Work Hub, home edition: the installer. "Install Client Work Hub.cmd" in the unzipped download runs it.
#
# For this Windows user only, no administrator needed. It copies the application and its PHP and MariaDB to
# %LOCALAPPDATA%\Programs\Client Work Hub, builds Client Work Hub.exe there, adds it to the Start menu (and the
# desktop if wanted) with "Reset my password" beside it, and lists it in Settings > Apps, where Uninstall removes
# it again. Already installed: it replaces the program and leaves your data alone. Your data lives in
# %APPDATA%\Client Work Hub and nothing here changes it.
#
#   -Yes          answer yes to every question (desktop shortcut included), for tests
#   -NoDesktop    no desktop shortcut
#   -NoOpen       do not open Client Work Hub at the end
# CWH_INSTALL_DIR, CWH_START_MENU_DIR, CWH_DESKTOP_DIR and CWH_UNINSTALL_KEY override where things go (tests).

param([switch]$Yes, [switch]$NoDesktop, [switch]$NoOpen)

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2
$Host.UI.RawUI.WindowTitle = 'Client Work Hub setup'

$source = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)     # the unzipped folder
$name = 'Client Work Hub'
$target = if ($env:CWH_INSTALL_DIR) { $env:CWH_INSTALL_DIR } else { Join-Path $env:LOCALAPPDATA "Programs\$name" }
$startMenu = if ($env:CWH_START_MENU_DIR) { $env:CWH_START_MENU_DIR } else { Join-Path $env:APPDATA "Microsoft\Windows\Start Menu\Programs\$name" }
$desktop = if ($env:CWH_DESKTOP_DIR) { $env:CWH_DESKTOP_DIR } else { [Environment]::GetFolderPath('Desktop') }
$regKey = if ($env:CWH_UNINSTALL_KEY) { $env:CWH_UNINSTALL_KEY } else { 'HKCU:\Software\Microsoft\Windows\CurrentVersion\Uninstall\ClientWorkHub' }
$dataDir = if ($env:CWH_HOME_DATA) { $env:CWH_HOME_DATA } else { Join-Path $env:APPDATA $name }

function Say([string]$s) { Write-Host " $s" }
function Ask([string]$q) {
    if ($Yes) { return $true }
    while ($true) {
        $a = (Read-Host " $q [Y/N]").Trim().ToLower()
        if ($a -in @('y', 'yes')) { return $true }
        if ($a -in @('n', 'no')) { return $false }
    }
}
function Stop-Setup([string]$s) { Write-Host ''; Say $s; exit 1 }

Write-Host ''
Say 'Client Work Hub (home edition) setup'
Say '------------------------------------'

foreach ($need in @('app\windows\start.ps1', 'app\lib\bootstrap.php', 'runtime\php\php.exe', 'runtime\mariadb\bin\mariadbd.exe')) {
    if (-not (Test-Path (Join-Path $source $need))) {
        Stop-Setup "This folder is incomplete ($need is missing). Unzip the whole download again (right-click the zip, Extract All), then run Install Client Work Hub.cmd from the unzipped folder."
    }
}
$bootstrap = Get-Content (Join-Path $source 'app\lib\bootstrap.php') -Raw
$version = if ($bootstrap -match "const CM_VERSION = '([0-9.]+)'") { $Matches[1] } else { '' }
Say "Version $version, for $env:USERNAME on this PC."
Say "It goes in $target"
Say "Your data stays in $dataDir"
Write-Host ''

# The Visual C++ runtime: PHP needs it, and a fresh Windows does not always have it.
$sys = [Environment]::SystemDirectory
if (-not (Test-Path (Join-Path $sys 'vcruntime140.dll')) -or -not (Test-Path (Join-Path $sys 'msvcp140.dll'))) {
    Say 'This PC is missing the Microsoft Visual C++ Runtime, which Client Work Hub needs.'
    Say 'Setup can download it from Microsoft (aka.ms/vs/17/release/vc_redist.x64.exe, about 25 MB)'
    Say 'and run it. Windows will ask for permission, because it installs for every user.'
    if (-not (Ask 'Download and install it now?')) {
        Stop-Setup 'Setup stopped. Install the "Visual C++ Redistributable for Visual Studio 2015-2022 (x64)" from Microsoft, then run this again.'
    }
    $vc = Join-Path $env:TEMP 'vc_redist.x64.exe'
    try {
        [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
        Invoke-WebRequest -UseBasicParsing -Uri 'https://aka.ms/vs/17/release/vc_redist.x64.exe' -OutFile $vc
        $sig = Get-AuthenticodeSignature $vc
        if ($sig.Status -ne 'Valid' -or $sig.SignerCertificate.Subject -notmatch 'O=Microsoft Corporation') {
            Stop-Setup 'The download did not carry Microsoft''s signature, so it was not run.'
        }
        $p = Start-Process -FilePath $vc -ArgumentList '/install', '/passive', '/norestart' -Verb RunAs -Wait -PassThru
        if ($p.ExitCode -notin @(0, 1638, 3010)) { Stop-Setup "The Visual C++ Runtime installer ended with code $($p.ExitCode)." }
    } catch {
        Stop-Setup "The Visual C++ Runtime could not be installed: $_"
    } finally { Remove-Item -Force $vc -ErrorAction SilentlyContinue }
}

$update = Test-Path (Join-Path $target 'app\lib\bootstrap.php')
if ($update) {
    $old = Get-Content (Join-Path $target 'app\lib\bootstrap.php') -Raw
    $oldVersion = if ($old -match "const CM_VERSION = '([0-9.]+)'") { $Matches[1] } else { '?' }
    if (-not (Ask "Client Work Hub $oldVersion is already installed. Replace it with ${version}? (Your data is kept.)")) { Stop-Setup 'Nothing was changed.' }
    # It must not be running while its files are replaced.
    $state = Join-Path $env:LOCALAPPDATA "$name\running.json"
    while ($true) {
        $busy = $false
        try { $m = [System.Threading.Mutex]::OpenExisting('Local\ClientWorkHubHome'); $m.Dispose(); $busy = $true } catch { }
        if (-not $busy) { break }
        if ($Yes) { Stop-Setup 'Client Work Hub is open. Close its window, then run setup again.' }
        Read-Host ' Client Work Hub is open. Close its window, then press Enter' | Out-Null
    }
} else {
    if (-not (Ask "Install Client Work Hub ${version}?")) { Stop-Setup 'Nothing was changed.' }
}
$wantDesktop = -not $NoDesktop -and (Ask 'Put a shortcut on the desktop too?')

# Copy. robocopy /MIR makes the installed copy exactly the new one (files a release dropped go too).
Say 'Copying...'
New-Item -ItemType Directory -Force -Path $target | Out-Null
foreach ($part in @('app', 'runtime')) {
    $null = & robocopy (Join-Path $source $part) (Join-Path $target $part) /MIR /NFL /NDL /NJH /NJS /NP /R:2 /W:1
    if ($LASTEXITCODE -ge 8) { Stop-Setup "Copying $part failed (robocopy code $LASTEXITCODE)." }
}
# Downloaded files carry the internet mark, and Windows would ask before every start of php.exe and the database.
Get-ChildItem -Recurse -File $target | Unblock-File

# The program the shortcuts open: built here, with Windows' own compiler.
$exe = Join-Path $target "$name.exe"
$ico = Join-Path $target 'app\windows\cwh.ico'
$csc = Join-Path ([Runtime.InteropServices.RuntimeEnvironment]::GetRuntimeDirectory()) 'csc.exe'
$launchTarget = $exe; $launchArgs = ''
if (Test-Path $csc) {
    Remove-Item -Force $exe -ErrorAction SilentlyContinue
    $out = & $csc -nologo -target:winexe -optimize+ "-win32icon:$ico" "-out:$exe" -r:System.Windows.Forms.dll (Join-Path $target 'app\windows\launcher.cs') 2>&1
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $exe)) { Say "Note: the program could not be built ($out); the shortcuts start PowerShell instead." }
}
if (-not (Test-Path $exe)) {
    $launchTarget = Join-Path $sys 'WindowsPowerShell\v1.0\powershell.exe'
    $launchArgs = "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File `"$(Join-Path $target 'app\windows\start.ps1')`""
}

function New-Shortcut([string]$lnk, [string]$path, [string]$arguments, [string]$desc) {
    $s = (New-Object -ComObject WScript.Shell).CreateShortcut($lnk)
    $s.TargetPath = $path
    $s.Arguments = $arguments
    $s.WorkingDirectory = $target
    $s.IconLocation = "$ico,0"
    $s.Description = $desc
    $s.Save()
}
New-Item -ItemType Directory -Force -Path $startMenu | Out-Null
$ps = Join-Path $sys 'WindowsPowerShell\v1.0\powershell.exe'
New-Shortcut (Join-Path $startMenu "$name.lnk") $launchTarget $launchArgs 'Client Work Hub'
New-Shortcut (Join-Path $startMenu 'Reset my password.lnk') $ps `
    "-NoProfile -ExecutionPolicy Bypass -File `"$(Join-Path $target 'app\windows\reset-password.ps1')`"" 'Set a new password for Client Work Hub on this PC'
$deskLnk = Join-Path $desktop "$name.lnk"
if ($wantDesktop) { New-Shortcut $deskLnk $launchTarget $launchArgs 'Client Work Hub' }

# Settings > Apps.
$kb = [int]((Get-ChildItem -Recurse -File $target | Measure-Object -Sum Length).Sum / 1KB)
New-Item -Force -Path $regKey | Out-Null
$uninstall = "`"$ps`" -NoProfile -ExecutionPolicy Bypass -File `"$(Join-Path $target 'app\windows\uninstall.ps1')`""
$values = [ordered]@{ DisplayName = $name; DisplayVersion = $version; Publisher = $name; DisplayIcon = "$ico,0";
                      InstallLocation = $target; UninstallString = $uninstall; NoModify = 1; NoRepair = 1;
                      EstimatedSize = $kb; DesktopShortcut = [int]$wantDesktop }
foreach ($k in $values.Keys) {
    $type = if ($values[$k] -is [int]) { 'DWord' } else { 'String' }
    New-ItemProperty -Force -Path $regKey -Name $k -Value $values[$k] -PropertyType $type | Out-Null
}

Write-Host ''
Say "Client Work Hub $version is installed. Open it from the Start menu$(if ($wantDesktop) { ' or the desktop' })."
Say ('Uninstall it from Settings > Apps; your data stays in ' + $dataDir + '.')
if (-not $NoOpen -and (Ask 'Open it now?')) {
    if ($launchArgs) { Start-Process -FilePath $launchTarget -ArgumentList $launchArgs } else { Start-Process -FilePath $launchTarget }
}
exit 0
