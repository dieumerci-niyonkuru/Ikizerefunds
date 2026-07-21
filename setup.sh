#!/bin/bash
echo "============================================"
echo "  IKIZERE FUNDS - Local Setup"
echo "============================================"
echo ""

MYSQL="C:/xampp/mysql/bin/mysql.exe"
PHP="C:/xampp/php/php.exe"
DB_NAME="ikizere_funds"
DIR="$(cd "$(dirname "$0")" && pwd)"

echo "[1/4] Creating database..."
$MYSQL -u root -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if [ $? -ne 0 ]; then
    echo "ERROR: MySQL not running. Start MySQL from XAMPP Control Panel!"
    exit 1
fi
echo "      Database created."

echo "[2/4] Importing schema..."
$MYSQL -u root $DB_NAME < "$DIR/database/schema.sql"
echo "      Schema imported."

echo "[3/4] Creating admin user..."
$PHP "$DIR/scripts/create_admin.php" "Club President" admin "Admin@12345" admin@ikizere.local +250700000000 president
echo "      Admin user created."

echo "[4/4] Starting server..."
echo ""
echo "============================================"
echo "  OPEN: http://localhost:8000"
echo "  LOGIN: admin / Admin@12345"
echo "============================================"
echo ""
$PHP -S localhost:8000 -t "$DIR"
