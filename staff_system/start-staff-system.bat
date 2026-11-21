@echo off
setlocal
cd /d "%~dp0"
set "STAFF_PHP=C:\xampp\php\php.exe"
if not exist "%STAFF_PHP%" set "STAFF_PHP=php"
echo iServe Staff Portal - shared database pre_defense_system
echo Start MySQL in XAMPP before continuing.
"%STAFF_PHP%" upgrade-shared-database.php
if errorlevel 1 goto failed
"%STAFF_PHP%" artisan serve --host=127.0.0.1 --port=8002
goto end
:failed
echo Check the MySQL connection in .env. Existing data was not reset.
pause
:end
endlocal
