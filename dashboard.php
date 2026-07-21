<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();
$user = currentUser();
$role = $user['role_name'];
$isStaff = userHasPermission($user, 'dashboard.overview');

$navItems = require __DIR__ . '/includes/nav.php';
$quickLinks = array_filter($navItems, fn($item) => $item['label'] !== 'Dashboard' && userCanSeeNavItem($user, $item));

$stats = [];

if ($isStaff) {
    $stats['Total Members'] = (int) db()->query("SELECT COUNT(*) FROM members WHERE membership_status = 'active'")->fetchColumn();
    $stats['Total Savings Held'] = formatMoney((float) db()->query(
        "SELECT COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE -amount END), 0) FROM savings"
    )->fetchColumn());
    $stats['Active Loans'] = (int) db()->query("SELECT COUNT(*) FROM loans WHERE status = 'active'")->fetchColumn();
    $stats['Pending Loan Applications'] = (int) db()->query("SELECT COUNT(*) FROM loans WHERE status = 'pending'")->fetchColumn();
    $stats['Upcoming Meetings'] = (int) db()->query("SELECT COUNT(*) FROM meetings WHERE status = 'scheduled' AND meeting_date >= NOW()")->fetchColumn();
} else {
    $stmt = db()->prepare('SELECT id FROM members WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $memberId = $stmt->fetchColumn();

    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE -amount END), 0) FROM savings WHERE member_id = ?"
    );
    $stmt->execute([$memberId]);
    $stats['My Savings Balance'] = formatMoney((float) $stmt->fetchColumn());

    $stmt = db()->prepare("SELECT COUNT(*) FROM loans WHERE member_id = ? AND status = 'active'");
    $stmt->execute([$memberId]);
    $stats['My Active Loans'] = (int) $stmt->fetchColumn();

    $stats['Upcoming Meetings'] = (int) db()->query("SELECT COUNT(*) FROM meetings WHERE status = 'scheduled' AND meeting_date >= NOW()")->fetchColumn();
}

if (userHasPermission($user, 'messages.manage')) {
    $stats['Unanswered Member Messages'] = (int) db()->query(
        "SELECT COUNT(*) FROM messages m
         WHERE m.channel = 'member_leadership' AND m.parent_id IS NULL
         AND NOT EXISTS (SELECT 1 FROM messages r WHERE r.parent_id = m.id)"
    )->fetchColumn();
} else {
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM messages m
         WHERE m.channel = 'member_leadership' AND m.parent_id IS NULL AND m.sender_id = ?
         AND NOT EXISTS (SELECT 1 FROM messages r WHERE r.parent_id = m.id)"
    );
    $stmt->execute([$user['id']]);
    $stats['My Threads Awaiting Reply'] = (int) $stmt->fetchColumn();
}

$stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$user['id']]);
$stats['New Notifications'] = (int) $stmt->fetchColumn();

require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="flex items-center gap-4">
        <?= avatarHtml($user['photo_path'], $user['full_name'], 'w-16 h-16 text-xl') ?>
        <div>
            <h1 class="mb-0">Welcome back, <?= e($user['full_name']) ?></h1>
            <p class="text-gray-500 text-sm mb-0">Role: <strong><?= e(str_replace('_', ' ', ucfirst($role))) ?></strong></p>
            <?php if (!empty($user['email'])): ?>
                <p class="text-gray-400 text-xs mb-0"><?= e($user['email']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="stat-grid">
    <?php foreach ($stats as $label => $value): ?>
        <div class="stat">
            <div class="label"><?= e($label) ?></div>
            <div class="value"><?= e((string) $value) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <h2>Quick Links</h2>
    <div class="dashboard-grid">
        <?php foreach ($quickLinks as $item): ?>
            <a class="card btn text-center" href="<?= e(APP_URL) ?>/<?= e($item['href']) ?>">
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
