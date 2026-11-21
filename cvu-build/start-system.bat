@echo off
cd /d "%~dp0"
set "ISERVE_PHP=php"
if exist "C:\xampp\php\php.exe" set "ISERVE_PHP=C:\xampp\php\php.exe"
"%ISERVE_PHP%" artisan serve --host=127.0.0.1 --port=8001
goto end
:failed
echo Setup failed. Review the error above.
pause
:end

