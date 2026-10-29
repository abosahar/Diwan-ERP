<#
.SYNOPSIS
    Updates the local Diwan copy (Windows + MAMP) from GitHub and prepares it.

.DESCRIPTION
    - First run on a folder that is not a git clone: takes a full backup copy,
      then links the folder to the repository (.env, storage and uploads stay).
    - Later runs: pulls new commits only when there are any.
    - Then: maintenance page, database backup, composer, npm build, migrations,
      permission sync, cache clear, back online. A version counts as installed
      only once all of that succeeded, so re-running after a failure finishes
      the job instead of reporting "up to date".

    Double-click update.bat, or run every hour via install-auto-update.ps1.

.PARAMETER Auto
    Scheduled mode: quiet, and never touches a folder with local code edits.
.PARAMETER Force
    Prepare again even when there is nothing new to pull, and continue even
    when the pre-update database backup fails.
.PARAMETER Branch
    Branch to follow. Default: UPDATE_BRANCH in .env, else main.
#>
param(
    [string]$Path = '',
    [string]$Branch = '',
    [string]$Repository = 'https://github.com/abosahar/Diwan-ERP.git',
    [switch]$Auto,
    [switch]$Force
)

# Windows PowerShell 5.1 compatible (no ?? / ternaries).
$ErrorActionPreference = 'Stop'
try { [Console]::OutputEncoding = [System.Text.Encoding]::UTF8 } catch { }

if (-not $Path) {
    $candidate = Resolve-Path (Join-Path $PSScriptRoot '../..') -ErrorAction SilentlyContinue
    if ($candidate -and (Test-Path (Join-Path $candidate 'artisan'))) { $Path = $candidate.Path }
    else { $Path = 'C:\MAMP\htdocs\diwan' }
}
if (-not (Test-Path (Join-Path $Path 'artisan'))) {
    Write-Host "Diwan was not found in: $Path" -ForegroundColor Red
    exit 1
}
Set-Location $Path

$logDir = Join-Path $Path 'storage/logs'
New-Item -ItemType Directory -Force -Path $logDir | Out-Null
$logFile = Join-Path $logDir 'update.log'

