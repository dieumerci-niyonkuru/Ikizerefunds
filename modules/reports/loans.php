<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('reports.view');
$user = currentUser();

$loans = db()->query(
    "SELECT loans.*, users.full_name, members.member_number, loan_types.name AS loan_type_name,
            COALESCE((SELECT SUM(amount) FROM loan_payments WHERE loan_payments.loan_id = loans.id), 0) AS paid_so_far
     FROM loans
     JOIN members ON members.id = loans.member_id
     JOIN users ON users.id = members.user_id
     JOIN loan_types ON loan_types.id = loans.loan_type_id
     ORDER BY loans.applied_date DESC"
)->fetchAll();

$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'active' => 0, 'completed' => 0, 'defaulted' => 0];
$totalDisbursed = 0;
$totalOutstanding = 0;
$overdue = [];
$today = date('Y-m-d');

foreach ($loans as $l) {
    $counts[$l['status']] = ($counts[$l['status']] ?? 0) + 1;
    if (in_array($l['status'], ['active', 'completed'], true)) {
        $totalDisbursed += (float) $l['amount'];
    }
    if ($l['status'] === 'active') {
        $balance = (float) $l['total_payable'] - (float) $l['paid_so_far'];
        $totalOutstanding += $balance;
        if ($l['due_date'] && $l['due_date'] < $today) {
            $overdue[] = $l;
        }
    }
}

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Loan Report</h1>
    <p class="no-print"><button onclick="window.print()">Print / Save as PDF</button></p>

    <div class="report-summary">
        <div class="stat"><div class="label">Pending</div><div class="value"><?= e((string) $counts['pending']) ?></div></div>
        <div class="stat"><div class="label">Active</div><div class="value"><?= e((string) $counts['active']) ?></div></div>
        <div class="stat"><div class="label">Completed</div><div class="value"><?= e((string) $counts['completed']) ?></div></div>
        <div class="stat"><div class="label">Rejected</div><div class="value"><?= e((string) $counts['rejected']) ?></div></div>
        <div class="stat"><div class="label">Total Disbursed</div><div class="value"><?= formatMoney($totalDisbursed) ?></div></div>
        <div class="stat"><div class="label">Outstanding Balance</div><div class="value"><?= formatMoney($totalOutstanding) ?></div></div>
    </div>

    <h2>Overdue Active Loans</h2>
    <table>
        <thead><tr><th>Member</th><th>Type</th><th>Balance</th><th>Due Date</th></tr></thead>
        <tbody>
        <?php foreach ($overdue as $l): ?>
            <tr>
                <td><?= e($l['member_number'] . ' - ' . $l['full_name']) ?></td>
                <td><?= e($l['loan_type_name']) ?></td>
                <td><?= formatMoney((float) $l['total_payable'] - (float) $l['paid_so_far']) ?></td>
                <td><?= e($l['due_date']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$overdue): ?>
            <tr><td colspan="4">No overdue loans.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <h2>All Loans</h2>
    <table>
        <thead>
        <tr><th>Member</th><th>Type</th><th>Amount</th><th>Total Payable</th><th>Paid</th><th>Status</th><th>Applied</th><th>Due</th></tr>
        </thead>
        <tbody>
        <?php foreach ($loans as $l): ?>
            <tr>
                <td><?= e($l['member_number'] . ' - ' . $l['full_name']) ?></td>
                <td><?= e($l['loan_type_name']) ?></td>
                <td><?= formatMoney((float) $l['amount']) ?></td>
                <td><?= formatMoney((float) $l['total_payable']) ?></td>
                <td><?= formatMoney((float) $l['paid_so_far']) ?></td>
                <td><?= statusBadge($l['status']) ?></td>
                <td><?= e($l['applied_date']) ?></td>
                <td><?= e($l['due_date']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$loans): ?>
            <tr><td colspan="8">No loans recorded yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
