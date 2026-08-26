<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
$user = currentUser();

$stmt = db()->prepare(
    'SELECT id, type, channel, message, status, error, created_at, sent_at, read_at
     FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100'
);
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

// Mark everything on this page as read. This is tracked with read_at rather
// than the status column: status records whether DELIVERY succeeded, and
// overwriting it here used to stop pending notifications from ever being
// emailed.
db()->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL')
    ->execute([$user['id']]);

$unreadCount = 0;
foreach ($notifications as $n) {
    if ($n['read_at'] === null) {
        $unreadCount++;
    }
}

$notificationIcons = [
    'saving_reminder' => '&#128176;',
    'loan_approval' => '&#127974;',
    'payment_due' => '&#128179;',
    'meeting_reminder' => '&#128197;',
    'late_payment' => '&#9888;',
];
require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>My Notifications</h1>
    <p class="text-gray-500 text-sm">
        Reminders and alerts sent to you by the club.
        <?php if ($unreadCount): ?>
            <strong class="text-primary-dark"><?= $unreadCount ?> new since you last looked.</strong>
        <?php endif; ?>
    </p>
</div>

<?php if (!$notifications): ?>
    <div class="card text-center py-10">
        <span class="mx-auto grid place-items-center w-14 h-14 rounded-xl bg-primary-light text-primary mb-3 text-2xl">&#128276;</span>
        <h2 class="font-bold text-primary-dark mb-1">No notifications yet</h2>
        <p class="text-sm text-gray-500">Savings reminders, loan updates and meeting notices will appear here.</p>
    </div>
<?php else: ?>
    <div class="flex flex-col gap-3">
        <?php foreach ($notifications as $n): ?>
            <?php $isUnread = $n['read_at'] === null; ?>
            <div class="card mb-0 flex items-start gap-3<?= $isUnread ? ' border-l-4 border-l-gold' : '' ?>">
                <span class="w-10 h-10 shrink-0 rounded-full bg-primary-light flex items-center justify-center text-lg"><?= $notificationIcons[$n['type']] ?? '&#128276;' ?></span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="font-semibold">
                            <?= e(str_replace('_', ' ', ucfirst($n['type']))) ?>
                            <?php if ($isUnread): ?>
                                <span class="badge bg-gold-light text-gold-deep ml-1">New</span>
                            <?php endif; ?>
                        </span>
                        <?php if ($n['status'] === 'failed'): ?>
                            <span class="badge badge-danger" title="<?= e((string) $n['error']) ?>">Email not delivered</span>
                        <?php endif; ?>
                    </div>
                    <p class="mb-1"><?= e($n['message']) ?></p>
                    <div class="text-gray-500 text-xs"><?= e($n['created_at']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
