<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

require_once __DIR__ . '/includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_reset') {
    verifyCsrf();
    $identifier = trim($_POST['username'] ?? '');

    if ($identifier !== '') {
        // Accept either a username or the email on file.
        $stmt = db()->prepare(
            'SELECT id, full_name, username, email FROM users
             WHERE (username = ? OR (email IS NOT NULL AND email = ?)) AND status = "active"'
        );
        $stmt->execute([$identifier, $identifier]);
        $target = $stmt->fetch();

        if ($target) {
            // Throttle: at most 3 live tokens per user per 15 minutes, so this
            // form cannot be used to flood someone's inbox.
            $recent = db()->prepare(
                'SELECT COUNT(*) FROM password_resets
                 WHERE user_id = ? AND used_at IS NULL AND created_at > (NOW() - INTERVAL 15 MINUTE)'
            );
            $recent->execute([$target['id']]);

            if ((int) $recent->fetchColumn() < 3) {
                // Keep the plaintext token; store only its hash. Previously the
                // token was hashed and immediately discarded, so no reset link
                // could ever be redeemed.
                $token = bin2hex(random_bytes(32));

                db()->prepare(
                    'INSERT INTO password_resets (user_id, token_hash, expires_at)
                     VALUES (?, ?, NOW() + INTERVAL 1 HOUR)'
                )->execute([$target['id'], hash('sha256', $token)]);

                $emailed = false;

                if (mailerConfigured() && !empty($target['email'])) {
                    $link = rtrim(APP_URL, '/') . '/reset_password.php?token=' . $token;

                    $body = "Hello {$target['full_name']},\n\n"
                        . "We received a request to reset the password for your "
                        . APP_NAME . " account ({$target['username']}).\n\n"
                        . "Open this link to choose a new password. It expires in one hour "
                        . "and can only be used once:\n\n{$link}\n\n"
                        . "If you did not ask for this, you can ignore this email — "
                        . "your password has not changed.";

                    $html = notificationEmailHtml('Reset your password', $body, $target['full_name'])
                        . '<p style="font-family:sans-serif;font-size:12px;color:#6B7280;'
                        . 'max-width:560px;margin:12px auto 0;word-break:break-all;">'
                        . 'If the button does not work, paste this into your browser:<br>'
                        . mailerEscape($link) . '</p>';

                    $emailed = sendMail(
                        $target['email'],
                        $target['full_name'],
                        'Reset your ' . APP_NAME . ' password',
                        $body,
                        $html
                    )['ok'];
                }

                // Fall back to the staff-fulfilled workflow whenever we could
                // not put a link in the member's hands ourselves.
                if (!$emailed) {
                    $leaders = db()->query(
                        "SELECT users.id FROM users JOIN roles ON roles.id = users.role_id
                         WHERE roles.name IN ('president','vice_president','secretary')
                           AND users.status = 'active'"
                    )->fetchAll();

                    foreach ($leaders as $leader) {
                        queueNotification((int) $leader['id'], 'password_reset_request', [
                            'name' => $target['full_name'],
                            'username' => $target['username'],
                        ]);
                    }
                }
            }
        }
    }

    // Always the same message, whether or not the account exists, so this form
    // cannot be used to discover which usernames are registered.
    setFlash('success', 'If that account exists, we have sent a reset link to the email on file. '
        . 'If no email is registered, club leadership has been notified and will help you.');
    redirect('forgot_password.php');
}

$pageTitle = 'Reset Password';
$pageDescription = 'Request a password reset for your IKIZERE FUNDS Club member account.';

require __DIR__ . '/includes/header.php';
?>
<div class="card auth-card text-center">
    <?php if ($siteLogo): ?>
        <img src="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>" alt="" class="h-16 w-16 mx-auto mb-4 rounded-lg bg-white p-2 object-contain shadow-md border border-gray-200">
    <?php endif; ?>
    <h2>Forgot Password</h2>
    <p class="text-gray-500 text-sm">Enter your username or the email address on your account.
    We'll send you a link to choose a new password &mdash; it expires in one hour.
    If your account has no email on file, club leadership is notified instead and will
    reset it for you.</p>
    <form method="post" action="" data-loading-text="Submitting your request…">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="request_reset">
        <label for="username">Username or email</label>
        <input type="text" id="username" name="username" required autofocus
               autocomplete="username" placeholder="president or you@example.com">
        <button type="submit">Send reset link</button>
    </form>
    <p class="text-center text-sm text-gray-500 mt-4">
        <a href="<?= e(APP_URL) ?>/login.php">&larr; Back to login</a>
    </p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
