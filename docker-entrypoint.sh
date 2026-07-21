#!/bin/bash
set -e

echo "=== IKIZERE FUNDS — Docker Setup ==="

# ---- Auto-detect Railway MySQL env vars ----
export DB_HOST="${DB_HOST:-${MYSQLHOST:-127.0.0.1}}"
export DB_PORT="${DB_PORT:-${MYSQLPORT:-3306}}"
export DB_NAME="${DB_NAME:-${MYSQL_DATABASE:-ikizere_funds}}"
export DB_USER="${DB_USER:-${MYSQL_USER:-root}}"
export DB_PASS="${DB_PASS:-${MYSQL_PASSWORD:-}}"

echo "DB: ${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"

# ---- Wait for MySQL to be ready ----
echo "Waiting for MySQL..."
for i in $(seq 1 30); do
    if php -r "
        try { new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USER}', '${DB_PASS}'); echo 'ok'; exit(0); }
        catch (Exception \$e) { exit(1); }
    " 2>/dev/null; then
        echo " MySQL is ready."
        break
    fi
    sleep 2
done

# ---- Run the setup script ----
php /var/www/html/scripts/railway_setup.php

# ---- Start Apache ----
exec apache2-foreground
