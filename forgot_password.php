<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_reset') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');

    if ($username !== '') {
        $stmt = db()->prepare('SELECT id FROM users WHERE username = ? AND status = "active"');
        $stmt->execute([$username]);
        $userId = $stmt->fetchColumn();

        if ($userId) {
            $tokenHash = hash('sha256', bin2hex(random_bytes(32)));
            $stmt = db()->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 DAY)'
            );
            $stmt->execute([$userId, $tokenHash]);

            // Notify all leadership (president, vice_president, secretary) about this reset request
            $targetUser = db()->prepare('SELECT full_name, username FROM users WHERE id = ?');
            $targetUser->execute([$userId]);
            $targetInfo = $targetUser->fetch();

            $leaders = db()->query(
                "SELECT users.id FROM users JOIN roles ON roles.id = users.role_id
                 WHERE roles.name IN ('president','vice_president','secretary') AND users.status = 'active'"
            )->fetchAll();

            foreach ($leaders as $leader) {
                queueNotification((int) $leader['id'], 'password_reset_request', [
                    'name' => $targetInfo['full_name'] ?? 'Unknown',
                    'username' => $targetInfo['username'] ?? '',
                ]);
            }
        }
    }

    // Always show the same message, whether or not the username exists,
    // so this form can't be used to check which usernames are registered.
    setFlash('success', 'If that username exists, club leadership has been notified and will help you reset your password.');
    redirect('forgot_password.php');
}

require __DIR__ . '/includes/header.php';
?>
<div class="card auth-card">
    <h2>Forgot Password</h2>
    <p class="text-gray-500 text-sm">This system doesn't send reset emails automatically. Submitting this
    logs a reset request that club leadership can act on &mdash; they'll set a new temporary password and
    share it with you securely (in person, by phone, etc.).</p>
    <form method="post" action="" data-loading-text="Submitting your request…">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="request_reset">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" required autofocus>
        <button type="submit">Request Password Reset</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-4">
        <a href="<?= e(APP_URL) ?>/login.php">&larr; Back to login</a>
    </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
