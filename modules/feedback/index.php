<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('feedback.review');
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_reviewed') {
    verifyCsrf();
    $id = (int) ($_POST['feedback_id'] ?? 0);
    db()->prepare("UPDATE feedback SET status = 'reviewed' WHERE id = ?")->execute([$id]);
    setFlash('success', 'Marked as reviewed.');
    redirect('modules/feedback/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_feedback') {
    verifyCsrf();
    $id = (int) ($_POST['feedback_id'] ?? 0);
    db()->prepare('DELETE FROM feedback WHERE id = ?')->execute([$id]);
    setFlash('success', 'Feedback deleted.');
    redirect('modules/feedback/index.php');
}

$feedback = db()->query('SELECT * FROM feedback ORDER BY created_at DESC')->fetchAll();

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Visitor Feedback &amp; Ideas</h1>
    <p class="text-gray-500 text-sm">Submitted through the public "Share an Idea" form.</p>
</div>

<div class="card">
    <table>
        <thead><tr><th>From</th><th>Message</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($feedback as $f): ?>
            <tr>
                <td>
                    <?= e($f['name'] ?: 'Anonymous') ?>
                    <?php if ($f['email']): ?><br><small class="text-gray-500"><?= e($f['email']) ?></small><?php endif; ?>
                </td>
                <td><?= nl2br(e($f['message'])) ?></td>
                <td><?= statusBadge($f['status']) ?></td>
                <td><?= e($f['created_at']) ?></td>
                <td>
                    <?php if ($f['status'] === 'new'): ?>
                        <form method="post" style="display:inline-block">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="mark_reviewed">
                            <input type="hidden" name="feedback_id" value="<?= e((string) $f['id']) ?>">
                            <button type="submit">Mark Reviewed</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline-block">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_feedback">
                        <input type="hidden" name="feedback_id" value="<?= e((string) $f['id']) ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$feedback): ?>
            <tr><td colspan="5">No feedback submitted yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
