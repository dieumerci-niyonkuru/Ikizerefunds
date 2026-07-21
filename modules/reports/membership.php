<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('reports.view');
$user = currentUser();

$members = db()->query(
    "SELECT members.member_number, members.join_date, members.membership_status,
            users.full_name, users.phone, users.photo_path,
            COALESCE((SELECT SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE -amount END)
                      FROM savings WHERE savings.member_id = members.id), 0) AS savings_balance,
            (SELECT COUNT(*) FROM loans WHERE loans.member_id = members.id AND loans.status = 'active') AS active_loans
     FROM members
     JOIN users ON users.id = members.user_id
     ORDER BY members.join_date"
)->fetchAll();

$totalMembers = count($members);
$activeMembers = count(array_filter($members, fn($m) => $m['membership_status'] === 'active'));
$totalSavings = array_sum(array_column($members, 'savings_balance'));

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Membership Report</h1>
    <p class="no-print"><button onclick="window.print()">Print / Save as PDF</button></p>

    <div class="report-summary">
        <div class="stat"><div class="label">Total Members</div><div class="value"><?= e((string) $totalMembers) ?></div></div>
        <div class="stat"><div class="label">Active Members</div><div class="value"><?= e((string) $activeMembers) ?></div></div>
        <div class="stat"><div class="label">Total Savings Held</div><div class="value"><?= formatMoney($totalSavings) ?></div></div>
    </div>

    <div class="table-wrap">
    <table>
        <thead>
        <tr><th>Member #</th><th>Name</th><th>Phone</th><th>Joined</th><th>Status</th><th>Savings Balance</th><th>Active Loans</th></tr>
        </thead>
        <tbody>
        <?php foreach ($members as $m): ?>
            <tr>
                <td><?= e($m['member_number']) ?></td>
                <td class="flex items-center gap-2"><?= avatarHtml($m['photo_path'] ?? null, $m['full_name']) ?> <?= e($m['full_name']) ?></td>
                <td><?= e($m['phone']) ?></td>
                <td><?= e($m['join_date']) ?></td>
                <td><?= statusBadge($m['membership_status']) ?></td>
                <td><?= formatMoney((float) $m['savings_balance']) ?></td>
                <td><?= e((string) $m['active_loans']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$members): ?>
            <tr><td colspan="7">No members registered yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
