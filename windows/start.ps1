# SPDX-License-Identifier: PolyForm-Shield-1.0.0
# Client Work Hub, home edition: the launcher. The Start-menu and desktop shortcuts run
#   powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "<install>\app\windows\start.ps1"
#
# It starts the database and the application on this PC only (127.0.0.1, ports chosen
# free), opens the application in its own Edge window, and when that window closes it
# stops both again. The first run makes the database and config.php in the data folder;
# the application then asks for the account. Opened again while running, it only opens
# another window.
#
#   -NoWindow   start, write the address to <local>\running.json, and stop when a file
#               called stop-request appears beside it (tests)

param([switch]$NoWindow)

. "$PSScriptRoot\common.ps1"
$paths = Get-CwhPaths

# One copy at a time, per Windows user.
$mutex = [System.Threading.Mutex]::new($false, 'Local\ClientWorkHubHome')
$mine = $false
try { $mine = $mutex.WaitOne(0) } catch [System.Threading.AbandonedMutexException] { $mine = $true }
if (-not $mine) {
    $state = Read-CwhState
    if ($state -and (Test-CwhPort ([int]$state.web_port))) {
        if (-not $NoWindow) { [void](Open-CwhWindow ([int]$state.web_port)) }
        exit 0
    }
    Show-CwhMessage 'Client Work Hub is still starting or closing. Try again in a few seconds.'
    exit 1
}

$db = $null
$web = $null
try {
    foreach ($need in @($paths.Php, (Join-Path $paths.MariaBin 'mariadbd.exe'))) {
        if (-not (Test-Path $need)) { throw "A part of Client Work Hub is missing: $need. Install it again." }
    }
    New-Item -ItemType Directory -Force -Path $paths.Data, $paths.Local, $paths.Logs | Out-Null
    Write-CwhLog "start $($paths.App)"

    $last = Read-CwhState
    $dbPort = Get-CwhFreePort $(if ($last) { [int]$last.db_port } else { 0 })
    $webPort = Get-CwhFreePort $(if ($last) { [int]$last.web_port } else { 0 })
    if ($webPort -eq $dbPort) { $webPort = Get-CwhFreePort 0 }

    Initialize-CwhDatabase
    $db = Start-CwhDatabase $dbPort
    New-CwhConfig $dbPort

    $mig = Invoke-CwhCli @('migrate') $dbPort
    Write-CwhLog "migrate: $($mig.Out)"
    if ($mig.Code -ne 0) { throw "The database could not be brought up to date: $($mig.Out)" }

    Set-CwhPhpEnv $dbPort
    $phpArgs = (Get-CwhPhpArgs) + @('-S', "127.0.0.1:$webPort", '-t', $paths.App,
                                    (Join-Path $paths.App 'windows\router.php'))
    $web = Start-Process -FilePath $paths.Php -ArgumentList (Join-CwhArgs $phpArgs) -WindowStyle Hidden -PassThru `
        -RedirectStandardOutput (Join-Path $paths.Logs 'server.log') -RedirectStandardError (Join-Path $paths.Logs 'server.err.log')
    if (-not (Wait-CwhPort $webPort 30 $web)) { throw "The application did not start (see $($paths.Logs))." }

    Save-CwhState ([pscustomobject]@{ db_port = $dbPort; web_port = $webPort; db_pid = $db.Id;
                                      web_pid = $web.Id; started = (Get-Date).ToString('s') })
    Write-CwhLog "running on 127.0.0.1:$webPort (database $dbPort)"

    if ($NoWindow) {
        $stopFile = Join-Path $paths.Local 'stop-request'
        Remove-Item -Force $stopFile -ErrorAction SilentlyContinue
        while (-not (Test-Path $stopFile)) { Start-Sleep -Milliseconds 500 }
        Remove-Item -Force $stopFile -ErrorAction SilentlyContinue
    } elseif (Open-CwhWindow $webPort) {
        # Wait for the window to take the profile, then for every window on it to close.
        $until = (Get-Date).AddSeconds(30)
        while (-not (Test-CwhBrowserOpen) -and (Get-Date) -lt $until) { Start-Sleep -Milliseconds 500 }
        while (Test-CwhBrowserOpen) { Start-Sleep -Seconds 2 }
    } else {
        Show-CwhMessage ("Client Work Hub is open in your browser at http://127.0.0.1:$webPort/`n`n" +
                         "Microsoft Edge was not found, so it could not have a window of its own. " +
                         "Press OK when you have finished, and Client Work Hub will stop.")
    }
} catch {
    Write-CwhLog "error: $_"
    if ($NoWindow) { Write-Output "ERROR: $_" }
    else { Show-CwhMessage ("Client Work Hub could not start.`n`n$_`n`nThe log is in $($paths.Logs).") 'Error' }
} finally {
    if ($web -and -not $web.HasExited) { Stop-Process -Id $web.Id -Force -ErrorAction SilentlyContinue }
    if ($db -and -not $db.HasExited) { Stop-CwhDatabase $dbPort $db }
    Write-CwhLog 'stopped'
    $mutex.ReleaseMutex()
}
