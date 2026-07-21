<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('password_resets.manage');
$user = currentUser();

// ------------------------------------------------------------
// Fulfill a pending reset request (or an ad-hoc reset)
// ------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    verifyCsrf();
    $targetUserId = (int) ($_POST['user_id'] ?? 0);
    $requestId = (int) ($_POST['request_id'] ?? 0);

    $stmt = db()->prepare('SELECT id, username FROM users WHERE id = ?');
    $stmt->execute([$targetUserId]);
    $target = $stmt->fetch();

    if (!$target) {
        setFlash('error', 'User not found.');
    } else {
        $newPassword = bin2hex(random_bytes(4));
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($newPassword, PASSWORD_DEFAULT), $target['id']]);

        if ($requestId) {
            db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([$requestId]);
        }

        setFlash('success', "New temporary password for '{$target['username']}': {$newPassword} (share securely).");
    }
    redirect('modules/password_resets/index.php');
}

$pendingRequests = db()->query(
    "SELECT password_resets.id, password_resets.created_at, password_resets.expires_at,
            users.id AS user_id, users.username, users.full_name
     FROM password_resets
     JOIN users ON users.id = password_resets.user_id
     WHERE password_resets.used_at IS NULL AND password_resets.expires_at > NOW()
     ORDER BY password_resets.created_at DESC"
)->fetchAll();

$allUsers = db()->query(
    "SELECT id, username, full_name FROM users WHERE status = 'active' ORDER BY full_name"
)->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Password Resets</h1>
    <p class="text-gray-500 text-sm">There's no automated email delivery configured, so resets are
    completed by a leader here and the new temporary password is shared with the member directly.</p>
</div>

<div class="card">
    <h2>Pending Requests</h2>
    <table>
        <thead><tr><th>Username</th><th>Name</th><th>Requested</th><th>Expires</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pendingRequests as $r): ?>
            <tr>
                <td><?= e($r['username']) ?></td>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e($r['created_at']) ?></td>
                <td><?= e($r['expires_at']) ?></td>
                <td>
                    <form method="post" style="display:inline-block">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" value="<?= e((string) $r['user_id']) ?>">
                        <input type="hidden" name="request_id" value="<?= e((string) $r['id']) ?>">
                        <button type="submit">Reset Password</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pendingRequests): ?>
            <tr><td colspan="5">No pending reset requests.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card max-w-lg">
    <h2>Reset Any User's Password</h2>
    <p class="text-gray-500 text-sm">Use this if a member or leader asks for help directly, without submitting the Forgot Password form.</p>
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="request_id" value="0">
        <label for="user_id">User</label>
        <select id="user_id" name="user_id" required>
            <option value="">-- Select user --</option>
            <?php foreach ($allUsers as $u): ?>
                <option value="<?= e((string) $u['id']) ?>"><?= e($u['username'] . ' - ' . $u['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit">Reset Password</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
