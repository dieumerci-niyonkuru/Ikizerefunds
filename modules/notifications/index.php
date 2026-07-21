<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requireLogin();
$user = currentUser();

$stmt = db()->prepare(
    'SELECT type, channel, message, status, created_at, sent_at
     FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100'
);
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

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
    <p class="text-gray-500 text-sm">Reminders and alerts sent to you by the club.</p>
</div>

<?php if (!$notifications): ?>
    <div class="card text-center text-gray-500">No notifications yet.</div>
<?php else: ?>
    <div class="flex flex-col gap-3">
        <?php foreach ($notifications as $n): ?>
            <div class="card mb-0 flex items-start gap-3">
                <span class="w-10 h-10 shrink-0 rounded-full bg-primary-light flex items-center justify-center text-lg"><?= $notificationIcons[$n['type']] ?? '&#128276;' ?></span>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between gap-2 flex-wrap">
                        <span class="font-semibold"><?= e(str_replace('_', ' ', ucfirst($n['type']))) ?></span>
                        <?= statusBadge($n['status']) ?>
                    </div>
                    <p class="mb-1"><?= e($n['message']) ?></p>
                    <div class="text-gray-500 text-xs"><?= e(strtoupper($n['channel'])) ?> &middot; <?= e($n['created_at']) ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
