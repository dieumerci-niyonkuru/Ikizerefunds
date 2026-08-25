<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$leadership = require __DIR__ . '/includes/leadership.php';

$pageTitle = 'About Us';
$pageDescription = 'The story, mission and governance of IKIZERE FUNDS Club — a member-owned '
    . 'savings and credit club at Tumba College, Rulindo District, Rwanda.';

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <span class="hero-eyebrow"><?= icon('info', 'w-3.5 h-3.5') ?> About the club</span>
    <h1>Built digital<br><span class="hero-highlight">from day one.</span></h1>
    <p>Most savings groups start with a paper ledger and hit trouble as they grow.
    <?= e($siteName) ?> started the other way round &mdash; with a system that records every
    contribution, loan and decision from the very first franc.</p>
</section>

<section class="grid gap-6 lg:grid-cols-3 mb-10">
    <div class="card mb-0 lg:col-span-2">
        <span class="section-eyebrow"><?= icon('sparkles', 'w-3.5 h-3.5') ?> Our story</span>
        <h2 class="text-lg sm:text-xl font-bold text-primary-dark mb-3">Who we are</h2>
        <p class="text-sm text-gray-600 leading-relaxed mb-3">
            <?= e($siteName) ?> is a savings and credit club based at <strong>Tumba College,
            Rulindo District, Northern Province, Rwanda</strong>. It was established to encourage
            financial discipline, promote investment, and provide affordable financial support to
            its members.
        </p>
        <p class="text-sm text-gray-600 leading-relaxed">
            As a newly established initiative, the club adopted a computerised management system
            before operations even began &mdash; so that every financial and administrative
            activity is handled digitally, transparently and accountably from the start.
        </p>
    </div>

    <div class="card mb-0 bg-primary text-white border-0">
        <span class="inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] text-gold mb-3">
            <?= icon('scale', 'w-3.5 h-3.5') ?> Why we exist
        </span>
        <p class="text-sm text-white/80 leading-relaxed mb-4">
            Manual record keeping leads to poor bookkeeping, inaccurate calculations and delayed
            reporting as a group grows. Rather than wait for those problems, we started on a
            secure, web-based platform.
        </p>
        <p class="text-sm text-white/80 leading-relaxed">
            Members can trust that savings and loans are accurately tracked, and leaders can focus
            on serving members instead of paperwork.
        </p>
    </div>
</section>

<section class="grid gap-4 sm:grid-cols-2 mb-10">
    <article class="svc-card">
        <div class="svc-icon"><?= icon('sparkles', 'w-6 h-6') ?></div>
        <h3>Our mission</h3>
        <p>To promote financial literacy, a savings culture, and access to affordable credit among
        members of the Tumba College community through transparent, technology-driven operations.</p>
    </article>
    <article class="svc-card">
        <div class="svc-icon"><?= icon('leadership', 'w-6 h-6') ?></div>
        <h3>Our vision</h3>
        <p>To become a model savings and credit club that empowers its members financially, fosters
        discipline and mutual support, and contributes to the economic well-being of Rulindo District.</p>
    </article>
</section>

<section class="mb-10">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('map-pin', 'w-3.5 h-3.5') ?> Where we are</span>
        <h2 class="section-title">Our location</h2>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="card mb-0 lg:col-span-3">
            <div class="grid gap-6 sm:grid-cols-2">
                <div class="why-item">
                    <span class="why-item-icon"><?= icon('leadership', 'w-5 h-5') ?></span>
                    <div>
                        <h3>Tumba College</h3>
                        <p>Tumba College of Technology, one of Rwanda's leading technical
                        institutions, sits in Rulindo District. The club operates right here on
                        campus, serving students and staff.</p>
                    </div>
                </div>
                <div class="why-item">
                    <span class="why-item-icon"><?= icon('map-pin', 'w-5 h-5') ?></span>
                    <div>
                        <h3>Rulindo District</h3>
                        <p>Rulindo is a district in the Northern Province, known for its
                        agricultural activity and growing educational institutions. We are proud to
                        serve this community.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-0 lg:col-span-2 flex flex-col">
            <div class="map-frame flex-1">
                <iframe
                    src="https://maps.google.com/maps?q=<?= rawurlencode($club['mapQuery']) ?>&amp;output=embed"
                    title="Map showing Tumba College, Rulindo District, Rwanda"
                    loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
            </div>
            <a class="link-more mt-4" target="_blank" rel="noopener noreferrer"
               href="https://www.google.com/maps/search/?api=1&amp;query=<?= rawurlencode($club['mapQuery']) ?>">
                Open in Google Maps <?= icon('arrow-right', 'w-4 h-4') ?>
            </a>
        </div>
    </div>
