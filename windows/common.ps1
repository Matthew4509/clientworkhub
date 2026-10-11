# SPDX-License-Identifier: PolyForm-Shield-1.0.0
# Client Work Hub, home edition: what the launcher, the password reset and the
# installer share. Dot-sourced (. "$PSScriptRoot\common.ps1"); defines functions only.
#
# Folders (an installed copy):
#   <install>\app\                      the application, this file is app\windows\common.ps1
#   <install>\runtime\php\              PHP
#   <install>\runtime\mariadb\          MariaDB
#   %APPDATA%\Client Work Hub\          YOUR DATA: config.php, storage\, database\  (kept on uninstall)
#   %LOCALAPPDATA%\Client Work Hub\     the app window's browser profile and the logs (safe to delete)
# CWH_RUNTIME_DIR, CWH_HOME_DATA and CWH_HOME_LOCAL override the last three (for tests).

$ErrorActionPreference = 'Stop'
Set-StrictMode -Version 2

function Get-CwhPaths {
    $app = Split-Path -Parent $PSScriptRoot
    $install = Split-Path -Parent $app
    $runtime = if ($env:CWH_RUNTIME_DIR) { $env:CWH_RUNTIME_DIR } else { Join-Path $install 'runtime' }
    $data = if ($env:CWH_HOME_DATA) { $env:CWH_HOME_DATA } else { Join-Path $env:APPDATA 'Client Work Hub' }
    $local = if ($env:CWH_HOME_LOCAL) { $env:CWH_HOME_LOCAL } else { Join-Path $env:LOCALAPPDATA 'Client Work Hub' }
    [pscustomobject]@{
        App      = $app
        Install  = $install
        Php      = Join-Path $runtime 'php\php.exe'
        PhpExt   = Join-Path $runtime 'php\ext'
        PhpIni   = Join-Path $app 'windows\php.ini'
        MariaBin = Join-Path $runtime 'mariadb\bin'
        Data     = $data
        Database = Join-Path $data 'database'
        Config   = Join-Path $data 'config.php'
        Secrets  = Join-Path $data 'database-root.cnf'
        State    = Join-Path $local 'running.json'
        Local    = $local
        Browser  = Join-Path $local 'browser'
        Logs     = Join-Path $local 'logs'
    }
}

# One command line from an argument list, quoting what needs it (Windows rules, as
# far as these arguments need them: no argument here ends in a backslash).
function Join-CwhArgs([string[]]$List) {
    return ($List | ForEach-Object {
        if ($_ -match '^[^\s"]+$') { $_ } else { '"' + ($_ -replace '"', '\"') + '"' }
    }) -join ' '
}

function Write-CwhLog([string]$Message) {
    $p = Get-CwhPaths
    New-Item -ItemType Directory -Force -Path $p.Logs | Out-Null
    $line = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + '  ' + $Message
    Add-Content -Path (Join-Path $p.Logs 'launcher.log') -Value $line -Encoding UTF8
}

function Show-CwhMessage([string]$Text, [string]$Kind = 'Information') {
    Add-Type -AssemblyName System.Windows.Forms
    [void][System.Windows.Forms.MessageBox]::Show($Text, 'Client Work Hub', 'OK', $Kind)
}

# A port nothing is listening on, preferring the one used last time.
function Get-CwhFreePort([int]$Preferred = 0) {
    if ($Preferred -gt 0) {
        try {
            $l = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, $Preferred)
            $l.Start(); $l.Stop(); return $Preferred
        } catch { }
    }
    $l = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
    $l.Start(); $port = $l.LocalEndpoint.Port; $l.Stop()
    return $port
}

function Test-CwhPort([int]$Port) {
    $c = [System.Net.Sockets.TcpClient]::new()
    try {
        $t = $c.ConnectAsync('127.0.0.1', $Port)
        return ($t.Wait(400) -and $c.Connected)
    } catch { return $false } finally { $c.Dispose() }
}

function Wait-CwhPort([int]$Port, [int]$Seconds, $Process = $null) {
    $until = (Get-Date).AddSeconds($Seconds)
    while ((Get-Date) -lt $until) {
        if (Test-CwhPort $Port) { return $true }
        if ($Process -and $Process.HasExited) { return $false }
        Start-Sleep -Milliseconds 250
    }
    return $false
}

function New-CwhSecret([int]$Bytes = 24) {
    $b = New-Object byte[] $Bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b)
    return -join ($b | ForEach-Object { $_.ToString('x2') })
}

function Read-CwhState {
    $p = Get-CwhPaths
    if (-not (Test-Path $p.State)) { return $null }
    try { return Get-Content $p.State -Raw | ConvertFrom-Json } catch { return $null }
}

function Save-CwhState($State) {
    $p = Get-CwhPaths
    New-Item -ItemType Directory -Force -Path $p.Local | Out-Null
    $State | ConvertTo-Json | Set-Content -Path $p.State -Encoding UTF8
}

