<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('reports.view');
$user = currentUser();

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}
$monthStart = $month . '-01';
$monthEnd = date('Y-m-d', strtotime($monthStart . ' +1 month'));

$stmt = db()->prepare('SELECT COUNT(*) FROM members WHERE join_date >= ? AND join_date < ?');
$stmt->execute([$monthStart, $monthEnd]);
$newMembers = (int) $stmt->fetchColumn();

$stmt = db()->prepare(
    "SELECT COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END), 0) AS deposits,
            COALESCE(SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END), 0) AS withdrawals
     FROM savings WHERE saving_date >= ? AND saving_date < ?"
);
$stmt->execute([$monthStart, $monthEnd]);
$savingsRow = $stmt->fetch();

$stmt = db()->prepare('SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM loans WHERE applied_date >= ? AND applied_date < ?');
$stmt->execute([$monthStart, $monthEnd]);
[$loansApplied, $loansAppliedAmount] = $stmt->fetch(PDO::FETCH_NUM);

$stmt = db()->prepare("SELECT COUNT(*), COALESCE(SUM(amount), 0) FROM loans WHERE approved_date >= ? AND approved_date < ? AND status IN ('active','completed')");
$stmt->execute([$monthStart, $monthEnd]);
[$loansApproved, $loansApprovedAmount] = $stmt->fetch(PDO::FETCH_NUM);

$stmt = db()->prepare('SELECT COUNT(*), COALESCE(SUM(amount), 0), COALESCE(SUM(penalty_amount), 0) FROM loan_payments WHERE payment_date >= ? AND payment_date < ?');
$stmt->execute([$monthStart, $monthEnd]);
[$paymentsCount, $paymentsAmount, $penaltiesAmount] = $stmt->fetch(PDO::FETCH_NUM);

$stmt = db()->prepare("SELECT COUNT(*) FROM meetings WHERE meeting_date >= ? AND meeting_date < ?");
$stmt->execute([$monthStart, $monthEnd]);
$meetingsHeld = (int) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date >= ? AND expense_date < ?');
$stmt->execute([$monthStart, $monthEnd]);
$expensesTotal = (float) $stmt->fetchColumn();

$stmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM income WHERE income_date >= ? AND income_date < ?');
$stmt->execute([$monthStart, $monthEnd]);
$incomeTotal = (float) $stmt->fetchColumn();

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Monthly Report</h1>
    <form method="get" class="no-print flex gap-4 items-end max-w-xs">
        <div class="flex-1">
            <label for="month">Month</label>
            <input type="month" id="month" name="month" value="<?= e($month) ?>">
        </div>
        <button type="submit">View</button>
    </form>
    <p class="no-print"><button onclick="window.print()">Print / Save as PDF</button></p>

    <h2>Summary for <?= e(date('F Y', strtotime($monthStart))) ?></h2>
    <div class="report-summary">
        <div class="stat"><div class="label">New Members</div><div class="value"><?= e((string) $newMembers) ?></div></div>
        <div class="stat"><div class="label">Savings Deposited</div><div class="value"><?= formatMoney((float) $savingsRow['deposits']) ?></div></div>
        <div class="stat"><div class="label">Savings Withdrawn</div><div class="value"><?= formatMoney((float) $savingsRow['withdrawals']) ?></div></div>
        <div class="stat"><div class="label">Loans Applied</div><div class="value"><?= e((string) $loansApplied) ?> (<?= formatMoney((float) $loansAppliedAmount) ?>)</div></div>
        <div class="stat"><div class="label">Loans Approved</div><div class="value"><?= e((string) $loansApproved) ?> (<?= formatMoney((float) $loansApprovedAmount) ?>)</div></div>
        <div class="stat"><div class="label">Loan Payments Collected</div><div class="value"><?= formatMoney((float) $paymentsAmount) ?> (<?= e((string) $paymentsCount) ?> payments)</div></div>
        <div class="stat"><div class="label">Penalties Collected</div><div class="value"><?= formatMoney((float) $penaltiesAmount) ?></div></div>
        <div class="stat"><div class="label">Meetings Held</div><div class="value"><?= e((string) $meetingsHeld) ?></div></div>
        <div class="stat"><div class="label">Other Income</div><div class="value"><?= formatMoney($incomeTotal) ?></div></div>
        <div class="stat"><div class="label">Expenses</div><div class="value"><?= formatMoney($expensesTotal) ?></div></div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
