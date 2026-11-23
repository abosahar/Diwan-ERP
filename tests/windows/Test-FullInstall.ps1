#Requires -Version 5.1
<#
.SYNOPSIS
    End-to-end test of the Windows installer, only for a throwaway Windows (a CI runner, or a virtual
    machine made for it): it installs Diwan in C:\Diwan (or -Root), damages it on purpose, installs
    again and updates it.

.DESCRIPTION
    1. Runs this checkout's Diwan-Setup.bat /local (with its install.ps1) from a folder whose name has
       spaces and brackets, like a second download "Diwan-Setup (1).bat", with the latest release.
       With -Root (DIWAN_DIR) it installs there: -Root D:\Diwan is on another drive than %TEMP%.
    2. Checks the exit code, Diwan answering, the database service, the tasks, Caddy, the firewall
       rule, the shortcuts and their English names, the queues of the worker, the install log.
    3. Leaves what a run again must keep or repair: a table and an uploaded file, a caddy.exe that
       does not run (as an interrupted download leaves it), the Arabic shortcuts of earlier installers.
    4. Runs the setup again and checks everything again: the data, settings and passwords are the
       same, Caddy works again, the earlier shortcuts made way for the English ones.
    5. The daily update, with this checkout's local-update.ps1 and local-tools.ps1 in the running release
       (as the next release brings them): the release reports an older version and the task "Diwan
       Update" starts, as the dashboard starts it; it installs the latest release again next to the
       running one and switches to it. Then the Start menu shortcut "Update Diwan" finds it up to date.
        powershell -NoProfile -ExecutionPolicy Bypass -File tests\windows\Test-FullInstall.ps1 -ThrowawayWindows [-Root D:\Diwan]
    Keep this file UTF-8 with BOM (Windows PowerShell 5.1 reads its Arabic names otherwise).
#>
param([switch]$ThrowawayWindows, [string]$Root = 'C:\Diwan')

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
if (-not $ThrowawayWindows) {
    throw "This test installs Diwan in $Root and damages it on purpose: run it only on a throwaway Windows, with -ThrowawayWindows."
}
if (Test-Path -LiteralPath $Root) { throw "$Root exists: this is not a fresh throwaway Windows." }
# The installer's own folder when it is C:\Diwan, DIWAN_DIR otherwise.
if ($Root -eq 'C:\Diwan') { Remove-Item Env:\DIWAN_DIR -ErrorAction SilentlyContinue } else { $env:DIWAN_DIR = $Root }

$repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$folder = Join-Path $env:PUBLIC 'Diwan setup (test)'
$bat = Join-Path $folder 'Diwan-Setup (1).bat'
$failures = New-Object 'System.Collections.Generic.List[string]'

function Check($ok, $what) {
    if ($ok) { Write-Host "  ok    $what" -ForegroundColor Green }
    else { Write-Host "  FAIL  $what" -ForegroundColor Red; $failures.Add($what) }
}

# Starts $file with $arguments and waits for it alone: Start-Process -Wait of Windows PowerShell 5.1 also
# waits for what it starts and leaves running (the browser the installer opens at its end). Its exit code.
function Invoke-Program($file, $arguments, $timeoutMinutes, $stdin, $stdout) {
    $options = @{ FilePath = $file; ArgumentList = $arguments; PassThru = $true; NoNewWindow = $true }
    if ($stdin) { $options.RedirectStandardInput = $stdin }
    if ($stdout) { $options.RedirectStandardOutput = $stdout }
    $process = Start-Process @options
    $null = $process.Handle # without it, ExitCode stays empty once the process ends
    if (-not $process.WaitForExit($timeoutMinutes * 60000)) {
        Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
        throw "$file $arguments did not end within $timeoutMinutes minutes."
    }
    return $process.ExitCode
}

# cmd /c ""<bat>" /local < NUL": the pause at its end reads NUL and returns at once.
function Invoke-Setup {
    Write-Host ''
    Write-Host "==== $bat /local (install folder $Root)" -ForegroundColor Cyan
    $code = Invoke-Program $env:ComSpec ('/d /c ""' + $bat + '" /local < NUL"') 45
    if ($code -ne 0) { throw "Diwan-Setup.bat ended with exit code $($code): see $Root\logs\install.log" }
    Write-Host '  ok    Diwan-Setup.bat ended with exit code 0' -ForegroundColor Green
}

