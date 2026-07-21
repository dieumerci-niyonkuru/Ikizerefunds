<?php
// One-time bootstrap: creates the first login (normally the President)
// since a fresh database has no user accounts yet.
//
// Usage (all args optional, you'll be prompted for anything missing):
//   php scripts/create_admin.php "Full Name" username password email phone role
//
// Example:
//   php scripts/create_admin.php "Jean Claude" jclaude "StrongPass123!" jc@example.com 0788000000 president

require_once __DIR__ . '/../config/database.php';

function prompt(string $label, ?string $default = null): string
{
    $suffix = $default !== null ? " [{$default}]" : '';
    fwrite(STDOUT, "{$label}{$suffix}: ");
    $value = trim(fgets(STDIN));
    return $value === '' ? (string) $default : $value;
}

$fullName = $argv[1] ?? prompt('Full name');
$username = $argv[2] ?? prompt('Username');
$password = $argv[3] ?? prompt('Password');
$email    = $argv[4] ?? prompt('Email (optional)', '');
$phone    = $argv[5] ?? prompt('Phone (optional)', '');
$role     = $argv[6] ?? prompt('Role', 'president');

if ($fullName === '' || $username === '' || $password === '') {
    fwrite(STDERR, "Full name, username, and password are required.\n");
    exit(1);
}

$pdo = db();

$roleStmt = $pdo->prepare('SELECT id FROM roles WHERE name = ?');
$roleStmt->execute([$role]);
$roleId = $roleStmt->fetchColumn();

if (!$roleId) {
    fwrite(STDERR, "Unknown role '{$role}'. Valid roles: president, vice_president, secretary, accountant, auditor, member.\n");
    exit(1);
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO users (role_id, full_name, username, email, phone, password_hash, status)
         VALUES (?, ?, ?, ?, ?, ?, "active")'
    );
    $stmt->execute([
        $roleId,
        $fullName,
        $username,
        $email !== '' ? $email : null,
        $phone !== '' ? $phone : null,
        password_hash($password, PASSWORD_DEFAULT),
    ]);
    fwrite(STDOUT, "Created '{$role}' account '{$username}'. You can now log in.\n");
} catch (PDOException $e) {
    fwrite(STDERR, "Could not create user: username or email may already be in use.\n");
    exit(1);
}
