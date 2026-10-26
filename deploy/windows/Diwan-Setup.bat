@echo off
rem Diwan ERP - Windows setup. Double-click this file.
rem It asks Windows for administrator rights, downloads the installer of the
rem latest release and runs it. Safe to run again (keeps your data).

net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Asking Windows for administrator rights...
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

title Diwan ERP setup
echo Downloading the Diwan installer...
set "PS1=%TEMP%\diwan-install.ps1"
set "URL=https://github.com/turkialawlqy/Diwan-ERP/releases/latest/download/install-windows.ps1"
curl.exe -fsSL -o "%PS1%" "%URL%"
if %errorlevel% neq 0 powershell -NoProfile -Command "[Net.ServicePointManager]::SecurityProtocol='Tls12'; Invoke-WebRequest -UseBasicParsing -Uri '%URL%' -OutFile '%PS1%'"
if not exist "%PS1%" (
    echo.
    echo Could not download the installer. Check the internet connection and try again.
    pause
    exit /b 1
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%PS1%"
echo.
pause