# The environment every PHP process of ours runs with.
function Set-CwhPhpEnv([int]$DbPort) {
    $p = Get-CwhPaths
    $env:CWH_DATA_DIR = $p.Data
    $env:CWH_DB_PORT = [string]$DbPort
}

function Get-CwhPhpArgs {
    $p = Get-CwhPaths
    $sessions = Join-Path $p.Data 'sessions'
    New-Item -ItemType Directory -Force -Path $sessions | Out-Null
    return @('-n', '-c', $p.PhpIni,
             '-d', ('extension_dir=' + $p.PhpExt),
             '-d', ('session.save_path=' + $sessions),
             '-d', ('error_log=' + (Join-Path $p.Logs 'php.log')))
}

# Runs windows\home-cli.php; returns @{ Code; Out }.
function Invoke-CwhCli([string[]]$CliArgs, [int]$DbPort, [string]$StdIn = $null) {
    $p = Get-CwhPaths
    Set-CwhPhpEnv $DbPort
    $all = (Get-CwhPhpArgs) + @((Join-Path $p.App 'windows\home-cli.php')) + $CliArgs
    $psi = [System.Diagnostics.ProcessStartInfo]::new($p.Php)
    $psi.Arguments = Join-CwhArgs $all
    $psi.UseShellExecute = $false
    $psi.CreateNoWindow = $true
    $psi.RedirectStandardOutput = $true
    $psi.RedirectStandardError = $true
    $psi.RedirectStandardInput = $true
    $proc = [System.Diagnostics.Process]::Start($psi)
    if ($null -ne $StdIn) { $proc.StandardInput.WriteLine($StdIn) }
    $proc.StandardInput.Close()
    $out = $proc.StandardOutput.ReadToEnd() + $proc.StandardError.ReadToEnd()
    $proc.WaitForExit()
    return @{ Code = $proc.ExitCode; Out = $out.Trim() }
}

# -- the database -------------------------------------------------------------

