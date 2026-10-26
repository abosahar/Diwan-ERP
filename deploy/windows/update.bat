@echo off
chcp 65001 >nul
title Diwan - Update
echo Updating Diwan from GitHub...
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0update.ps1" %*
echo.
pause