function Invoke-Db($config, $query) {
    $ErrorActionPreference = 'Continue'
    $out = & "$Root\bin\mariadb\bin\mariadb.exe" -uroot "-p$($config.db_root_password)" -h 127.0.0.1 "-P$($config.db_port)" -N -B diwan -e $query 2>$null
    return ("$out").Trim()
}

# What the update in this install (local-update.ps1) last reported: state, from, to, message.
function Get-UpdateStatus {
    $file = "$Root\shared\storage\app\update-status.json"
    if (-not (Test-Path -LiteralPath $file)) { return $null }
    return Get-Content -LiteralPath $file -Raw | ConvertFrom-Json
}

function Test-Installed {
    Write-Host ''
    Write-Host '==== Checks' -ForegroundColor Cyan
    $config = Get-Content -LiteralPath "$Root\diwan.json" -Raw | ConvertFrom-Json
    $up = $null
    try { $up = (Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$($config.port)/up" -TimeoutSec 30).StatusCode } catch { }
    Check ($up -eq 200) "Diwan answers on port $($config.port) (/up)"
    $page = $null
    try { $page = (Invoke-WebRequest -UseBasicParsing -Uri "http://127.0.0.1:$($config.port)/" -TimeoutSec 30).StatusCode } catch { }
    Check ($page -eq 200) 'its first page opens'
    Check ((Get-Service DiwanDB -ErrorAction SilentlyContinue).Status -eq 'Running') 'the database service DiwanDB runs'
    Check ((Get-CimInstance Win32_Service -Filter "Name = 'DiwanDB'").PathName -like "*$Root\bin\mariadb\bin\*") "the database service runs the MariaDB of $Root"
    foreach ($task in 'Diwan Web', 'Diwan Worker', 'Diwan Update') { Check ([bool](Get-ScheduledTask -TaskName $task -ErrorAction SilentlyContinue)) "task $task" }
    $update = Get-ScheduledTask -TaskName 'Diwan Update' -ErrorAction SilentlyContinue
    Check ($update -and $update.Actions[0].Arguments -like "*`"$Root\current\deploy\windows\local-update.ps1`"*") "the task Diwan Update runs $Root\current\deploy\windows\local-update.ps1"
    $caddy = & "$Root\bin\caddy.exe" version
    Check ($LASTEXITCODE -eq 0 -and "$caddy" -match '^v\d+\.\d+\.\d+') "caddy.exe runs ($caddy)"
    Check (-not (Test-Path -LiteralPath "$Root\bin\caddy.exe.damaged")) 'no caddy.exe is left set aside'
    Check ([bool](Get-NetFirewallRule -DisplayName 'Diwan ERP' -ErrorAction SilentlyContinue)) 'firewall rule Diwan ERP'
    $desktop = [Environment]::GetFolderPath('CommonDesktopDirectory')
    $menu = Join-Path ([Environment]::GetFolderPath('CommonPrograms')) 'Diwan ERP'
    foreach ($shortcut in @((Join-Path $desktop 'Diwan ERP.url'), (Join-Path $menu 'Open Diwan ERP.url'), (Join-Path $menu 'Update Diwan.lnk'),
            (Join-Path $menu 'Backup Diwan.lnk'), (Join-Path $menu 'Restart Diwan.lnk'), (Join-Path $menu 'Uninstall Diwan.lnk'))) {
        Check (Test-Path -LiteralPath $shortcut) "shortcut $shortcut"
    }
    Check (Select-String -LiteralPath "$Root\scripts\run-worker.ps1" -SimpleMatch "'--queue=zatca,default,webhooks,exports'" -Quiet) 'the queue worker serves zatca, default, webhooks and exports'
    Check (Select-String -LiteralPath "$Root\logs\install.log" -SimpleMatch 'Installed successfully' -Quiet) 'logs\install.log has the run'
    foreach ($left in 'diwan-mariadb', 'diwan-unpack', 'diwan-update') { Check (-not (Test-Path -LiteralPath "$Root\$left")) "nothing is left in $Root\$left" }
    return $config
}

New-Item -ItemType Directory -Force -Path $folder | Out-Null
Copy-Item -LiteralPath (Join-Path $repo 'deploy\windows\Diwan-Setup.bat') -Destination $bat
Copy-Item -LiteralPath (Join-Path $repo 'deploy\windows\install.ps1') -Destination (Join-Path $folder 'install.ps1')

Invoke-Setup
$before = Test-Installed

Write-Host ''
Write-Host '==== Data to keep, damage to repair' -ForegroundColor Cyan
Invoke-Db $before "CREATE TABLE ci_kept (v VARCHAR(20)); INSERT INTO ci_kept VALUES ('kept');" | Out-Null
Check ((Invoke-Db $before 'SELECT v FROM ci_kept') -eq 'kept') 'a table with a row'
$upload = "$Root\shared\storage\app\public\ci-kept.txt"
Set-Content -LiteralPath $upload -Value 'kept'
$envHash = (Get-FileHash -LiteralPath "$Root\shared\.env").Hash
$secretsHash = (Get-FileHash -LiteralPath "$Root\secrets.txt").Hash
# A caddy.exe that does not run, as an interrupted or refused download leaves it.
Stop-ScheduledTask -TaskName 'Diwan Web'
Get-Process caddy -ErrorAction SilentlyContinue | Stop-Process -Force
Start-Sleep -Seconds 2
[IO.File]::WriteAllText("$Root\bin\caddy.exe", '401 Unauthorized: Please log in or use an API key to build Caddy.')
# The Arabic shortcuts of earlier installers.
$desktop = [Environment]::GetFolderPath('CommonDesktopDirectory')
$oldMenu = Join-Path ([Environment]::GetFolderPath('CommonPrograms')) 'الديوان'
New-Item -ItemType Directory -Force -Path $oldMenu | Out-Null
$old = @((Join-Path $desktop 'الديوان.url'), (Join-Path $oldMenu 'فتح الديوان.url'), (Join-Path $oldMenu 'تحديث الديوان.lnk'))
foreach ($file in $old) { [IO.File]::WriteAllText($file, "[InternetShortcut]`r`nURL=http://localhost:$($before.port)`r`n") }

Invoke-Setup
$after = Test-Installed
Check (Select-String -LiteralPath "$Root\logs\install.log" -SimpleMatch 'does not run (damaged or incomplete)' -Quiet) 'the run again found the damaged caddy.exe and replaced it'
Check ((Invoke-Db $after 'SELECT v FROM ci_kept') -eq 'kept') 'the database kept its data'
Check ((Get-Content -LiteralPath $upload -Raw).Trim() -eq 'kept') 'the uploaded files stayed'
Check ((Get-FileHash -LiteralPath "$Root\shared\.env").Hash -eq $envHash) 'shared\.env is unchanged'
Check ((Get-FileHash -LiteralPath "$Root\secrets.txt").Hash -eq $secretsHash) 'secrets.txt is unchanged'
foreach ($key in 'port', 'db_port', 'db_root_password', 'db_password', 'backup_password') { Check ("$($after.$key)" -eq "$($before.$key)") "diwan.json kept $key" }
foreach ($file in $old) { Check (-not (Test-Path -LiteralPath $file)) "the earlier shortcut $file made way" }
Check (-not (Test-Path -LiteralPath $oldMenu)) 'the Start menu folder of earlier installers is gone'

Write-Host ''
Write-Host '==== The daily update (task Diwan Update)' -ForegroundColor Cyan
# The installed release is the latest published one: its update and tools become this checkout's, as
# the next release brings them. It reports an older version, so the update installs the latest release
# again next to it.
function Copy-Tools {
    foreach ($name in 'local-update.ps1', 'local-tools.ps1') {
        Copy-Item -LiteralPath (Join-Path $repo "deploy\windows\$name") -Destination "$Root\current\deploy\windows\$name" -Force
    }
}
Copy-Tools
$version = (Get-Content -LiteralPath "$Root\current\VERSION" -Raw).Trim()
$running = @(Get-ChildItem -LiteralPath "$Root\releases" -Directory | ForEach-Object { $_.Name })
[IO.File]::WriteAllText("$Root\current\VERSION", "0.0.1`r`n")
$lastRun = (Get-ScheduledTaskInfo -TaskName 'Diwan Update').LastRunTime
Start-ScheduledTask -TaskName 'Diwan Update'
$deadline = (Get-Date).AddMinutes(20)
do {
    Start-Sleep -Seconds 10
    $info = Get-ScheduledTaskInfo -TaskName 'Diwan Update'
    $ended = $info.LastRunTime -ne $lastRun -and (Get-ScheduledTask -TaskName 'Diwan Update').State -ne 'Running'
} until ($ended -or (Get-Date) -gt $deadline)
if (-not $ended) { throw "The task Diwan Update did not end within 20 minutes: see $Root\logs\update.log" }
Check ($info.LastTaskResult -eq 0) "the task Diwan Update ended with exit code 0 ($($info.LastTaskResult))"
$status = Get-UpdateStatus
Check ($status -and $status.state -eq 'done' -and $status.from -eq '0.0.1' -and $status.to -eq $version) "it updated from 0.0.1 to $version (update-status.json: $($status.state) from $($status.from) to $($status.to): $($status.message))"
$releases = @(Get-ChildItem -LiteralPath "$Root\releases" -Directory | ForEach-Object { $_.Name })
Check ($releases.Count -eq 1 -and $running -notcontains $releases[0] -and $releases[0] -like "*-$version") "the new release $($releases -join ', ') took the place of $($running -join ', ')"
Check ((Get-Content -LiteralPath "$Root\current\VERSION" -Raw).Trim() -eq $version) "current is the new release ($version)"
Check (Select-String -LiteralPath "$Root\logs\update.log" -SimpleMatch "Updated from 0.0.1 to $version." -Quiet) 'logs\update.log has the update'
$updated = Test-Installed
Check ((Invoke-Db $updated 'SELECT v FROM ci_kept') -eq 'kept') 'the database kept its data'
Check ((Get-Content -LiteralPath $upload -Raw).Trim() -eq 'kept') 'the uploaded files stayed'
Check ((Get-FileHash -LiteralPath "$Root\shared\.env").Hash -eq $envHash) 'shared\.env is unchanged'

Write-Host ''
Write-Host '==== The Start menu shortcut Update Diwan' -ForegroundColor Cyan
# As Windows runs it: local-tools.ps1 finds the install folder and runs local-update.ps1, which finds
# the release up to date. An empty input answers its "Press Enter to close". The update switched to the
# published release: its tools become this checkout's again.
Copy-Tools
$link = (New-Object -ComObject WScript.Shell).CreateShortcut((Join-Path ([Environment]::GetFolderPath('CommonPrograms')) 'Diwan ERP\Update Diwan.lnk'))
$empty = Join-Path $env:TEMP 'diwan-test-input.txt'
$shown = Join-Path $env:TEMP 'diwan-test-output.txt'
[IO.File]::WriteAllText($empty, '')
Push-Location $link.WorkingDirectory
try { $code = Invoke-Program $link.TargetPath $link.Arguments 10 $empty $shown } finally { Pop-Location }
$text = [IO.File]::ReadAllText($shown)
Write-Host $text
Check ($code -eq 0) "Update Diwan ended with exit code 0 ($code)"
Check ($text.Contains("Update finished. Details: $Root\logs\update.log")) "Update Diwan worked in $Root"
$status = Get-UpdateStatus
Check ($status -and $status.state -eq 'uptodate') "it found Diwan up to date (update-status.json: $($status.state): $($status.message))"
Check (-not (Test-Path -LiteralPath "$Root\diwan-update")) "nothing is left in $Root\diwan-update"

Write-Host ''
if ($failures.Count) { throw "$($failures.Count) check(s) failed: $($failures -join '; ')" }
Write-Host 'All checks passed.' -ForegroundColor Green
