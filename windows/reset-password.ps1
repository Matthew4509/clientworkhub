# SPDX-License-Identifier: PolyForm-Shield-1.0.0
# Client Work Hub, home edition: "Reset my password" in the Start menu.
# Sets a new password for the account on this PC, turns two-step sign-in off and signs
# every device out. Nothing is sent anywhere. Runs in a visible window, because it asks.

. "$PSScriptRoot\common.ps1"
$paths = Get-CwhPaths
$Host.UI.RawUI.WindowTitle = 'Client Work Hub: reset my password'

function Finish([int]$Code) {
    Write-Host ''
    Read-Host 'Press Enter to close this window' | Out-Null
    exit $Code
}

Write-Host ''
Write-Host ' Client Work Hub: reset my password'
Write-Host ' ----------------------------------'
if (-not (Test-Path $paths.Config)) {
    Write-Host ' Client Work Hub has not been set up on this PC yet. Open it from the Start menu first.'
    Finish 1
}

# The database has to be running for this. Use the running copy, or start one just for this.
$state = Read-CwhState
$db = $null
$dbPort = 0
if ($state -and (Test-CwhPort ([int]$state.db_port))) {
    $dbPort = [int]$state.db_port
} else {
    # Hold the launcher's lock while the database is ours, so it is not started twice.
    $mutex = [System.Threading.Mutex]::new($false, 'Local\ClientWorkHubHome')
    $held = $false
    try { $held = $mutex.WaitOne(0) } catch [System.Threading.AbandonedMutexException] { $held = $true }
    if (-not $held) { Write-Host ' Client Work Hub is starting or closing. Wait a moment and run this again.'; Finish 1 }
    $dbPort = Get-CwhFreePort 0
    try { $db = Start-CwhDatabase $dbPort } catch { Write-Host " $_"; $mutex.ReleaseMutex(); Finish 1 }
}

try {
    $acc = Invoke-CwhCli @('accounts') $dbPort
    $emails = @($acc.Out -split "`r?`n" | Where-Object { $_.Trim() -ne '' } | ForEach-Object { ($_ -split ' ')[0] })
    if ($emails.Count -eq 0) { Write-Host ' There is no account yet. Open Client Work Hub to create one.'; Finish 1 }
    $email = $emails[0]
    if ($emails.Count -gt 1) {
        Write-Host ' The accounts on this PC:'
        for ($i = 0; $i -lt $emails.Count; $i++) { Write-Host ("   {0}. {1}" -f ($i + 1), $emails[$i]) }
        $pick = Read-Host ' Which one (number)'
        $n = 0
        if (-not [int]::TryParse($pick, [ref]$n) -or $n -lt 1 -or $n -gt $emails.Count) { Write-Host ' No change made.'; Finish 1 }
        $email = $emails[$n - 1]
    }
    Write-Host " Account: $email"
    Write-Host ' A new password: at least 10 characters. Three or four unrelated words work well.'
    while ($true) {
        $a = Read-Host ' New password' -AsSecureString
        $b = Read-Host ' The same again' -AsSecureString
        $pa = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($a))
        $pb = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($b))
        if ($pa -ne $pb) { Write-Host ' Those two were not the same. Try again.'; continue }
        $r = Invoke-CwhCli @('reset-password', $email) $dbPort $pa
        $pa = $null; $pb = $null
        if ($r.Code -eq 3) { Write-Host " $($r.Out)"; continue }
        Write-Host " $($r.Out)"
        if ($r.Code -eq 0) { break } else { Finish 1 }
    }
} finally {
    if ($db) { Stop-CwhDatabase $dbPort $db; $mutex.ReleaseMutex() }
}
Finish 0
