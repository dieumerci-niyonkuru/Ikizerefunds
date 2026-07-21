<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('membership_requests.manage');
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    verifyCsrf();
    $id = (int) ($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (in_array($status, ['approved', 'rejected'], true)) {
        $stmt = db()->prepare('UPDATE membership_requests SET status = ?, reviewed_by = ? WHERE id = ?');
        $stmt->execute([$status, $user['id'], $id]);
        setFlash('success', 'Request updated.');
    }
    redirect('modules/membership_requests/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_request') {
    verifyCsrf();
    $id = (int) ($_POST['request_id'] ?? 0);
    db()->prepare('DELETE FROM membership_requests WHERE id = ?')->execute([$id]);
    setFlash('success', 'Request deleted.');
    redirect('modules/membership_requests/index.php');
}

$requests = db()->query('SELECT * FROM membership_requests ORDER BY created_at DESC')->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Membership Requests</h1>
    <p class="text-gray-500 text-sm">Submitted through the public "Request to Join" form on the Membership page.</p>
</div>

<div class="card">
    <table>
        <thead><tr><th>Name</th><th>Contact</th><th>Message</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($requests as $r): ?>
            <tr>
                <td><?= e($r['full_name']) ?></td>
                <td>
                    <?php if ($r['email']): ?><?= e($r['email']) ?><br><?php endif; ?>
                    <?php if ($r['phone']): ?><?= e($r['phone']) ?><?php endif; ?>
                </td>
                <td><?= e($r['message']) ?></td>
                <td><?= statusBadge($r['status']) ?></td>
                <td><?= e($r['created_at']) ?></td>
                <td>
                    <?php if ($r['status'] === 'pending'): ?>
                        <form method="post" style="display:inline-block">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="request_id" value="<?= e((string) $r['id']) ?>">
                            <input type="hidden" name="status" value="approved">
                            <button type="submit">Approve</button>
                        </form>
                        <form method="post" style="display:inline-block">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="request_id" value="<?= e((string) $r['id']) ?>">
                            <input type="hidden" name="status" value="rejected">
                            <button type="submit">Reject</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($r['status'] === 'approved'): ?>
                        <a class="btn" href="<?= e(APP_URL) ?>/modules/members/index.php?prefill_name=<?= urlencode($r['full_name']) ?>&prefill_email=<?= urlencode((string) $r['email']) ?>&prefill_phone=<?= urlencode((string) $r['phone']) ?>">Register as Member</a>
                    <?php endif; ?>
                    <form method="post" style="display:inline-block" onsubmit="return confirm('Are you sure? This cannot be undone.')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_request">
                        <input type="hidden" name="request_id" value="<?= e((string) $r['id']) ?>">
                        <button type="submit" style="background:#dc2626;color:#fff;">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$requests): ?>
            <tr><td colspan="6">No membership requests yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