</section>

<section class="mb-10">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('savings', 'w-3.5 h-3.5') ?> Member benefits</span>
        <h2 class="section-title">What we offer members</h2>
        <p class="section-sub">Four things every member gets from the day their application is approved.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article class="svc-card">
            <div class="svc-icon"><?= icon('savings', 'w-6 h-6') ?></div>
            <h3>Savings</h3>
            <p>Monthly contributions tracked automatically, with a running balance and full history
            available any time.</p>
        </article>
        <article class="svc-card">
            <div class="svc-icon"><?= icon('loans', 'w-6 h-6') ?></div>
            <h3>Loans</h3>
            <p>Apply online, get reviewed by leadership, and track your repayment schedule from your
            own account.</p>
        </article>
        <article class="svc-card">
            <div class="svc-icon"><?= icon('calendar', 'w-6 h-6') ?></div>
            <h3>Meetings</h3>
            <p>Schedules, attendance and minutes are all recorded and available to every member.</p>
        </article>
        <article class="svc-card">
            <div class="svc-icon"><?= icon('scale', 'w-6 h-6') ?></div>
            <h3>Transparency</h3>
            <p>Reports are generated directly from real transaction records &mdash; not from
            spreadsheets kept on someone's laptop.</p>
        </article>
    </div>
</section>

<?php if ($leadership): ?>
<section class="mb-10">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('leadership', 'w-3.5 h-3.5') ?> Governance</span>
        <h2 class="section-title">Our committee</h2>
        <p class="section-sub">Elected by the membership to run day-to-day operations and account
        for every franc.</p>
    </div>

    <div class="card">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
            <?php foreach ($leadership as $leader): ?>
                <a class="leader-card" href="<?= e(APP_URL) ?>/leadership.php">
                    <img src="<?= e(APP_URL) ?>/<?= e($leader['photo']) ?>"
                         alt="Portrait of <?= e($leader['name']) ?>, <?= e($leader['title']) ?>"
                         class="leader-photo" loading="lazy">
                    <div class="leader-name"><?= e($leader['name']) ?></div>
                    <div class="leader-title"><?= e($leader['title']) ?></div>
                    <?php if (!empty($leader['phone'])): ?>
                        <div class="leader-phone"><?= e($leader['phone']) ?></div>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-6 pt-5 border-t border-gray-100">
            <a href="<?= e(APP_URL) ?>/leadership.php" class="link-more">
                See roles and responsibilities <?= icon('arrow-right', 'w-4 h-4') ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="cta-band">
    <span class="hero-eyebrow"><?= icon('user-plus', 'w-3.5 h-3.5') ?> Membership is open</span>
    <h2 class="text-2xl sm:text-3xl font-bold text-white mb-3 tracking-tight">Ready to get involved?</h2>
    <p class="text-white/75 max-w-xl mx-auto mb-7 text-sm sm:text-base leading-relaxed">
        Members log in to check savings, apply for a loan, or review upcoming meetings.
    </p>
    <div class="hero-actions">
        <a class="nav-cta-solid justify-center" href="<?= e(APP_URL) ?>/membership.php">
            <?= icon('user-plus', 'w-4 h-4') ?> Join the Club
        </a>
        <a class="btn btn-ghost justify-center inline-flex items-center gap-2" href="<?= e(APP_URL) ?>/login.php">
            <?= icon('lock', 'w-4 h-4') ?> Member Login
        </a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
