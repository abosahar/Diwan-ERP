#Requires -Version 5.1
<#
    Pester 5 tests of an install folder on another drive than %TEMP% (DIWAN_DIR=D:\Diwan, with %TEMP% on C:),
    on Windows PowerShell 5.1:
        Invoke-Pester -Path tests/windows
    install.ps1 (MariaDB, the release) and local-update.ps1 (the new release) unpack a download in %TEMP%,
    then move the folder it holds into the install folder with Move-Item. Move-Item of Windows PowerShell 5.1
    moves a folder to another drive too (it copies, then deletes), unlike [IO.Directory]::Move of .NET
    ("Source and destination path must have identical roots"): the scripts must keep Move-Item.
    local-update.ps1 runs here as a script, as the "Diwan Update" task runs it, in installs laid out as the
    installer lays them out, with a stand-in php.exe that refuses to prepare the new release: it stops
    there, before it switches anything.
    The tests on another drive need a folder on it, in DIWAN_TEST_OTHER_DRIVE (the workflow gives the
    D:\a\_temp of its runner); without it they are skipped.
    Keep this file UTF-8 with BOM, like the other Windows scripts.
#>

BeforeDiscovery {
    $script:OtherDrive = $env:DIWAN_TEST_OTHER_DRIVE
}

BeforeAll {
    $ErrorActionPreference = 'Stop'
    $script:Repo = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
    $script:PowerShellExe = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    $script:TempDrive = [IO.Path]::GetPathRoot([IO.Path]::GetTempPath())
    $script:Junctions = New-Object 'System.Collections.Generic.List[string]'
    $script:Programs = Join-Path ([IO.Path]::GetTempPath()) ('diwan-tests-' + [guid]::NewGuid().ToString('N'))
    [IO.Directory]::CreateDirectory($Programs) | Out-Null

    # A new folder on another drive than %TEMP%, in DIWAN_TEST_OTHER_DRIVE.
    function New-OtherDriveFolder {
        $base = $env:DIWAN_TEST_OTHER_DRIVE
        if ([IO.Path]::GetPathRoot($base) -eq $TempDrive) { throw "DIWAN_TEST_OTHER_DRIVE ($base) is on the drive of %TEMP% ($TempDrive): give a folder on another drive." }
        $dir = Join-Path $base ('diwan-tests-' + [guid]::NewGuid().ToString('N'))
        [IO.Directory]::CreateDirectory($dir) | Out-Null
        return $dir
    }

    # A stand-in php.exe: notes in php.log next to it the folder it runs in, its arguments and the VERSION
    # there, then exits 1, as php does when the new release cannot be prepared ("artisan optimize").
    $script:FakePhp = Join-Path $Programs 'php\php.exe'
    [IO.Directory]::CreateDirectory((Split-Path -Parent $FakePhp)) | Out-Null
    $class = 'FakePhp' + [guid]::NewGuid().ToString('N')
    Add-Type -OutputAssembly $FakePhp -OutputType ConsoleApplication -TypeDefinition @"
public static class $class {
    public static int Main(string[] args) {
        string log = System.IO.Path.Combine(System.AppDomain.CurrentDomain.BaseDirectory, "php.log");
        string version = System.IO.File.Exists("VERSION") ? System.IO.File.ReadAllText("VERSION").Trim() : "";
        System.IO.File.AppendAllText(log, System.IO.Directory.GetCurrentDirectory() + "|" + string.Join(" ", args) + "|" + version + "\r\n");
        return 1;
    }
}
"@

    # The package of a release 9.9.9 as the releases publish it (diwan-erp.zip: the folder diwan-erp with
    # VERSION, public and storage), made by the tar.exe of Windows that local-update.ps1 unpacks it with.
    $stage = Join-Path $Programs 'package'
    foreach ($dir in @('diwan-erp\public', 'diwan-erp\storage\app\public', 'diwan-erp\deploy\windows')) { [IO.Directory]::CreateDirectory((Join-Path $stage $dir)) | Out-Null }
    [IO.File]::WriteAllText((Join-Path $stage 'diwan-erp\VERSION'), "9.9.9`r`n")
    [IO.File]::WriteAllText((Join-Path $stage 'diwan-erp\public\index.php'), '<?php')
    [IO.File]::WriteAllText((Join-Path $stage 'diwan-erp\storage\app\public\.gitignore'), '*')
    $script:Package = Join-Path $Programs 'diwan-erp.zip'
    & (Join-Path $env:SystemRoot 'System32\tar.exe') -a -c -f $Package -C $stage diwan-erp
    if ($LASTEXITCODE -ne 0) { throw "tar.exe could not make $Package" }

    # An install in $Root as the installer lays it out: diwan.json, the shared settings and files, the release
    # 1.8.1 with this checkout's local-update.ps1, the junction current to it, and the stand-in php.exe.
    function New-Install([string]$Root) {
        $release = Join-Path $Root 'releases\20261122033000-1.8.1'
        foreach ($dir in @((Join-Path $release 'deploy\windows'), (Join-Path $Root 'bin\php'), (Join-Path $Root 'shared\storage\app\public'))) {
            [IO.Directory]::CreateDirectory($dir) | Out-Null
        }
        [IO.File]::WriteAllText((Join-Path $Root 'diwan.json'), '{"port": 8000}')
        [IO.File]::WriteAllText((Join-Path $Root 'shared\.env'), "APP_ENV=production`r`n")
        [IO.File]::WriteAllText((Join-Path $Root 'shared\storage\app\public\kept.txt'), 'kept')
        [IO.File]::WriteAllText((Join-Path $release 'VERSION'), "1.8.1`r`n")
        Copy-Item -LiteralPath (Join-Path $Repo 'deploy\windows\local-update.ps1') -Destination (Join-Path $release 'deploy\windows\local-update.ps1')
        Copy-Item -LiteralPath $FakePhp -Destination (Join-Path $Root 'bin\php\php.exe')
        $current = Join-Path $Root 'current'
        cmd /c mklink /J "$current" "$release" | Out-Null
        if ((Get-Item -LiteralPath $current).LinkType -ne 'Junction') { throw "mklink /J did not make the junction $current" }
        $Junctions.Add($current)
    }

    # rmdir removes a junction and never what it points to: before the folders they are in go.
    function Remove-Junctions {
        foreach ($junction in $Junctions) { if (Test-Path -LiteralPath $junction) { cmd /c rmdir "$junction" | Out-Null } }
        $Junctions.Clear()
    }

    # Runs the local-update.ps1 of the install in $Root through the junction current, as the "Diwan Update"
    # task runs it, with %TEMP% in $Temp and the package above. Returns its exit code and what it showed.
    function Invoke-Update([string]$Root, [string]$Temp) {
        # Native stderr redirected under 'Stop' would end the test on its first line (Windows PowerShell 5.1).
        $ErrorActionPreference = 'Continue'
        $saved = @($env:TEMP, $env:TMP, $env:DIWAN_PACKAGE)
        try {
            $env:TEMP = $Temp
            $env:TMP = $Temp
            $env:DIWAN_PACKAGE = $Package
            $output = & $PowerShellExe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $Root 'current\deploy\windows\local-update.ps1') 2>&1 | Out-String
            return @{ ExitCode = $LASTEXITCODE; Output = $output }
        } finally {
            $env:TEMP = $saved[0]
            $env:TMP = $saved[1]
            $env:DIWAN_PACKAGE = $saved[2]
        }
    }

    # What an update of the install in $Root that stops where the stand-in php.exe refuses leaves: the new
    # release reached the install folder (php ran "artisan optimize" in it), nothing switched, the new release
    # and the unpacked download are gone, the shared files stayed.
    function Assert-StoppedAtThePreparation([string]$Root, [string]$Temp, $Result) {
        Write-Host $Result.Output
        $status = Get-Content -LiteralPath (Join-Path $Root 'shared\storage\app\update-status.json') -Raw | ConvertFrom-Json
        $status.message | Should -Be 'Could not prepare the new version.'
        $status.state | Should -Be 'failed'
        $status.from | Should -Be '1.8.1'
        $status.to | Should -Be '9.9.9'
        $Result.ExitCode | Should -Be 1
        $calls = @(Get-Content -LiteralPath (Join-Path $Root 'bin\php\php.log'))
        $calls.Count | Should -Be 1
        $folder, $arguments, $version = $calls[0] -split '\|'
        $arguments | Should -Be 'artisan optimize'
        $version | Should -Be '9.9.9'
        Split-Path -Parent $folder | Should -Be (Join-Path $Root 'releases')
        Split-Path -Leaf $folder | Should -Match '^\d{14}-9\.9\.9$'
        @(Get-ChildItem -LiteralPath (Join-Path $Root 'releases') -Directory | ForEach-Object { $_.Name }) | Should -Be @('20261122033000-1.8.1')
        (Get-Content -LiteralPath (Join-Path $Root 'current\VERSION') -Raw).Trim() | Should -Be '1.8.1'
        Get-Content -LiteralPath (Join-Path $Root 'shared\storage\app\public\kept.txt') | Should -Be 'kept'
        Test-Path -LiteralPath (Join-Path $Root 'diwan-update') | Should -BeFalse
        @(Get-ChildItem -LiteralPath $Temp -Filter 'diwan-update*' -Force) | Should -BeNullOrEmpty
    }
}

