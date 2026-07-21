<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$leadership = require __DIR__ . '/includes/leadership.php';
require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>About <?= e($siteName) ?></h1>
    <p><?= e($siteName) ?> is a savings and credit club based at <strong>Tumba College, Rulindo District,
    Northern Province, Rwanda</strong>. It was established to encourage financial discipline, promote
    investment, and provide affordable financial support to its members. As a newly established initiative,
    the club adopted a computerized management system from day one &mdash; before operations even began
    &mdash; so that every financial and administrative activity is handled digitally, transparently, and
    accountably from the start.</p>
</div>

<div class="card">
    <h2>&#128205; Our Location</h2>
    <div class="grid sm:grid-cols-2 gap-6 mt-3">
        <div>
            <h3 class="mb-1">Tumba College</h3>
            <p class="text-gray-600 text-sm">Tumba College of Technology, one of Rwanda's leading technical institutions,
            is located in Rulindo District, Northern Province. The college provides quality technical and vocational
            education, and <?= e($siteName) ?> operates right here on campus to serve students and staff.</p>
        </div>
        <div>
            <h3 class="mb-1">Rulindo District</h3>
            <p class="text-gray-600 text-sm">Rulindo is a district in the Northern Province of Rwanda, known for its
            agricultural activities and growing educational institutions. Our club is proud to serve the
            community here at Tumba College.</p>
        </div>
    </div>
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
    <h2>Our Mission</h2>
    <p>To promote financial literacy, savings culture, and access to affordable credit among members
    of the Tumba College community through transparent, technology-driven operations.</p>
</div>

<div class="card">
    <h2>Our Vision</h2>
    <p>To become a model savings and credit club that empowers its members financially, fosters a
    culture of discipline and mutual support, and contributes to the economic well-being of the
    Rulindo District community.</p>
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

<?php if ($leadership): ?>
<div class="card">
    <h2>Our Committee</h2>
    <p>The committee members oversee <?= e($siteName) ?>'s day-to-day operations and
    financial accountability to members.</p>
    <div class="dashboard-grid mt-3">
        <?php foreach ($leadership as $leader): ?>
            <div class="card">
                <div class="flex items-center gap-3">
                    <img src="<?= e(APP_URL) ?>/<?= e($leader['photo']) ?>" alt="<?= e($leader['name']) ?>"
                         class="w-14 h-14 rounded-full object-cover object-top border border-gray-200">
                    <div>
                        <div class="font-semibold text-sm"><?= e($leader['name']) ?></div>
                        <div class="text-gray-500 text-xs"><?= e($leader['title']) ?></div>
                        <?php if (!empty($leader['phone'])): ?>
                            <div class="text-xs text-gray-400">TEL: <?= e($leader['phone']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card text-center">
    <h2>Ready to Get Involved?</h2>
    <p>Members can log in to check their savings, apply for a loan, or review upcoming meetings.</p>
    <div class="flex flex-wrap justify-center gap-3 mt-3">
        <a class="btn" href="<?= e(APP_URL) ?>/login.php">Member / Admin Login</a>
        <a class="btn btn-ghost" href="<?= e(APP_URL) ?>/membership.php">Join Us</a>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
