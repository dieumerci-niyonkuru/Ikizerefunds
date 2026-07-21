<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$announcements = db()->query(
    'SELECT title, content, posted_at FROM announcements
     WHERE is_published = 1 ORDER BY posted_at DESC LIMIT 3'
)->fetchAll();
$leadership = require __DIR__ . '/includes/leadership.php';

require __DIR__ . '/includes/header.php';
?>
<div class="hero">
    <h1>Welcome to <?= e($siteName) ?></h1>
    <p>A savings and credit club committed to financial discipline, transparency, and supporting our members' growth.</p>
    <?php if (!isLoggedIn()): ?>
        <a class="btn" href="<?= e(APP_URL) ?>/login.php">Member / Admin Login</a>
        <a class="btn btn-ghost" href="<?= e(APP_URL) ?>/about.php">Learn More</a>
    <?php else: ?>
        <a class="btn" href="<?= e(APP_URL) ?>/dashboard.php">Go to Dashboard</a>
    <?php endif; ?>
</div>

<div class="dashboard-grid">
    <div class="card">
        <h3 class="mb-1">Savings</h3>
        <p class="text-gray-500 text-sm">Track monthly contributions and balances automatically, with a full history for every member.</p>
    </div>
    <div class="card">
        <h3 class="mb-1">Loans</h3>
        <p class="text-gray-500 text-sm">Apply online, get reviewed by club leadership, and follow a transparent repayment schedule.</p>
    </div>
    <div class="card">
        <h3 class="mb-1">Meetings</h3>
        <p class="text-gray-500 text-sm">Schedules, attendance, and minutes are recorded and available to every member.</p>
    </div>
</div>

<?php if ($leadership): ?>
<div class="card">
    <div class="flex items-center justify-between mb-3">
        <h2 class="mb-0">Leadership</h2>
        <a href="<?= e(APP_URL) ?>/leadership.php" class="text-sm">Meet the team &rarr;</a>
    </div>
    <div class="dashboard-grid">
        <?php foreach (array_slice($leadership, 0, 3) as $leader): ?>
            <div class="text-center">
                <img src="<?= e(APP_URL) ?>/<?= e($leader['photo']) ?>" alt="<?= e($leader['name']) ?>"
                     class="w-32 h-32 rounded-full object-cover object-top mx-auto mb-3 border border-gray-200">
                <div class="font-semibold"><?= e($leader['name']) ?></div>
                <div class="text-gray-500 text-sm"><?= e($leader['title']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="flex items-center justify-between mb-3">
        <h2 class="mb-0">Latest Announcements</h2>
        <a href="<?= e(APP_URL) ?>/announcements.php" class="text-sm">View all &rarr;</a>
    </div>
    <?php if (!$announcements): ?>
        <p>No announcements yet.</p>
    <?php else: ?>
        <?php foreach ($announcements as $a): ?>
            <div class="pb-4 mb-4 border-b border-gray-200 last:border-0 last:pb-0 last:mb-0">
                <h3 class="mb-1"><?= e($a['title']) ?></h3>
                <small class="text-gray-500"><?= e($a['posted_at']) ?></small>
                <p><?= nl2br(e($a['content'])) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
