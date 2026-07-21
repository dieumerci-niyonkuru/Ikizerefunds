<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>About <?= e($siteName) ?></h1>
    <p><?= e($siteName) ?> is a savings and credit club built to encourage financial
    discipline, promote investment, and provide affordable financial support to its
    members. As a newly established initiative, the club adopted a computerized
    management system from day one &mdash; before operations even began &mdash; so that
    every financial and administrative activity is handled digitally, transparently,
    and accountably from the start.</p>
</div>

<div class="card">
    <h2>Why We Exist</h2>
    <p>Many savings groups begin with manual record keeping and later run into poor
    bookkeeping, inaccurate calculations, and delayed reporting as they grow. Rather
    than wait for those problems to appear, <?= e($siteName) ?> chose to start with a
    secure, reliable, web-based platform &mdash; so members can trust that their
    savings and loans are always accurately tracked, and leaders can focus on serving
    members instead of paperwork.</p>
</div>

<div class="card">
    <h2>What We Offer Members</h2>
    <div class="dashboard-grid">
        <div class="card">
            <h3 class="mb-1">Savings</h3>
            <p class="text-gray-500 text-sm">Monthly contributions tracked automatically, with a running balance and full history available any time.</p>
        </div>
        <div class="card">
            <h3 class="mb-1">Loans</h3>
            <p class="text-gray-500 text-sm">Apply online, get reviewed by club leadership, and track your repayment schedule from your own account.</p>
        </div>
        <div class="card">
            <h3 class="mb-1">Meetings</h3>
            <p class="text-gray-500 text-sm">Schedules, attendance, and minutes are all recorded and available to members.</p>
        </div>
        <div class="card">
            <h3 class="mb-1">Transparency</h3>
            <p class="text-gray-500 text-sm">Financial and membership reports are generated directly from real transaction records &mdash; not spreadsheets.</p>
        </div>
    </div>
</div>

<div class="card text-center">
    <h2>Ready to Get Involved?</h2>
    <p>Members can log in to check their savings, apply for a loan, or review upcoming meetings.</p>
    <a class="btn" href="<?= e(APP_URL) ?>/login.php">Member / Admin Login</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
