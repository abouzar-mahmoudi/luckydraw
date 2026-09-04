@echo off
chcp 65001 >nul
title LuckyDraw - قرعه‌کشی
cd /d "%~dp0"
set PHP=php
if exist "C:\xampp\php\php.exe" set PHP=C:\xampp\php\php.exe
if exist "C:\laragon\bin\php\php.exe" set PHP=C:\laragon\bin\php\php.exe
where php >nul 2>nul && set PHP=php
echo.
echo  LuckyDraw is starting on port 8080 ...
echo  Open on this PC:      http://localhost:8080/
echo  Open on the network:  http://YOUR-IP:8080/   (see: ipconfig)
echo  Press Ctrl+C to stop.
echo.
set PHP_CLI_SERVER_WORKERS=8
"%PHP%" -S 0.0.0.0:8080 index.php
pause