AfterAll {
    Remove-Junctions
    Remove-Item -LiteralPath $Programs -Recurse -Force -ErrorAction SilentlyContinue
}

Describe 'Move-Item of Windows PowerShell 5.1' -Skip:(-not $OtherDrive) {
    BeforeAll { $script:Other = New-OtherDriveFolder }
    AfterAll { Remove-Item -LiteralPath $Other -Recurse -Force -ErrorAction SilentlyContinue }

    It 'moves a folder of %TEMP% to another drive, as install.ps1 and local-update.ps1 move a release' {
        $unpacked = Join-Path $TestDrive 'diwan-update\diwan-erp'
        [IO.Directory]::CreateDirectory((Join-Path $unpacked 'public')) | Out-Null
        [IO.File]::WriteAllText((Join-Path $unpacked 'VERSION'), '9.9.9')
        $release = Join-Path $Other 'Diwan\releases\20261123033000-9.9.9'
        [IO.Directory]::CreateDirectory((Split-Path -Parent $release)) | Out-Null
        Move-Item $unpacked $release
        Get-Content -LiteralPath (Join-Path $release 'VERSION') | Should -Be '9.9.9'
        Test-Path -LiteralPath (Join-Path $release 'public') -PathType Container | Should -BeTrue
        Test-Path -LiteralPath $unpacked | Should -BeFalse
    }

    It 'unlike [IO.Directory]::Move of .NET, which moves a folder only within one drive' {
        $unpacked = Join-Path $TestDrive 'diwan-unpack\diwan-erp'
        [IO.Directory]::CreateDirectory($unpacked) | Out-Null
        $release = Join-Path $Other 'Diwan\releases\20261123033001-9.9.9'
        # "Source and destination path must have identical roots. Move will not work across volumes."
        $failure = $null
        try { [IO.Directory]::Move($unpacked, $release) } catch { $failure = $_.Exception.InnerException }
        $failure | Should -BeOfType ([IO.IOException])
        Test-Path -LiteralPath $release | Should -BeFalse
    }

    It 'moves a folder within the drive of the install folder' {
        $unpacked = Join-Path $Other 'Diwan\diwan-update\diwan-erp'
        [IO.Directory]::CreateDirectory((Join-Path $unpacked 'public')) | Out-Null
        [IO.File]::WriteAllText((Join-Path $unpacked 'VERSION'), '9.9.9')
        $release = Join-Path $Other 'Diwan\releases\20261123033000-9.9.9'
        [IO.Directory]::CreateDirectory((Split-Path -Parent $release)) | Out-Null
        Move-Item $unpacked $release
        Get-Content -LiteralPath (Join-Path $release 'VERSION') | Should -Be '9.9.9'
        Test-Path -LiteralPath $unpacked | Should -BeFalse
    }
}

Describe 'The daily update (local-update.ps1) of an install on the drive of %TEMP%' {
    # Before TestDrive goes.
    AfterAll { Remove-Junctions }

    It 'moves the new release into the install folder, as before' {
        $root = Join-Path $TestDrive 'Diwan'
        $temp = Join-Path $TestDrive 'temp'
        [IO.Directory]::CreateDirectory($temp) | Out-Null
        New-Install $root
        Assert-StoppedAtThePreparation $root $temp (Invoke-Update $root $temp)
    }
}

Describe 'The daily update (local-update.ps1) of an install on another drive than %TEMP% (DIWAN_DIR=D:\Diwan)' -Skip:(-not $OtherDrive) {
    BeforeAll { $script:Other = New-OtherDriveFolder }
    AfterAll {
        Remove-Junctions
        Remove-Item -LiteralPath $Other -Recurse -Force -ErrorAction SilentlyContinue
    }

    It 'moves the new release into the install folder' {
        $root = Join-Path $Other 'Diwan'
        $temp = Join-Path $TestDrive 'temp'
        [IO.Directory]::CreateDirectory($temp) | Out-Null
        New-Install $root
        Assert-StoppedAtThePreparation $root $temp (Invoke-Update $root $temp)
    }
}