function Write-Log([string]$Message, [string]$Color = 'Gray', [switch]$Always) {
    Add-Content -Path $logFile -Value ("[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message) -Encoding UTF8
    if (-not $Auto -or $Always) { Write-Host $Message -ForegroundColor $Color }
}
function Step([string]$Message) { Write-Log "==> $Message" 'Cyan' }

# .env helpers: read a value; add a key only when it is missing (never overwrite).
$envFile = Join-Path $Path '.env'
function Get-EnvValue([string]$Key) {
    if (-not (Test-Path $envFile)) { return '' }
    $line = Get-Content $envFile -Encoding UTF8 | Where-Object { $_ -match ('^\s*' + [regex]::Escape($Key) + '\s*=') } | Select-Object -Last 1
    if (-not $line) { return '' }
    return (($line -replace ('^\s*' + [regex]::Escape($Key) + '\s*='), '').Trim().Trim('"').Trim("'"))
}
function Add-EnvValue([string]$Key, [string]$Value) {
    if (-not (Test-Path $envFile) -or (Get-EnvValue $Key)) { return }
    $raw = [IO.File]::ReadAllText($envFile)
    if ($raw.Length -and -not $raw.EndsWith("`n")) { Add-Content -Path $envFile -Value '' -Encoding UTF8 }
    # Single quotes: dotenv keeps Windows backslashes literally.
    Add-Content -Path $envFile -Value ("{0}='{1}'" -f $Key, $Value) -Encoding UTF8
    Write-Log "    Added to .env: $Key" 'DarkGray'
}

if (-not $Branch) { $Branch = Get-EnvValue 'UPDATE_BRANCH' }
if (-not $Branch) { $Branch = 'main' }

# Keep update.log to its last 5000 lines.
if ((Test-Path $logFile) -and ((Get-Item $logFile).Length -gt 2MB)) {
    Get-Content $logFile -Tail 5000 -Encoding UTF8 | Set-Content $logFile -Encoding UTF8
}

# Runs a program, logs its output, throws on a non-zero exit code. stderr is
# captured as text so git progress messages are not treated as errors.
function Invoke-Tool([string]$Exe, [string[]]$Arguments, [switch]$AllowFail) {
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    $output = & $Exe @Arguments 2>&1 | ForEach-Object { "$_" }
    $code = $LASTEXITCODE
    $ErrorActionPreference = $previous
    foreach ($line in $output) { if ($line) { Write-Log "    $line" 'DarkGray' } }
    if ($code -ne 0 -and -not $AllowFail) {
        throw ("Command failed: {0} {1} (code {2})" -f (Split-Path $Exe -Leaf), ($Arguments -join ' '), $code)
    }
    return $output
}

function Find-Tool([string[]]$Names, [string[]]$Candidates) {
    $cmd = Get-Command $Names -CommandType Application -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($cmd) { return $cmd.Source }
    foreach ($c in $Candidates) {
        $found = Get-ChildItem -Path $c -ErrorAction SilentlyContinue | Sort-Object FullName -Descending | Select-Object -First 1
        if ($found) { return $found.FullName }
    }
    return $null
}

function Copy-Backup([string]$Reason) {
    $target = '{0}-backup-{1}' -f $Path.TrimEnd('\'), (Get-Date -Format 'yyyyMMdd-HHmm')
    Step "Backing up the folder ($Reason) to: $target"
    # robocopy exit codes below 8 mean success.
    & robocopy $Path $target /E /XD vendor node_modules .git /R:1 /W:1 /NFL /NDL /NJH /NJS /NP | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "Could not back up the folder (robocopy $LASTEXITCODE)" }
    Write-Log "    Backup saved: $target" 'Green'
    return $target
}

# Maintenance page served by public/index.php before vendor/ is loaded, so it
# keeps showing even while composer is half-way through replacing packages.
$downFile = Join-Path $Path 'storage/framework/down'
$maintenanceFile = Join-Path $Path 'storage/framework/maintenance.php'
$preparedFile = Join-Path $Path 'storage/framework/diwan-prepared'
function Enter-Maintenance {
    $html = '<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta http-equiv="refresh" content="20"><title>جارٍ التحديث</title></head>' +
        '<body style="font-family:Tahoma,sans-serif;background:#f8fafc;color:#334155;display:flex;align-items:center;justify-content:center;height:100vh;margin:0">' +
        '<div style="text-align:center"><h1 style="color:#4f46e5">جارٍ تحديث النظام</h1><p>يعود النظام للعمل خلال دقائق؛ ستُحدَّث الصفحة تلقائياً.</p></div></body></html>'
    $utf8 = New-Object System.Text.UTF8Encoding($false)
    [IO.File]::WriteAllText($downFile, (@{ template = $html; status = 503; retry = 60 } | ConvertTo-Json -Compress), $utf8)
    $stub = @'
<?php
if (! file_exists($down = __DIR__.'/down')) { return; }
$data = json_decode(file_get_contents($down), true);
if (! isset($data['template'])) { return; }
http_response_code($data['status'] ?? 503);
header('Retry-After: '.($data['retry'] ?? 60));
echo $data['template'];
exit;
'@
    [IO.File]::WriteAllText($maintenanceFile, $stub, $utf8)
}
function Exit-Maintenance {
    # Laravel's own "artisan down" (older runs, or done by hand) is lifted too.
    if ($php -and (Test-Vendor)) { & $php artisan up 2>&1 | Out-Null }
    Remove-Item -LiteralPath $downFile, $maintenanceFile -Force -ErrorAction SilentlyContinue
    if (Test-Path $downFile) { Write-Log "    Could not delete $downFile; delete it by hand to bring the system back." 'Red' -Always }
}
# A maintenance flag left behind by an interrupted run must not keep the site
# down when there is nothing to do now.
function Clear-StaleMaintenance {
    if ((Test-Path $downFile) -and (Test-Vendor)) {
        Exit-Maintenance
        Write-Log '    Cleared a maintenance mode left over from a previous update.' 'Yellow' -Always
    }
}
function Test-Vendor {
    if (-not (Test-Path (Join-Path $Path 'vendor/autoload.php'))) { return $false }
    & $php -r "require 'vendor/autoload.php';" 2>&1 | Out-Null
    return ($LASTEXITCODE -eq 0)
}

$siteDown = $false
$codeChanged = $false
$previousHead = ''
$backupFile = ''
$php = $null
try {
    Write-Log ("Update started - {0} - branch {1}{2}" -f $Path, $Branch, $(if ($Auto) { ' (automatic)' } else { '' })) 'White'

    # ---------------------------------------------------------------- tools
    $git = Find-Tool 'git' @("$env:ProgramFiles\Git\cmd\git.exe", "${env:ProgramFiles(x86)}\Git\cmd\git.exe", "$env:LOCALAPPDATA\Programs\Git\cmd\git.exe")
    $phpCandidates = Get-ChildItem 'C:\MAMP\bin\php\php8*\php.exe' -ErrorAction SilentlyContinue |
        Where-Object { $_.Directory.Name -match '^php8\.(\d+)' -and [int]$Matches[1] -ge 2 } |
        Sort-Object { [version]($_.Directory.Name -replace '^php', '') } -Descending
    $php = Find-Tool 'php' @()
    if ($php) {
        $phpVersion = (& $php -r 'echo PHP_VERSION;') 2>$null
        if (-not $phpVersion -or [version]($phpVersion -replace '[^0-9.].*$', '') -lt [version]'8.2') { $php = $null }
    }
    if (-not $php -and $phpCandidates) { $php = ($phpCandidates | Select-Object -First 1).FullName }
    $node = Find-Tool 'node' @("$env:ProgramFiles\nodejs\node.exe")
    $npm = Find-Tool @('npm.cmd', 'npm') @("$env:ProgramFiles\nodejs\npm.cmd")

    $missing = @()
    if (-not $git) { $missing += 'Git for Windows: https://git-scm.com/download/win' }
    if (-not $php) { $missing += 'PHP 8.2 or newer (enable it in MAMP or install it): https://windows.php.net/download' }
    if (-not $node -or -not $npm) { $missing += 'Node.js LTS: https://nodejs.org' }
    if ($missing.Count) {
        Write-Log 'Required tools are missing; install them, then run again:' 'Red' -Always
        $missing | ForEach-Object { Write-Log "  - $_" 'Yellow' -Always }
        exit 2
    }
    $env:Path = (Split-Path $php) + ';' + (Split-Path $node) + ';' + $env:Path
    Write-Log "    PHP: $php" 'DarkGray'

    $composer = Find-Tool @('composer.bat', 'composer') @()
    $composerArgs = @()
    if (-not $composer) {
        $phar = Join-Path $PSScriptRoot 'tools/composer.phar'
        if (-not (Test-Path $phar)) {
            Step 'Downloading Composer'
            New-Item -ItemType Directory -Force -Path (Split-Path $phar) | Out-Null
            [Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12
            Invoke-WebRequest -UseBasicParsing 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile $phar
        }
        $composer = $php
        $composerArgs = @($phar)
    }
    $mysqldump = Get-ChildItem 'C:\MAMP\bin\mysql\bin\mysqldump.exe' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($mysqldump) {
        $env:BACKUP_MYSQLDUMP = $mysqldump.FullName; $env:BACKUP_MYSQL = Join-Path $mysqldump.DirectoryName 'mysql.exe'
        # Written to .env too, so the scheduled nightly backup finds them.
        Add-EnvValue 'BACKUP_MYSQLDUMP' $env:BACKUP_MYSQLDUMP
        Add-EnvValue 'BACKUP_MYSQL' $env:BACKUP_MYSQL
    }

    # MAMP has no Redis: jobs (ZATCA reporting, e-mail) would never run.
    # The framework's default name (or the old brand) shows in the sidebar and the PDFs: use the product name.
    if ((Test-Path $envFile) -and (@('Laravel', 'AlOstaz', 'Alostaz', 'الأستاذ') -contains (Get-EnvValue 'APP_NAME'))) {
        $utf8 = New-Object System.Text.UTF8Encoding($false)
        $raw = [IO.File]::ReadAllText($envFile, $utf8)
        [IO.File]::WriteAllText($envFile, ($raw -replace '(?m)^\s*APP_NAME\s*=.*$', 'APP_NAME="الديوان"'), $utf8)
        Write-Log '    The system name in .env is now Diwan.' 'DarkGray'
    }

    $redisDrivers = @('QUEUE_CONNECTION', 'SESSION_DRIVER', 'CACHE_STORE') | Where-Object { (Get-EnvValue $_) -eq 'redis' }
    if ($redisDrivers -and -not (@(& $php -m 2>$null) -contains 'redis')) {
        Write-Log ('Warning: .env uses Redis for: ' + ($redisDrivers -join ', ') + ' but Redis is not available. Change them to database (see .env.windows.example).') 'Red' -Always
    }

    # ---------------------------------------------------------------- code
    # Linked = a git folder whose "origin" already points at this repository.
    # A folder copied by hand, or a local git folder without that remote,
    # is backed up in full before being linked.
    $isRepo = Test-Path (Join-Path $Path '.git')
    $origin = ''
    if ($isRepo -and (@(Invoke-Tool $git @('remote') -AllowFail) | ForEach-Object { "$_".Trim() }) -contains 'origin') {
        $origin = "$(@(Invoke-Tool $git @('remote', 'get-url', 'origin') -AllowFail)[0])".Trim()
    }
    $linked = $isRepo -and ($origin -match 'abosahar/Diwan' -or $origin -eq $Repository)
    if (-not $linked) {
        if ($Auto) { Write-Log 'The folder is not linked to the repository yet; run update.bat by hand the first time.' 'Yellow' -Always; exit 3 }
        Copy-Backup 'first link to the repository' | Out-Null
        Step 'Linking the folder to the repository'
        if (-not $isRepo) { Invoke-Tool $git @('init', '-q') | Out-Null }
        if ($origin) { Invoke-Tool $git @('remote', 'set-url', 'origin', $Repository) | Out-Null }
        else { Invoke-Tool $git @('remote', 'add', 'origin', $Repository) | Out-Null }
        # The local history is unrelated: switch to the repository's version
        # (untracked .env, storage and uploads are not touched).
        $isRepo = $false
    } elseif ($origin -ne $Repository) {
        Invoke-Tool $git @('remote', 'set-url', 'origin', $Repository) -AllowFail | Out-Null
    }

    Step 'Fetching the latest changes from GitHub'
    $remoteBranch = @(Invoke-Tool $git @('ls-remote', '--heads', 'origin', $Branch))
    if (-not ($remoteBranch -join '').Trim()) {
        Write-Log "Branch $Branch does not exist (merged?); using main." 'Yellow' -Always
        $Branch = 'main'
    }
    Invoke-Tool $git @('fetch', '--prune', 'origin', $Branch) | Out-Null
    $remoteHead = (@(Invoke-Tool $git @('rev-parse', "origin/$Branch"))[0]).Trim()

    $localHead = ''
    if ($isRepo) { $localHead = "$(@(Invoke-Tool $git @('rev-parse', 'HEAD') -AllowFail)[0])".Trim() }
    $prepared = ''
    if (Test-Path $preparedFile) { $prepared = (Get-Content $preparedFile -Raw).Trim() }
    if ($isRepo -and $localHead -eq $remoteHead -and $prepared -eq $remoteHead -and -not $Force) {
        Clear-StaleMaintenance
        Write-Log ("Up to date (version {0})." -f $remoteHead.Substring(0, 7)) 'Green'
        exit 0
    }
    if ($isRepo -and $localHead -eq $remoteHead) { Write-Log '    The code is current but the last preparation did not finish; finishing it now.' 'Yellow' }

    # PHP extensions the new version needs, checked before anything changes.
    $lockJson = (@(Invoke-Tool $git @('show', "origin/${Branch}:composer.lock")) -join "`n")
    $needed = @()
    foreach ($pkg in @(($lockJson | ConvertFrom-Json).packages)) {
        if ($pkg.require) { $needed += $pkg.require.PSObject.Properties.Name | Where-Object { $_ -like 'ext-*' } }
    }
    $loaded = @(& $php -m 2>$null | ForEach-Object { 'ext-' + ("$_".Trim().ToLower() -replace ' ', '-') })
    $absent = @($needed | Sort-Object -Unique | Where-Object { $loaded -notcontains $_ -and $_ -ne 'ext-zend-opcache' })
    if ($absent.Count) {
        Write-Log ('These PHP extensions are not enabled: ' + (($absent | ForEach-Object { $_ -replace '^ext-', '' }) -join ', ')) 'Red' -Always
        Write-Log "Enable them, then run again: in Laragon via PHP > Extensions, or remove the ; at the start of extension=<name> in php.ini:" 'Yellow' -Always
        Write-Log ("    " + (@(& $php --ini 2>$null) | Where-Object { $_ -match 'Loaded Configuration' } | Select-Object -First 1)) 'Yellow' -Always
        Write-Log 'Nothing was changed.' 'Yellow' -Always
        Clear-StaleMaintenance
        exit 5
    }

    if ($isRepo) {
        $dirty = @(Invoke-Tool $git @('status', '--porcelain', '--untracked-files=no'))
        if (($dirty -join '').Trim()) {
            if ($Auto) {
                Write-Log 'The code files have manual edits; not updated automatically. Run update.bat by hand.' 'Yellow' -Always
                exit 4
            }
            $stash = 'auto-update ' + (Get-Date -Format 'yyyy-MM-dd HH:mm')
            Step "Setting your local edits aside (git stash: $stash)"
            Invoke-Tool $git @('stash', 'push', '-m', $stash) | Out-Null
            Write-Log '    To restore them later: git stash list, then git stash pop' 'Yellow'
        }
    }

    Step 'Turning on the maintenance page'
    Enter-Maintenance
    $siteDown = $true
    if (Test-Vendor) {
        Step 'Backing up the database'
        $backup = Invoke-Tool $php @('artisan', 'erp:backup', '--tag=preupdate') -AllowFail
        # The installed (older) version may not know --tag yet.
        if ($LASTEXITCODE -ne 0 -and (($backup -join ' ') -match 'does not exist')) {
            $backup = Invoke-Tool $php @('artisan', 'erp:backup') -AllowFail
        }
        if ($LASTEXITCODE -ne 0) {
            # No update without a way back, unless explicitly forced.
            if (-not $Force) { throw 'The database backup failed, so the update stopped and nothing changed. Check BACKUP_PASSWORD and BACKUP_MYSQLDUMP in .env, or run update.ps1 -Force to skip it.' }
            Write-Log '    The backup failed (skipped with -Force).' 'Yellow'
        }
        $backupFile = (@($backup) | Where-Object { $_ -match 'diwan-[a-z]+-[0-9-]+\.zip' } | ForEach-Object { $Matches[0] } | Select-Object -First 1)
    }

    Step 'Updating the code files'
    if ($isRepo) { $previousHead = "$(@(Invoke-Tool $git @('rev-parse', 'HEAD') -AllowFail)[0])".Trim() }
    $codeChanged = $true
    if ($isRepo) {
        $merge = Invoke-Tool $git @('merge', '--ff-only', "origin/$Branch") -AllowFail
        if ($LASTEXITCODE -ne 0) {
            Copy-Backup 'conflict with the local copy' | Out-Null
            Invoke-Tool $git @('checkout', '-f', '-B', $Branch, "origin/$Branch") | Out-Null
        }
    } else {
        Invoke-Tool $git @('checkout', '-f', '-B', $Branch, "origin/$Branch") | Out-Null
    }
    Invoke-Tool $git @('branch', "--set-upstream-to=origin/$Branch", $Branch) -AllowFail | Out-Null

    Step 'Installing PHP libraries (composer)'
    $composerInstall = $composerArgs + @('install', '--no-interaction', '--prefer-dist', '--optimize-autoloader', '--no-dev')
    Invoke-Tool $composer $composerInstall -AllowFail | Out-Null
    if ($LASTEXITCODE -ne 0) {
        # Windows often fails here on a file briefly locked by antivirus or the web server.
        Write-Log '    The first attempt failed; retrying in 10 seconds...' 'Yellow'
        Start-Sleep -Seconds 10
        Invoke-Tool $composer $composerInstall | Out-Null
    }

    Step 'Building the interface (npm)'
    Invoke-Tool $npm @('ci', '--no-audit', '--no-fund') | Out-Null
    Invoke-Tool $npm @('run', 'build') | Out-Null

    Step 'Updating the database'
    Invoke-Tool $php @('artisan', 'migrate', '--force') | Out-Null
    Invoke-Tool $php @('artisan', 'erp:sync-permissions') | Out-Null
    Invoke-Tool $php @('artisan', 'erp:accounting', 'provision') | Out-Null
    Invoke-Tool $php @('artisan', 'optimize:clear') | Out-Null
    Invoke-Tool $php @('artisan', 'optimize') -AllowFail | Out-Null

    Set-Content -Path $preparedFile -Value ((@(Invoke-Tool $git @('rev-parse', 'HEAD'))[0]).Trim()) -Encoding ASCII
    Exit-Maintenance
    $siteDown = $false

    # Settings added to .env.example that this .env does not have yet.
    $keys = { param($file) if (Test-Path $file) { Get-Content $file -Encoding UTF8 | Where-Object { $_ -match '^\s*([A-Z0-9_]+)\s*=' } | ForEach-Object { $Matches[1] } } }
    $missingKeys = & $keys (Join-Path $Path '.env.example') | Where-Object { (& $keys (Join-Path $Path '.env')) -notcontains $_ }
    if ($missingKeys) {
        Write-Log 'New settings missing from .env (defaults are used; add them if needed):' 'Yellow'
        $missingKeys | ForEach-Object { Write-Log "  - $_" 'Yellow' }
    }

    $version = @(Invoke-Tool $git @('log', '-1', '--format=%h %s'))[0]
    Write-Log "Updated successfully: $version" 'Green' -Always
    exit 0
}
catch {
    Write-Log ("Error: " + $_.Exception.Message) 'Red' -Always
    if ($siteDown -and $php) {
        if ($codeChanged) {
            # New code may be running on a half-updated database: stay down until fixed.
            Write-Log 'The system stays in maintenance mode because the update did not finish (to protect the data). Options:' 'Red' -Always
            Write-Log '  1) Fix the cause shown above, then run update.bat again (it continues where it stopped).' 'Yellow' -Always
            if ($previousHead) { Write-Log "  2) Or go back to the previous version: git checkout -f $previousHead, then php artisan erp:restore $backupFile --force, then php artisan up" 'Yellow' -Always }
        } elseif (Test-Vendor) {
            Exit-Maintenance
            Write-Log 'The system is back online; check the error above, then run the update again.' 'Yellow' -Always
        } else {
            Write-Log 'The "Updating" page is still shown because the PHP libraries are incomplete; run update.bat again to finish.' 'Yellow' -Always
        }
    }
    Write-Log "Full log: $logFile" 'Gray' -Always
    exit 1
}
