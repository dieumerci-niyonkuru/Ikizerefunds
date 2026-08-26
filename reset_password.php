<?php
// Redeems a password-reset token emailed by forgot_password.php.
//
// The database stores only sha256(token); the plaintext lives in the emailed
// link. A token is valid for one hour, works once, and using it invalidates
// every other outstanding token for that account.

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

const RESET_MIN_PASSWORD_LENGTH = 8;

// Looks up a live reset row for the given plaintext token.
function findResetToken(string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT pr.id, pr.user_id, u.full_name, u.username
         FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ?
           AND pr.used_at IS NULL
           AND pr.expires_at > NOW()
           AND u.status = "active"
         LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);

    return $stmt->fetch() ?: null;
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$reset = findResetToken($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_password') {
    verifyCsrf();

    $password = (string) ($_POST['password'] ?? '');
    $confirm  = (string) ($_POST['password_confirm'] ?? '');

    if (!$reset) {
        setFlash('error', 'That reset link is invalid or has expired. Please request a new one.');
        redirect('forgot_password.php');
    }

    if (strlen($password) < RESET_MIN_PASSWORD_LENGTH) {
        setFlash('error', 'Your password must be at least ' . RESET_MIN_PASSWORD_LENGTH . ' characters.');
    } elseif ($password !== $confirm) {
        setFlash('error', 'The two passwords do not match.');
    } else {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $reset['user_id']]);

            // Burn this token and every other outstanding one for the account.
            $pdo->prepare(
                'UPDATE password_resets SET used_at = NOW()
                 WHERE user_id = ? AND used_at IS NULL'
            )->execute([$reset['user_id']]);

            // A successful reset clears any lockout from failed logins.
            $pdo->prepare('DELETE FROM login_attempts WHERE username = ?')
                ->execute([$reset['username']]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            setFlash('error', 'Could not update your password. Please try again.');
            redirect('reset_password.php?token=' . urlencode($token));
        }

        auditLog((int) $reset['user_id'], 'password_reset', 'users', (int) $reset['user_id'],
            'Self-service reset via emailed link');

        setFlash('success', 'Your password has been changed. You can sign in now.');
        redirect('login.php');
    }

    redirect('reset_password.php?token=' . urlencode($token));
}

$pageTitle = 'Choose a New Password';
$pageDescription = 'Set a new password for your IKIZERE FUNDS Club account.';

require __DIR__ . '/includes/header.php';
?>
<div class="card auth-card text-center">
    <?php if ($siteLogo): ?>
        <img src="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>" alt="" class="h-16 w-16 mx-auto mb-4 rounded-lg bg-white p-2 object-contain shadow-md border border-gray-200">
    <?php endif; ?>

    <?php if (!$reset): ?>
        <h2>Link expired</h2>
        <p class="text-gray-500 text-sm">This reset link is invalid, has already been used, or is more
        than an hour old. Request a fresh one and we'll email you another.</p>
        <a class="btn-gold w-full mt-4" href="<?= e(APP_URL) ?>/forgot_password.php">
            Request a new link
        </a>
    <?php else: ?>
        <h2>Choose a new password</h2>
        <p class="text-gray-500 text-sm">Signing in as
        <strong class="text-primary-dark"><?= e($reset['username']) ?></strong>.
        Pick something at least <?= RESET_MIN_PASSWORD_LENGTH ?> characters long.</p>

        <form method="post" action="" data-loading-text="Saving your new password…">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="set_password">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <label for="password">New password</label>
            <input type="password" id="password" name="password" required autofocus
                   minlength="<?= RESET_MIN_PASSWORD_LENGTH ?>" autocomplete="new-password">

            <label for="password_confirm">Confirm new password</label>
            <input type="password" id="password_confirm" name="password_confirm" required
                   minlength="<?= RESET_MIN_PASSWORD_LENGTH ?>" autocomplete="new-password">

            <button type="submit">Change password</button>
        </form>
    <?php endif; ?>

    <p class="text-center text-sm text-gray-500 mt-4">
        <a href="<?= e(APP_URL) ?>/login.php">&larr; Back to login</a>
    </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
