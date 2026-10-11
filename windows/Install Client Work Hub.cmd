@echo off
rem Installs Client Work Hub (home edition) for this Windows user. Double-click it in the unzipped folder.
rem No administrator needed. It asks first, then copies Client Work Hub to %LOCALAPPDATA%\Programs\Client Work Hub,
rem adds it to the Start menu (and the desktop if you want), and lists it in Settings > Apps, where Uninstall
rem removes it again. Your data lives in %APPDATA%\Client Work Hub and is never removed by setup or uninstall.
title Client Work Hub setup
cd /d "%~dp0"
if not exist "app\windows\install.ps1" goto not_unzipped
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "app\windows\install.ps1" %*
if errorlevel 1 (echo. & pause & exit /b 1)
echo.
echo  This window closes in 10 seconds.
timeout /t 10 >nul 2>nul
exit /b 0
:not_unzipped
echo.
echo  This was opened from inside the zip, or without the rest of its folder.
echo  Unzip the whole download first (right-click the zip, Extract All), then double-click
echo  "Install Client Work Hub.cmd" in the unzipped folder.
echo.
pause
exit /b 1
