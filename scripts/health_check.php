<?php
// Post-deploy health check. Run it after any deploy to confirm the app is
// actually wired up correctly:
//
//   php scripts/health_check.php
//
// On Railway: open the service, use the shell, and run the same command.
// Exits 0 when everything required is in place, 1 when something is broken.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$problems = [];
$warnings = [];

function line(string $status, string $label, string $detail = ''): void
{
    printf("  %-6s %-38s %s\n", $status, $label, $detail);
}

echo "=== " . APP_NAME . " health check ===\n\n";

// ---------------------------------------------------------------- database
echo "Database\n";

try {
    $pdo = db();
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    line('OK', 'Connection', DB_HOST . ':' . DB_PORT . '/' . DB_NAME . ' (' . $version . ')');
} catch (Throwable $e) {
    line('FAIL', 'Connection', $e->getMessage());
    echo "\nCannot continue without a database.\n";
    exit(1);
}

$tables = array_column($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM), 0);

$required = [
    'users', 'roles', 'members', 'savings', 'loans', 'meetings', 'notifications',
    'notification_templates', 'password_resets', 'feedback', 'membership_requests',
    'permissions', 'role_permissions', 'club_settings', 'audit_log', 'login_attempts',
];

$missingTables = array_diff($required, $tables);

if ($missingTables) {
    line('FAIL', 'Core tables', 'missing: ' . implode(', ', $missingTables));
    $problems[] = 'Schema is incomplete — re-run the setup script.';
} else {
    line('OK', 'Core tables', count($tables) . ' tables present');
}

// Columns added by migrations after the original schema. A missing one here
// means the setup script did not finish, and pages that use it will 500.
$expectedColumns = [
    'notifications'        => ['error', 'read_at'],
    'membership_requests'  => ['photo_path'],
];

foreach ($expectedColumns as $table => $columns) {
    if (!in_array($table, $tables, true)) {
        continue;
    }

    $have = array_column($pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(), 'Field');
    $missing = array_diff($columns, $have);

    if ($missing) {
        line('FAIL', "Migrations: {$table}", 'missing column(s): ' . implode(', ', $missing));
        $problems[] = "Table {$table} is missing " . implode('/', $missing)
            . ' — redeploy so railway_setup.php can add it.';
    } else {
        line('OK', "Migrations: {$table}", implode(', ', $columns));
    }
}

// ---------------------------------------------------------------- accounts
echo "\nAccounts\n";

$userCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();

if ($userCount === 0) {
    line('FAIL', 'Active users', 'none — nobody can log in');
    $problems[] = 'No active user accounts exist.';
} else {
    line('OK', 'Active users', (string) $userCount);
}

// Flag any account still using a password published in the README.
$published = [
    'president'     => 'President@123',
    'vicepresident' => 'VicePresident@123',
    'secretary'     => 'Secretary@123',
    'accountant'    => 'Accountant@123',
    'auditor'       => 'Auditor@123',
    'admin'         => 'Admin@12345',
];

$stillDefault = [];
$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE username = ?');

foreach ($published as $username => $password) {
    $stmt->execute([$username]);
    $hash = $stmt->fetchColumn();

    if ($hash !== false && password_verify($password, $hash)) {
        $stillDefault[] = $username;
    }
}

if ($stillDefault) {
    line('WARN', 'Default passwords', implode(', ', $stillDefault));
    $warnings[] = 'These accounts still use passwords published in README.md: '
        . implode(', ', $stillDefault) . '. Change them now.';
} else {
    line('OK', 'Default passwords', 'none in use');
}

// ---------------------------------------------------------------- delivery
echo "\nNotification delivery\n";

require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/sms.php';

if (mailerConfigured()) {
    $m = mailerConfig();
    line('OK', 'Email (SMTP)', $m['host'] . ':' . $m['port'] . ' (' . $m['secure'] . ')');
} else {
    line('INFO', 'Email (SMTP)', 'not configured — in-app inbox only');
}

if (smsConfigured()) {
    line('OK', 'SMS', smsConfig()['provider']);
} else {
    line('INFO', 'SMS', 'not configured — in-app inbox only');
}

$pending = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE status = 'pending'")->fetchColumn();
$failed  = (int) $pdo->query("SELECT COUNT(*) FROM notifications WHERE status = 'failed'")->fetchColumn();

if ($pending > 0 && !mailerConfigured() && !smsConfigured()) {
    line('INFO', 'Queue', "{$pending} pending (no gateway configured)");
} elseif ($pending > 0) {
    line('WARN', 'Queue', "{$pending} pending — is send_reminders.php scheduled?");
    $warnings[] = "{$pending} notification(s) are queued but undelivered. "
        . 'Schedule scripts/send_reminders.php to run daily.';
} else {
    line('OK', 'Queue', 'nothing pending');
}

if ($failed > 0) {
    line('WARN', 'Failures', "{$failed} failed delivery attempt(s)");
    $top = $pdo->query(
        "SELECT error, COUNT(*) n FROM notifications
         WHERE status = 'failed' AND error IS NOT NULL
         GROUP BY error ORDER BY n DESC LIMIT 3"
    )->fetchAll();

    foreach ($top as $t) {
        line('', '', "  [{$t['n']}x] {$t['error']}");
    }
}

// ---------------------------------------------------------------- runtime
echo "\nRuntime\n";

line('OK', 'PHP version', PHP_VERSION);
line(APP_DEBUG ? 'WARN' : 'OK', 'APP_DEBUG', APP_DEBUG ? 'ON — leaks stack traces' : 'off');

if (APP_DEBUG) {
    $warnings[] = 'APP_DEBUG is on. Unset it (or set 0) on a public deployment.';
}

foreach (['pdo_mysql', 'gd', 'fileinfo'] as $ext) {
    if (extension_loaded($ext)) {
        line('OK', "Extension: {$ext}", 'loaded');
    } else {
        line('FAIL', "Extension: {$ext}", 'missing');
        $problems[] = "PHP extension {$ext} is not loaded.";
    }
}

$uploads = __DIR__ . '/../assets/uploads';

if (is_writable($uploads)) {
    line('OK', 'Uploads writable', $uploads);
} else {
    line('FAIL', 'Uploads writable', $uploads);
    $problems[] = 'assets/uploads is not writable — photo and document uploads will fail.';
}

// ---------------------------------------------------------------- summary
echo "\n" . str_repeat('-', 62) . "\n";

foreach ($problems as $p) {
    echo "PROBLEM: {$p}\n";
}

foreach ($warnings as $w) {
    echo "WARNING: {$w}\n";
}

if (!$problems && !$warnings) {
    echo "All checks passed.\n";
    exit(0);
}

if (!$problems) {
    echo "\nNo blocking problems — review the warnings above.\n";
    exit(0);
}

echo "\n" . count($problems) . " problem(s) need fixing.\n";
exit(1);
