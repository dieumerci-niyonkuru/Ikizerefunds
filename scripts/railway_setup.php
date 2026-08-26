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
            // Real environment variables always win — see config/config.php.
            if (getenv($key) === false) {
                $_ENV[$key] = $value;
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
// The president can be overridden with ADMIN_* env vars so a real deployment
// never has to go live on a published default password.
$adminUser  = getenv('ADMIN_USER')  ?: 'president';
$adminPass  = getenv('ADMIN_PASS')  ?: 'President@123';
$adminName  = getenv('ADMIN_NAME')  ?: 'Club President';
$adminEmail = getenv('ADMIN_EMAIL') ?: 'president@ikizere-funds.railway.app';
$adminPhone = getenv('ADMIN_PHONE') ?: '+250700000001';

$usingDefaultAdminPass = !getenv('ADMIN_PASS');

// The remaining committee logins are convenience seeds for a fresh install.
// Set SEED_ROLE_ACCOUNTS=0 to skip them and create those users by hand instead.
$seedRoleAccounts = getenv('SEED_ROLE_ACCOUNTS') !== '0';

$leaders = [
    ['president', $adminName, $adminUser, $adminPass, $adminEmail, $adminPhone],
];

if ($seedRoleAccounts) {
    array_push(
        $leaders,
        ['vice_president', 'Vice President', 'vicepresident', 'VicePresident@123', 'vicepresident@ikizere-funds.railway.app', '+250700000002'],
        ['secretary',      'Secretary',      'secretary',     'Secretary@123',     'secretary@ikizere-funds.railway.app',     '+250700000003'],
        ['accountant',     'Accountant',     'accountant',    'Accountant@123',    'accountant@ikizere-funds.railway.app',    '+250700000004'],
        ['auditor',        'Auditor',        'auditor',       'Auditor@123',       'auditor@ikizere-funds.railway.app',       '+250700000005'],
    );
}

$roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
// users.email is UNIQUE as well as username, so both have to be checked —
// otherwise a clash throws mid-boot and the platform restart-loops.
$checkStmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR (email IS NOT NULL AND email = ?)');
$userStmt  = $pdo->prepare(
    'INSERT INTO users (role_id, full_name, username, email, phone, password_hash, status)
     VALUES (?, ?, ?, ?, ?, ?, "active")'
);

$created = 0;
foreach ($leaders as [$role, $name, $user, $pass, $email, $phone]) {
    $checkStmt->execute([$user, $email]);
    if ($checkStmt->fetchColumn() > 0) {
        continue;
    }
    $roleStmt->execute([$role]);
    $roleId = $roleStmt->fetchColumn();
    if (!$roleId) {
        continue;
    }

    // Seeding a login must never be able to abort the boot.
    try {
        $userStmt->execute([$roleId, $name, $user, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
        echo "[Railway Setup] Created {$role}: {$user}\n";
        $created++;
    } catch (PDOException $e) {
        echo "[Railway Setup] Skipped {$role} ({$user}): {$e->getMessage()}\n";
    }
}

if ($created > 0) {
    echo "[Railway Setup] Created {$created} new user(s).\n";
    if ($usingDefaultAdminPass) {
        echo "[Railway Setup] !! WARNING: the president account is using the published default\n";
        echo "[Railway Setup] !! password. Set ADMIN_PASS in your Railway variables, or change it\n";
        echo "[Railway Setup] !! from Settings immediately after your first login.\n";
    }
} else {
    echo "[Railway Setup] All leadership accounts already exist.\n";
}

// ---- Ensure notification_templates type column supports new types (ALTER from ENUM to VARCHAR) ----
try {
    $pdo->exec("ALTER TABLE notification_templates MODIFY COLUMN type VARCHAR(50) NOT NULL");
    $pdo->exec("ALTER TABLE notifications MODIFY COLUMN type VARCHAR(50) NOT NULL");
    $pdo->exec("ALTER TABLE notifications MODIFY COLUMN channel VARCHAR(20) NOT NULL");
    $pdo->exec("ALTER TABLE notifications MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");
} catch (PDOException $e) {
    // Already VARCHAR or table doesn't exist — fine
}

// ---- Ensure notifications can record why a delivery failed ----
try {
    $pdo->exec("ALTER TABLE notifications ADD COLUMN error VARCHAR(255) NULL AFTER status");
    echo "[Railway Setup] Added error column to notifications.\n";
} catch (PDOException $e) {
    // Column already exists
}

// ---- Separate "has the member read it" from "did delivery succeed" ----
// These used to share the status column, so opening the inbox flipped a row to
// 'read' and it was never emailed. read_at now tracks reading; status tracks
// delivery only.
try {
    $pdo->exec("ALTER TABLE notifications ADD COLUMN read_at TIMESTAMP NULL DEFAULT NULL AFTER sent_at");
    echo "[Railway Setup] Added read_at column to notifications.\n";

    // Rows already marked 'read' were seen by the member and delivered under
    // the old stub — carry both facts across so they are not re-sent.
    $migrated = $pdo->exec(
        "UPDATE notifications SET read_at = COALESCE(sent_at, created_at), status = 'sent'
         WHERE status = 'read'"
    );
    if ($migrated) {
        echo "[Railway Setup] Migrated {$migrated} previously-read notification(s).\n";
    }
} catch (PDOException $e) {
    // Column already exists
}

// ---- Ensure password_reset_request notification template exists ----
$templateCheck = $pdo->prepare("SELECT COUNT(*) FROM notification_templates WHERE type = 'password_reset_request'");
$templateCheck->execute();
if ($templateCheck->fetchColumn() == 0) {
    $pdo->prepare("INSERT INTO notification_templates (type, subject, body) VALUES (?, ?, ?)")
        ->execute([
            'password_reset_request',
            'Password Reset Request',
            '{{username}} ({{name}}) has requested a password reset. Please set a new temporary password for them via the Password Resets page.',
        ]);
    echo "[Railway Setup] Added password_reset_request notification template.\n";
}

// ---- Ensure membership_requests has photo_path column ----
try {
    $pdo->exec("ALTER TABLE membership_requests ADD COLUMN photo_path VARCHAR(255) NULL AFTER message");
    echo "[Railway Setup] Added photo_path column to membership_requests.\n";
} catch (PDOException $e) {
    // Column already exists
}

// ---- Ensure membership_approval notification template exists ----
$templateCheck2 = $pdo->prepare("SELECT COUNT(*) FROM notification_templates WHERE type = 'membership_approval'");
$templateCheck2->execute();
if ($templateCheck2->fetchColumn() == 0) {
    $pdo->prepare("INSERT INTO notification_templates (type, subject, body) VALUES (?, ?, ?)")
        ->execute([
            'membership_approval',
            'Membership Approved',
            'Dear {{name}}, your request to join IKIZERE FUNDS Club has been approved by {{approved_by}}. Welcome to the club! Please contact the leadership to complete your registration.',
        ]);
    echo "[Railway Setup] Added membership_approval notification template.\n";
}

echo "[Railway Setup] Done.\n";
