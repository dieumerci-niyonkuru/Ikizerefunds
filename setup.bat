@echo off
echo ============================================
echo   IKIZERE FUNDS - Local Setup
echo ============================================
echo.

set MYSQL="C:/xampp/mysql/bin/mysql.exe"
set PHP="C:/xampp/php/php.exe"
set DB_NAME=ikizere_funds
set DB_USER=root
set DB_PASS=
set PROJECT_DIR=%~dp0

echo [1/4] Creating database...
%MYSQL% -u root -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if %errorlevel% neq 0 (
    echo ERROR: MySQL is not running. Start MySQL from XAMPP Control Panel first!
    pause
    exit /b 1
)
echo      Database created.

echo [2/4] Importing schema...
%MYSQL% -u root %DB_NAME% < "%PROJECT_DIR%database\schema.sql"
echo      Schema imported.

echo [3/4] Creating admin user...
%PHP% "%PROJECT_DIR%scripts\create_admin.php" "Club President" admin "Admin@12345" admin@ikizere.local +250700000000 president
echo      Admin user created.

echo [4/4] Starting server...
echo.
echo ============================================
echo   OPEN: http://localhost:8000
echo   LOGIN: admin / Admin@12345
echo ============================================
echo.
%PHP% -S localhost:8000 -t "%PROJECT_DIR%"
