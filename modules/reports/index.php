<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('reports.view');
$user = currentUser();

require __DIR__ . '/../../includes/header.php';
?>
<div class="card">
    <h1>Reports</h1>
    <p>Each report can be printed or saved as a PDF using your browser's print dialog.</p>
    <div class="dashboard-grid">
        <a class="card btn text-center" href="<?= e(APP_URL) ?>/modules/reports/membership.php">Membership Report</a>
        <a class="card btn text-center" href="<?= e(APP_URL) ?>/modules/reports/loans.php">Loan Report</a>
        <a class="card btn text-center" href="<?= e(APP_URL) ?>/modules/reports/financial.php">Financial Report</a>
        <a class="card btn text-center" href="<?= e(APP_URL) ?>/modules/reports/monthly.php">Monthly Report</a>
    </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
