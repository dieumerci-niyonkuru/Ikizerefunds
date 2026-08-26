<?php
// Replaces any account still using a password published in README.md with a
// freshly generated strong one.
//
//   php scripts/rotate_passwords.php --dry-run     # show what would change
//   php scripts/rotate_passwords.php               # rotate the default ones
//   php scripts/rotate_passwords.php --user=president
//   php scripts/rotate_passwords.php --all         # every active account
//
// The new passwords are printed ONCE and never written to a file or the audit
// log. Copy them somewhere safe before closing the terminal.

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$args   = array_slice($argv, 1);
$dryRun = in_array('--dry-run', $args, true);
$all    = in_array('--all', $args, true);
$only   = null;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--user=')) {
        $only = substr($arg, 7);
    }
}

// Passwords that appear in README.md and .env.example.
$published = [
    'president'     => 'President@123',
    'vicepresident' => 'VicePresident@123',
    'secretary'     => 'Secretary@123',
    'accountant'    => 'Accountant@123',
    'auditor'       => 'Auditor@123',
    'admin'         => 'Admin@12345',
];

/**
 * Generates a readable but strong password: 4 groups of 4 from an alphabet
 * with look-alike characters (0/O, 1/l/I) removed, so it can be dictated over
 * the phone without confusion.
 */
function generatePassword(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $max = strlen($alphabet) - 1;

    $groups = [];
    for ($g = 0; $g < 4; $g++) {
        $chunk = '';
        for ($i = 0; $i < 4; $i++) {
            $chunk .= $alphabet[random_int(0, $max)];
        }
        $groups[] = $chunk;
    }

    // A symbol and a digit guarantee it satisfies common complexity rules.
    return implode('-', $groups) . '#' . random_int(10, 99);
}

$pdo = db();

$sql = 'SELECT u.id, u.username, u.full_name, u.password_hash, r.name AS role
        FROM users u JOIN roles r ON r.id = u.role_id
        WHERE u.status = "active"';
$params = [];

if ($only !== null) {
    $sql .= ' AND u.username = ?';
    $params[] = $only;
}

$sql .= ' ORDER BY u.id';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

if (!$users) {
    fwrite(STDERR, $only !== null
        ? "No active account named '{$only}'.\n"
        : "No active accounts found.\n");
    exit(1);
}

// Decide who needs rotating.
$targets = [];

foreach ($users as $u) {
    $isDefault = isset($published[$u['username']])
        && password_verify($published[$u['username']], $u['password_hash']);

    if ($all || $only !== null || $isDefault) {
        $targets[] = $u + ['was_default' => $isDefault];
    }
}

if (!$targets) {
    echo "Nothing to do — no account is using a password published in README.md.\n";
    echo "Use --all to rotate every active account anyway.\n";
    exit(0);
}

echo "=== " . APP_NAME . " — password rotation ===\n";
echo "Database: " . DB_HOST . '/' . DB_NAME . "\n\n";

if ($dryRun) {
    echo "DRY RUN — nothing will be changed.\n\n";
    printf("  %-16s %-16s %s\n", 'USERNAME', 'ROLE', 'STATUS');
    printf("  %-16s %-16s %s\n", str_repeat('-', 16), str_repeat('-', 16), str_repeat('-', 24));

    foreach ($targets as $t) {
        printf("  %-16s %-16s %s\n", $t['username'], $t['role'],
            $t['was_default'] ? 'using published default' : 'would be rotated');
    }

    echo "\nRun without --dry-run to rotate " . count($targets) . " account(s).\n";
    exit(0);
}

$update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$rotated = [];

foreach ($targets as $t) {
    $password = generatePassword();
    $update->execute([password_hash($password, PASSWORD_DEFAULT), $t['id']]);

    // Record that a rotation happened — never what it was changed to.
    auditLog((int) $t['id'], 'password_rotated', 'users', (int) $t['id'],
        'Rotated via scripts/rotate_passwords.php');

    $rotated[] = ['username' => $t['username'], 'role' => $t['role'], 'password' => $password];
}

echo "Rotated " . count($rotated) . " account(s).\n\n";
printf("  %-16s %-16s %s\n", 'USERNAME', 'ROLE', 'NEW PASSWORD');
printf("  %-16s %-16s %s\n", str_repeat('-', 16), str_repeat('-', 16), str_repeat('-', 24));

foreach ($rotated as $r) {
    printf("  %-16s %-16s %s\n", $r['username'], $r['role'], $r['password']);
}

echo "\n";
echo "+----------------------------------------------------------------+\n";
echo "| SAVE THESE NOW. They are not stored anywhere and are not in the |\n";
echo "| audit log. Closing this terminal loses them, and you will need  |\n";
echo "| to rotate again.                                                |\n";
echo "+----------------------------------------------------------------+\n";
echo "\nEach holder should sign in and set their own password from Members -> Edit.\n";
