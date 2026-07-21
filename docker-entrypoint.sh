#!/bin/bash
set -e

echo "=== IKIZERE FUNDS — Docker Setup ==="

# ---- Parse DATABASE_URL if provided (Railway, Render, etc.) ----
if [ -n "$DATABASE_URL" ]; then
    echo "Parsing DATABASE_URL..."
    DB_URL="${DATABASE_URL#mysql://}"
    DB_USER_ENV=$(echo "$DB_URL" | cut -d':' -f1)
    DB_PASS_ENV=$(echo "$DB_URL" | cut -d':' -f2 | cut -d'@' -f1)
    DB_HOST_ENV=$(echo "$DB_URL" | cut -d'@' -f2 | cut -d':' -f1)
    DB_PORT_ENV=$(echo "$DB_URL" | cut -d'@' -f2 | cut -d':' -f2 | cut -d'/' -f1)
    DB_NAME_ENV=$(echo "$DB_URL" | cut -d'/' -f2 | cut -d'?' -f1)

    export DB_HOST="${DB_HOST:-$DB_HOST_ENV}"
    export DB_PORT="${DB_PORT:-$DB_PORT_ENV}"
    export DB_NAME="${DB_NAME:-$DB_NAME_ENV}"
    export DB_USER="${DB_USER:-$DB_USER_ENV}"
    export DB_PASS="${DB_PASS:-$DB_PASS_ENV}"
fi

# ---- Fallback: Railway standard MySQL env vars ----
export DB_HOST="${DB_HOST:-${MYSQLHOST:-127.0.0.1}}"
export DB_PORT="${DB_PORT:-${MYSQLPORT:-3306}}"
export DB_NAME="${DB_NAME:-${MYSQL_DATABASE:-ikizere_funds}}"
export DB_USER="${DB_USER:-${MYSQL_USER:-root}}"
export DB_PASS="${DB_PASS:-${MYSQL_PASSWORD:-}}"

echo "FINAL DB: host=$DB_HOST port=$DB_PORT name=$DB_NAME user=$DB_USER"

# ---- Wait for MySQL to be ready ----
echo "Waiting for MySQL..."
for i in $(seq 1 30); do
    if php -r "
        try { new PDO('mysql:host=${DB_HOST};port=${DB_PORT}', '${DB_USER}', '${DB_PASS}'); exit(0); }
        catch (Exception \$e) { exit(1); }
    " 2>/dev/null; then
        echo " MySQL is ready."
        break
    fi
    echo "  Attempt $i/30..."
    sleep 2
done

# ---- Run the setup script ----
php /var/www/html/scripts/railway_setup.php

# ---- Start PHP built-in server ----
PORT="${PORT:-8080}"
echo "Starting server on port $PORT..."
exec php -S 0.0.0.0:$PORT -t /var/www/html