function Initialize-CwhDatabase {
    # First run only: an empty MariaDB data folder with a random root password.
    $p = Get-CwhPaths
    if (Test-Path (Join-Path $p.Database 'mysql')) { return }
    New-Item -ItemType Directory -Force -Path $p.Data, $p.Logs | Out-Null
    if (Test-Path $p.Database) {
        # A half-made folder from a first run that was interrupted.
        Remove-Item -Recurse -Force $p.Database
    }
    $root = New-CwhSecret 18
    $log = Join-Path $p.Logs 'mariadb-install.log'
    $exe = Join-Path $p.MariaBin 'mariadb-install-db.exe'
    $proc = Start-Process -FilePath $exe -ArgumentList (Join-CwhArgs @("--datadir=$($p.Database)", "--password=$root")) `
        -WindowStyle Hidden -PassThru -Wait -RedirectStandardOutput $log -RedirectStandardError "$log.err"
    if ($proc.ExitCode -ne 0 -or -not (Test-Path (Join-Path $p.Database 'mysql'))) {
        throw "The database could not be created (see $log)."
    }
    Set-Content -Path $p.Secrets -Encoding ASCII -Value @('[client]', 'user=root', "password=$root", 'host=127.0.0.1')
}

function Start-CwhDatabase([int]$Port) {
    $p = Get-CwhPaths
    $ini = Join-Path $p.Database 'my.ini'
    $dbArgs = @()
    if (Test-Path $ini) { $dbArgs += "--defaults-file=$ini" }
    $dbArgs += @("--datadir=$($p.Database)", "--port=$Port", '--bind-address=127.0.0.1',
               '--skip-name-resolve', '--character-set-server=utf8mb4', '--collation-server=utf8mb4_unicode_ci',
               "--log-error=$(Join-Path $p.Logs 'mariadb.log')")
    $proc = Start-Process -FilePath (Join-Path $p.MariaBin 'mariadbd.exe') -ArgumentList (Join-CwhArgs $dbArgs) -WindowStyle Hidden -PassThru
    if (-not (Wait-CwhPort $Port 60 $proc)) {
        throw "The database did not start (see $(Join-Path $p.Logs 'mariadb.log'))."
    }
    return $proc
}

function Stop-CwhDatabase([int]$Port, $Process) {
    $p = Get-CwhPaths
    try {
        Start-Process -FilePath (Join-Path $p.MariaBin 'mariadb-admin.exe') `
            -ArgumentList (Join-CwhArgs @("--defaults-extra-file=$($p.Secrets)", "--port=$Port", 'shutdown')) `
            -WindowStyle Hidden -Wait | Out-Null
    } catch { Write-CwhLog "mariadb-admin shutdown failed: $_" }
    if ($Process) {
        if (-not $Process.WaitForExit(30000)) { Stop-Process -Id $Process.Id -Force -ErrorAction SilentlyContinue }
    }
}

# SQL as root, through the client and the secrets file (never on a command line).
function Invoke-CwhRootSql([int]$Port, [string]$Sql) {
    $p = Get-CwhPaths
    $tmp = Join-Path $p.Local ('sql-' + [guid]::NewGuid().ToString('n') + '.sql')
    Set-Content -Path $tmp -Value $Sql -Encoding UTF8
    try {
        $psi = [System.Diagnostics.ProcessStartInfo]::new((Join-Path $p.MariaBin 'mariadb.exe'))
        $psi.Arguments = Join-CwhArgs @("--defaults-extra-file=$($p.Secrets)", "--port=$Port")
        $psi.UseShellExecute = $false; $psi.CreateNoWindow = $true
        $psi.RedirectStandardInput = $true; $psi.RedirectStandardError = $true; $psi.RedirectStandardOutput = $true
        $proc = [System.Diagnostics.Process]::Start($psi)
        $proc.StandardInput.Write((Get-Content $tmp -Raw)); $proc.StandardInput.Close()
        $err = $proc.StandardError.ReadToEnd(); [void]$proc.StandardOutput.ReadToEnd()
        $proc.WaitForExit()
        if ($proc.ExitCode -ne 0) { throw "Database setup failed: $err" }
    } finally { Remove-Item -Force $tmp -ErrorAction SilentlyContinue }
}

# First run only: the application's own database, its user, and config.php.
function New-CwhConfig([int]$DbPort) {
    $p = Get-CwhPaths
    if (Test-Path $p.Config) { return }
    $appPass = New-CwhSecret 18
    Invoke-CwhRootSql $DbPort @"
CREATE DATABASE IF NOT EXISTS clientworkhub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'cwh'@'127.0.0.1' IDENTIFIED BY '$appPass';
CREATE USER IF NOT EXISTS 'cwh'@'localhost' IDENTIFIED BY '$appPass';
GRANT ALL PRIVILEGES ON clientworkhub.* TO 'cwh'@'127.0.0.1';
GRANT ALL PRIVILEGES ON clientworkhub.* TO 'cwh'@'localhost';
FLUSH PRIVILEGES;
"@
    $key = New-CwhSecret 32
    $config = @"
<?php
// Client Work Hub, home edition. Written by the launcher on first run.
// The database port changes with each start (CWH_DB_PORT); everything else stays.
return [
    'env'        => 'prod',
    'timezone'   => (static function (): string {
        `$f = __DIR__ . '/timezone.txt';
        `$z = is_file(`$f) ? trim((string) file_get_contents(`$f)) : '';
        return `$z !== '' ? `$z : 'UTC';
    })(),
    'base_url'   => 'http://127.0.0.1',
    'secret_key' => "$key",
    'db' => [
        'host'    => '127.0.0.1',
        'port'    => (int) (getenv('CWH_DB_PORT') ?: 3306),
        'name'    => 'clientworkhub',
        'user'    => 'cwh',
        'pass'    => '$appPass',
        'charset' => 'utf8mb4',
    ],
    'admin_emails' => [],
    'mail' => ['from' => '', 'from_name' => 'Client Work Hub', 'reply_to' => ''],
    'trust_proxy' => false,
    'dev_login'   => false,
    'edition'     => 'home',
];
"@
    New-Item -ItemType Directory -Force -Path (Join-Path $p.Data 'storage') | Out-Null
    [System.IO.File]::WriteAllText($p.Config, $config, [System.Text.UTF8Encoding]::new($false))
}

# -- the app window -----------------------------------------------------------

function Find-CwhEdge {
    foreach ($c in @("${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
                     "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe",
                     "$env:LOCALAPPDATA\Microsoft\Edge\Application\msedge.exe")) {
        if ($c -and (Test-Path $c)) { return $c }
    }
    try {
        $reg = Get-ItemProperty 'HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\msedge.exe' -ErrorAction Stop
        if ($reg.'(default)' -and (Test-Path $reg.'(default)')) { return $reg.'(default)' }
    } catch { }
    return $null
}

# True while a browser holds the app window's profile.
function Test-CwhBrowserOpen {
    $p = Get-CwhPaths
    $lock = Join-Path $p.Browser 'lockfile'
    if (-not (Test-Path $lock)) { return $false }
    try { $f = [System.IO.File]::Open($lock, 'Open', 'ReadWrite', 'None'); $f.Close(); return $false }
    catch { return $true }
}

function Open-CwhWindow([int]$Port, [string]$Path = 'admin/') {
    $p = Get-CwhPaths
    $url = "http://127.0.0.1:$Port/$Path"
    $edge = Find-CwhEdge
    if (-not $edge) { Start-Process $url; return $false }
    New-Item -ItemType Directory -Force -Path $p.Browser | Out-Null
    Start-Process -FilePath $edge -ArgumentList (Join-CwhArgs @("--app=$url", "--user-data-dir=$($p.Browser)",
        '--no-first-run', '--no-default-browser-check', '--window-size=1280,880')) | Out-Null
    return $true
}
