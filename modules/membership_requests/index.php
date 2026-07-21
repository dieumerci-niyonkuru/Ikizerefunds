<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/notifications.php';

requirePermission('membership_requests.manage');
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    verifyCsrf();
    $id = (int) ($_POST['request_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (in_array($status, ['approved', 'rejected'], true)) {
        $stmt = db()->prepare('UPDATE membership_requests SET status = ?, reviewed_by = ? WHERE id = ?');
        $stmt->execute([$status, $user['id'], $id]);

        if ($status === 'approved') {
            $reqStmt = db()->prepare('SELECT full_name, email, phone FROM membership_requests WHERE id = ?');
            $reqStmt->execute([$id]);
            $request = $reqStmt->fetch();

            if ($request && $request['email']) {
                $emailUser = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $emailUser->execute([$request['email']]);
                $targetUserId = $emailUser->fetchColumn();

                if ($targetUserId) {
                    queueNotification((int) $targetUserId, 'membership_approval', [
                        'name' => $request['full_name'],
                        'approved_by' => $user['full_name'],
                    ]);
                }
            }

            setFlash('success', "Request approved. " . ($request['email'] ? "Notification sent to {$request['email']}." : "No email on file to notify."));
        } else {
            setFlash('success', 'Request rejected.');
        }
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

$requests = db()->query(
    'SELECT membership_requests.*, reviewer.full_name AS reviewed_by_name, reviewer.photo_path AS reviewed_by_photo
     FROM membership_requests
     LEFT JOIN users AS reviewer ON reviewer.id = membership_requests.reviewed_by
     ORDER BY membership_requests.created_at DESC'
)->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Membership Requests</h1>
    <p class="text-gray-500 text-sm">Submitted through the public "Request to Join" form on the Membership page.</p>
</div>

<div class="card">
    <div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Why Join?</th><th>Contact</th><th>Status</th><th>Reviewed By</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($requests as $r): ?>
            <tr>
                <td class="font-semibold"><?= e($r['full_name']) ?></td>
                <td class="text-gray-600 text-sm max-w-[200px]"><?= e($r['message'] ?: '—') ?></td>
                <td>
                    <?php if ($r['email']): ?><?= e($r['email']) ?><br><?php endif; ?>
                    <?php if ($r['phone']): ?><?= e($r['phone']) ?><?php endif; ?>
                </td>
                <td><?= statusBadge($r['status']) ?></td>
                <td>
                    <?php if ($r['reviewed_by_name']): ?>
                        <span class="flex items-center gap-1">
                            <?= avatarHtml($r['reviewed_by_photo'] ?? null, $r['reviewed_by_name'], 'w-5 h-5 text-[9px]') ?>
                            <?= e($r['reviewed_by_name']) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-gray-400">—</span>
                    <?php endif; ?>
                </td>
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
                        <a class="btn" href="<?= e(APP_URL) ?>/modules/members/index.php?prefill_name=<?= urlencode($r['full_name']) ?>&prefill_email=<?= urlencode((string) $r['email']) ?>&prefill_phone=<?= urlencode((string) $r['phone']) ?>&prefill_photo=<?= urlencode((string) ($r['photo_path'] ?? '')) ?>">Register as Member</a>
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
            <tr><td colspan="7">No membership requests yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
