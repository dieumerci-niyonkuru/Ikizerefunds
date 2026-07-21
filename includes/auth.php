<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

const MAX_LOGIN_ATTEMPTS = 5;
const LOGIN_LOCKOUT_MINUTES = 15;

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUser(): ?array
{
    if (!isLoggedIn()) {
        return null;
    }

    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare(
            'SELECT users.*, roles.name AS role_name
             FROM users
             JOIN roles ON roles.id = users.role_id
             WHERE users.id = ? AND users.status = "active"'
        );
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }

    return $user;
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

// $roles: list of allowed role names, e.g. ['president', 'accountant'].
// Reserved for the handful of "constitutional" pages (Board Terms, the
// Permissions manager itself) that must stay reachable no matter how the
// configurable permission matrix below gets edited — everything else
// should use requirePermission() instead.
function requireRole(array $roles): void
{
    requireLogin();
    $user = currentUser();
    if (!$user || !in_array($user['role_name'], $roles, true)) {
        http_response_code(403);
        die('You do not have permission to access this page.');
    }
}

// Checks the role_permissions table rather than a hardcoded role list, so
// access can be reconfigured from Permissions without touching code.
function userHasPermission(array $user, string $code): bool
{
    static $cache = [];
    $key = $user['role_id'] . ':' . $code;

    if (!array_key_exists($key, $cache)) {
        $stmt = db()->prepare(
            'SELECT 1 FROM role_permissions
             JOIN permissions ON permissions.id = role_permissions.permission_id
             WHERE role_permissions.role_id = ? AND permissions.code = ?'
        );
        $stmt->execute([$user['role_id'], $code]);
        $cache[$key] = (bool) $stmt->fetch();
    }

    return $cache[$key];
}

function requirePermission(string $code): void
{
    requireLogin();
    $user = currentUser();
    if (!userHasPermission($user, $code)) {
        http_response_code(403);
        die('You do not have permission to access this page.');
    }
}

// Used by the sidebar/dashboard to decide whether to show a nav entry at
// all. Nav items with a 'permission' key are checked against the live
// permission matrix (so reconfiguring Permissions updates the menu too,
// not just the page's own gate). Items with a 'roles' key are the two
// "constitutional" pages restricted to the literal president role by name.
// Items with *neither* key are universal — visible to any logged-in user
// regardless of role, including brand-new custom roles that aren't in any
// hardcoded list (the pages themselves only call requireLogin()).
function userCanSeeNavItem(array $user, array $item): bool
{
    if (isset($item['permission'])) {
        return userHasPermission($user, $item['permission']);
    }
    if (isset($item['roles'])) {
        return in_array($user['role_name'], $item['roles'], true);
    }
    return true;
}

function isRateLimited(string $username): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) AS attempts FROM login_attempts
         WHERE username = ? AND success = 0
           AND attempted_at > (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$username, LOGIN_LOCKOUT_MINUTES]);
    return (int) $stmt->fetch()['attempts'] >= MAX_LOGIN_ATTEMPTS;
}

function recordLoginAttempt(string $username, bool $success): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)'
    );
    $stmt->execute([$username, $_SERVER['REMOTE_ADDR'] ?? null, $success ? 1 : 0]);
}

// Returns true on success, or a string error message on failure.
function attemptLogin(string $username, string $password)
{
    if (isRateLimited($username)) {
        return 'Too many failed attempts. Please try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.';
    }

    $stmt = db()->prepare(
        'SELECT users.*, roles.name AS role_name
         FROM users
         JOIN roles ON roles.id = users.role_id
         WHERE users.username = ?'
    );
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
        recordLoginAttempt($username, false);
        return 'Invalid username or password.';
    }

    recordLoginAttempt($username, true);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

    return true;
}

function logoutUser(): void
{
    $_SESSION = [];
    session_destroy();
}
