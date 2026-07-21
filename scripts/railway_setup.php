<?php
/**
 * Railway Auto-Setup Script
 * -------------------------
 * Runs automatically on first boot. It:
 *   1. Detects Railway MySQL env vars (MYSQLHOST, MYSQL_DATABASE, etc.)
 *   2. Imports database/schema.sql if tables don't exist yet
 *   3. Creates a default President account
 *
 * Safe to re-run: it checks if setup is already done before doing anything.
 */

// ---- Bootstrap config + DB ----
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
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

// ---- Parse DATABASE_URL if provided (Railway, Render, etc.) ----
$dbUrl = getenv('DATABASE_URL') ?: getenv('MYSQL_URL');
if ($dbUrl) {
    // Format: mysql://user:pass@host:port/dbname
    $url = parse_url($dbUrl);
    if ($url) {
        putenv('DB_HOST=' . ($url['host'] ?? '127.0.0.1'));
        putenv('DB_PORT=' . ($url['port'] ?? '3306'));
        putenv('DB_NAME=' . ltrim($url['path'] ?? '/ikizere_funds', '/'));
        putenv('DB_USER=' . ($url['user'] ?? 'root'));
        putenv('DB_PASS=' . ($url['pass'] ?? ''));
    }
}

// ---- Map Railway's MySQL env vars to our DB_* constants ----
// Railway provides: MYSQLHOST, MYSQLPORT, MYSQL_DATABASE, MYSQL_USER, MYSQL_PASSWORD
if (!getenv('DB_HOST') && getenv('MYSQLHOST')) {
    putenv('DB_HOST=' . getenv('MYSQLHOST'));
}
if (!getenv('DB_PORT') && getenv('MYSQLPORT')) {
    putenv('DB_PORT=' . getenv('MYSQLPORT'));
}
if (!getenv('DB_NAME') && getenv('MYSQL_DATABASE')) {
    putenv('DB_NAME=' . getenv('MYSQL_DATABASE'));
}
if (!getenv('DB_USER') && getenv('MYSQL_USER')) {
    putenv('DB_USER=' . getenv('MYSQL_USER'));
}
if (!getenv('DB_PASS') && getenv('MYSQL_PASSWORD')) {
    putenv('DB_PASS=' . getenv('MYSQL_PASSWORD'));
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'ikizere_funds';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

echo "[Railway Setup] DB_HOST={$host} DB_PORT={$port} DB_NAME={$name} DB_USER={$user}\n";

// ---- Connect ----
try {
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    echo "[Railway Setup] ERROR: Cannot connect to MySQL: " . $e->getMessage() . "\n";
    exit(1);
}

// ---- Create database if it doesn't exist ----
$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$name}`");

// ---- Check if already set up ----
try {
    $result = $pdo->query("SELECT COUNT(*) FROM roles")->fetchColumn();
    // Also check that core tables exist
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $hasUsers = in_array('users', $tables);
    if ($result > 0 && $hasUsers) {
        echo "[Railway Setup] Database already seeded ({$result} roles, " . count($tables) . " tables). Skipping import.\n";
    } else {
        // Partial import — drop everything and start fresh
        echo "[Railway Setup] Incomplete schema detected. Dropping and re-importing...\n";
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        foreach ($tables as $t) {
            $pdo->exec("DROP TABLE IF EXISTS `$t`");
        }
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        throw new Exception("empty");
    }
} catch (Exception $e) {
    // ---- Import schema.sql ----
    echo "[Railway Setup] Importing database/schema.sql ...\n";
    $schemaFile = __DIR__ . '/../database/schema.sql';
    if (!file_exists($schemaFile)) {
        echo "[Railway Setup] ERROR: database/schema.sql not found.\n";
        exit(1);
    }

    $sql = file_get_contents($schemaFile);
    // Remove multi-line comments (-- ...)
    $sql = preg_replace('/--[^\n]*/', '', $sql);
    // Remove block comments
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
    // Remove CREATE DATABASE / USE statements (we already created it)
    $sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql);
    $sql = preg_replace('/USE\s+`?[\w]+`?\s*;/is', '', $sql);

    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $imported = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
            $imported++;
        } catch (PDOException $e) {
            // Skip duplicate table/index errors on re-runs
            if (!str_contains($e->getMessage(), 'already exists')) {
                echo "[Railway Setup] Warning: {$e->getMessage()}\n";
            }
        }
    }
    echo "[Railway Setup] Imported {$imported} SQL statements.\n";
}

// ---- Create all leadership accounts (skip existing) ----
$leaders = [
    ['president',      'Club President',       'president',      'President@123',      'president@ikizere-funds.railway.app',      '+250700000001'],
    ['vice_president', 'Vice President',       'vicepresident',  'VicePresident@123',  'vicepresident@ikizere-funds.railway.app',  '+250700000002'],
    ['secretary',      'Secretary',            'secretary',      'Secretary@123',      'secretary@ikizere-funds.railway.app',      '+250700000003'],
    ['accountant',     'Accountant',           'accountant',     'Accountant@123',     'accountant@ikizere-funds.railway.app',     '+250700000004'],
    ['auditor',        'Auditor',              'auditor',        'Auditor@123',        'auditor@ikizere-funds.railway.app',        '+250700000005'],
];

$roleStmt  = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
$checkStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
$userStmt  = $pdo->prepare(
    'INSERT INTO users (role_id, full_name, username, email, phone, password_hash, status)
     VALUES (?, ?, ?, ?, ?, ?, "active")'
);

$created = 0;
foreach ($leaders as [$role, $name, $user, $pass, $email, $phone]) {
    $checkStmt->execute([$user]);
    if ($checkStmt->fetchColumn() > 0) {
        continue;
    }
    $roleStmt->execute([$role]);
    $roleId = $roleStmt->fetchColumn();
    if ($roleId) {
        $userStmt->execute([$roleId, $name, $user, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
        echo "[Railway Setup] Created {$role}: {$user} / {$pass}\n";
        $created++;
    }
}

if ($created > 0) {
    echo "[Railway Setup] Created {$created} new user(s). >>> CHANGE ALL PASSWORDS after first login! <<<\n";
} else {
    echo "[Railway Setup] All leadership accounts already exist.\n";
}

echo "[Railway Setup] Done.\n";
