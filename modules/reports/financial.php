<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('reports.view');
$user = currentUser();

$totalDeposits = (float) db()->query(
    "SELECT COALESCE(SUM(amount), 0) FROM savings WHERE transaction_type = 'deposit'"
)->fetchColumn();

$totalWithdrawals = (float) db()->query(
    "SELECT COALESCE(SUM(amount), 0) FROM savings WHERE transaction_type = 'withdrawal'"
)->fetchColumn();

$netSavings = $totalDeposits - $totalWithdrawals;

$totalDisbursed = (float) db()->query(
    "SELECT COALESCE(SUM(amount), 0) FROM loans WHERE status IN ('active', 'completed')"
)->fetchColumn();

$expectedInterest = (float) db()->query(
    "SELECT COALESCE(SUM(interest_amount), 0) FROM loans WHERE status IN ('active', 'completed')"
)->fetchColumn();

$totalOutstandingLoans = (float) db()->query(
    "SELECT COALESCE(SUM(total_payable), 0) - COALESCE((SELECT SUM(loan_payments.amount) FROM loan_payments
      JOIN loans l2 ON l2.id = loan_payments.loan_id WHERE l2.status = 'active'), 0)
     FROM loans WHERE status = 'active'"
)->fetchColumn();

$totalRepaymentsCollected = (float) db()->query(
    "SELECT COALESCE(SUM(amount), 0) FROM loan_payments"
)->fetchColumn();

$totalPenaltiesCollected = (float) db()->query(
    "SELECT COALESCE(SUM(penalty_amount), 0) FROM loan_payments"
)->fetchColumn();

$totalFines = (float) db()->query(
    "SELECT COALESCE(SUM(amount), 0) FROM fines WHERE status = 'paid'"
)->fetchColumn();

$totalIncome = (float) db()->query(
    "SELECT COALESCE(SUM(amount), 0) FROM income"
)->fetchColumn();

$totalExpenses = (float) db()->query(
    "SELECT COALESCE(SUM(amount), 0) FROM expenses"
)->fetchColumn();

$netClubPosition = $totalRepaymentsCollected + $totalPenaltiesCollected + $totalFines + $totalIncome - $totalExpenses;

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Financial Report</h1>
    <p class="no-print"><button onclick="window.print()">Print / Save as PDF</button></p>

    <h2>Savings</h2>
    <div class="report-summary">
        <div class="stat"><div class="label">Total Deposits</div><div class="value"><?= formatMoney($totalDeposits) ?></div></div>
        <div class="stat"><div class="label">Total Withdrawals</div><div class="value"><?= formatMoney($totalWithdrawals) ?></div></div>
        <div class="stat"><div class="label">Net Savings Held</div><div class="value"><?= formatMoney($netSavings) ?></div></div>
    </div>

    <h2>Loans</h2>
    <div class="report-summary">
        <div class="stat"><div class="label">Total Disbursed</div><div class="value"><?= formatMoney($totalDisbursed) ?></div></div>
        <div class="stat"><div class="label">Expected Interest (active + completed)</div><div class="value"><?= formatMoney($expectedInterest) ?></div></div>
        <div class="stat"><div class="label">Outstanding Balance (active loans)</div><div class="value"><?= formatMoney($totalOutstandingLoans) ?></div></div>
        <div class="stat"><div class="label">Repayments Collected</div><div class="value"><?= formatMoney($totalRepaymentsCollected) ?></div></div>
        <div class="stat"><div class="label">Penalties Collected</div><div class="value"><?= formatMoney($totalPenaltiesCollected) ?></div></div>
    </div>

    <h2>Other Club Finances</h2>
    <div class="report-summary">
        <div class="stat"><div class="label">Fines Collected</div><div class="value"><?= formatMoney($totalFines) ?></div></div>
        <div class="stat"><div class="label">Other Income</div><div class="value"><?= formatMoney($totalIncome) ?></div></div>
        <div class="stat"><div class="label">Expenses</div><div class="value"><?= formatMoney($totalExpenses) ?></div></div>
        <div class="stat"><div class="label">Net Club Cashflow</div><div class="value"><?= formatMoney($netClubPosition) ?></div></div>
    </div>
    <p><small>Net club cashflow = loan repayments + penalties + fines + other income − expenses.
    It excludes members' savings balances, which remain a liability owed back to members.</small></p>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
