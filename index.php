<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$announcements = db()->query(
    'SELECT title, content, posted_at FROM announcements
     WHERE is_published = 1 ORDER BY posted_at DESC LIMIT 3'
)->fetchAll();
$leadership = require __DIR__ . '/includes/leadership.php';

$memberCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
require __DIR__ . '/includes/header.php';
?>
<div class="hero">
    <h1>Welcome to <?= e($siteName) ?></h1>
    <p>A savings and credit club at <strong>Tumba College, Rulindo District</strong> &mdash;
    committed to financial discipline, transparency, and supporting our members' growth.</p>
    <div class="flex flex-wrap justify-center gap-3 mt-2">
        <?php if (!isLoggedIn()): ?>
            <a class="btn" href="<?= e(APP_URL) ?>/login.php">Member Login</a>
            <a class="btn btn-ghost" href="<?= e(APP_URL) ?>/membership.php">Join Us</a>
        <?php else: ?>
            <a class="btn" href="<?= e(APP_URL) ?>/dashboard.php">Go to Dashboard</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(APP_URL) ?>/about.php">Learn More</a>
    </div>
</div>

<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
        <div class="text-2xl font-bold text-primary-dark"><?= $memberCount ?></div>
        <div class="text-xs uppercase text-gray-500 mt-1">Active Members</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
        <div class="text-2xl font-bold text-primary-dark">&#128176;</div>
        <div class="text-xs uppercase text-gray-500 mt-1">Savings & Loans</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
        <div class="text-2xl font-bold text-primary-dark">&#128197;</div>
        <div class="text-xs uppercase text-gray-500 mt-1">Regular Meetings</div>
    </div>
    <div class="bg-white border border-gray-200 rounded-xl p-4 text-center">
        <div class="text-2xl font-bold text-primary-dark">&#128274;</div>
        <div class="text-xs uppercase text-gray-500 mt-1">Secure System</div>
    </div>
</div>

<h2>Our Services</h2>
<div class="dashboard-grid mb-6">
    <div class="card">
        <div class="text-3xl mb-2">&#128176;</div>
        <h3 class="mb-1">Savings</h3>
        <p class="text-gray-500 text-sm">Track monthly contributions and balances automatically, with a full history for every member.</p>
    </div>
    <div class="card">
        <div class="text-3xl mb-2">&#127974;</div>
        <h3 class="mb-1">Loans</h3>
        <p class="text-gray-500 text-sm">Apply online, get reviewed by club leadership, and follow a transparent repayment schedule.</p>
    </div>
    <div class="card">
        <div class="text-3xl mb-2">&#128197;</div>
        <h3 class="mb-1">Meetings</h3>
        <p class="text-gray-500 text-sm">Schedules, attendance, and minutes are recorded and available to every member.</p>
    </div>
    <div class="card">
        <div class="text-3xl mb-2">&#128202;</div>
        <h3 class="mb-1">Reports</h3>
        <p class="text-gray-500 text-sm">Financial and membership reports generated from real transaction records.</p>
    </div>
</div>

<?php if ($leadership): ?>
<h2>Our Leadership</h2>
<div class="card mb-6">
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-6 text-center">
        <?php foreach (array_slice($leadership, 0, 4) as $leader): ?>
            <div>
                <img src="<?= e(APP_URL) ?>/<?= e($leader['photo']) ?>" alt="<?= e($leader['name']) ?>"
                     class="w-24 h-24 sm:w-28 sm:h-28 rounded-full object-cover object-top mx-auto mb-2 border border-gray-200 shadow-sm">
                <div class="font-semibold text-sm"><?= e($leader['name']) ?></div>
                <div class="text-gray-500 text-xs"><?= e($leader['title']) ?></div>
                <?php if (!empty($leader['phone'])): ?>
                    <div class="text-xs text-gray-400"><?= e($leader['phone']) ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center mt-4">
        <a href="<?= e(APP_URL) ?>/leadership.php" class="text-sm font-semibold text-primary hover:underline">Meet the full team &rarr;</a>
    </div>
</div>
<?php endif; ?>

<?php if ($announcements): ?>
<h2>Latest Announcements</h2>
<div class="mb-6">
    <?php foreach ($announcements as $a): ?>
        <div class="card">
            <h3 class="mb-1"><?= e($a['title']) ?></h3>
            <small class="text-gray-400"><?= e($a['posted_at']) ?></small>
            <p class="mt-2 text-gray-600 text-sm"><?= nl2br(e($a['content'])) ?></p>
        </div>
    <?php endforeach; ?>
    <div class="text-center">
        <a href="<?= e(APP_URL) ?>/announcements.php" class="text-sm font-semibold text-primary hover:underline">View all announcements &rarr;</a>
    </div>
</div>
<?php endif; ?>

<div class="card text-center bg-gradient-to-br from-primary to-primary-dark text-white">
    <h2 class="text-white">Ready to Get Involved?</h2>
    <p class="text-white/80">Join <?= e($siteName) ?> at Tumba College, Rulindo and start your journey towards financial discipline.</p>
    <div class="flex flex-wrap justify-center gap-3 mt-3">
        <a class="btn bg-white text-primary hover:bg-gray-100" href="<?= e(APP_URL) ?>/membership.php">Join Now</a>
        <a class="btn btn-ghost border-white text-white hover:bg-white/15" href="<?= e(APP_URL) ?>/contact.php">Contact Us</a>
    </div>
</div>

<div class="card text-center">
    <h2>&#128205; Visit Us</h2>
    <p class="text-gray-600">Tumba College, Rulindo District, Northern Province, Rwanda</p>
    <a href="<?= e(APP_URL) ?>/contact.php" class="text-sm font-semibold text-primary hover:underline">Get directions &rarr;</a>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
