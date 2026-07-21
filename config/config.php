<?php
// Central app configuration. Reads from environment variables so
// real credentials never need to be hardcoded/committed.

// ---- Load .env file if present (no Composer / vlucas/phpdotenv needed) ----
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim(trim($value), '"\'');
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key]            = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

// ---- Auto-detect Railway MySQL env vars (MYSQLHOST, MYSQL_DATABASE, etc.) ----
function env(string $key, string $default = ''): string {
    return getenv($key) ?: $default;
}
$railwayHost = env('MYSQLHOST');
define('DB_HOST', env('DB_HOST') ?: ($railwayHost ?: '127.0.0.1'));
define('DB_PORT', env('DB_PORT') ?: (env('MYSQLPORT') ?: '3306'));
define('DB_NAME', env('DB_NAME') ?: (env('MYSQL_DATABASE') ?: 'ikizere_funds'));
define('DB_USER', env('DB_USER') ?: (env('MYSQL_USER') ?: 'root'));
define('DB_PASS', env('DB_PASS') ?: env('MYSQL_PASSWORD', ''));

define('APP_NAME', 'IKIZERE FUNDS Club');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/ikizere_funds');

// Show errors only in development. Defaults to OFF (fail-safe) so a
// forgotten env var on a production host never leaks stack traces —
// local dev must set APP_DEBUG=1 explicitly.
define('APP_DEBUG', getenv('APP_DEBUG') === '1');
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// Session cookies: httponly + strict so JS can't read them and they
// aren't sent on cross-site navigations; secure (HTTPS-only) whenever
// the request actually arrived over HTTPS, including behind a
// TLS-terminating proxy/load balancer.
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['SERVER_PORT'] ?? null) === '443'
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Baseline security headers on every response.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
