<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$leadership = require __DIR__ . '/includes/leadership.php';

$pageTitle = 'Leadership';
$pageDescription = 'Meet the elected committee of IKIZERE FUNDS Club — president, vice president, '
    . 'secretary and accountant — and how to reach each of them directly.';

require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <span class="section-eyebrow"><?= icon('leadership', 'w-3.5 h-3.5') ?> The committee</span>
    <h1 class="section-title text-2xl sm:text-3xl">Leadership</h1>
    <p class="section-sub">The people below oversee <?= e($siteName) ?>'s day-to-day operations and
    are accountable to the membership for every franc that moves through the club.</p>
</section>

<?php if ($leadership): ?>
<section class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4 mb-8">
    <?php foreach ($leadership as $leader): ?>
        <article class="leader-panel">
            <div class="leader-panel-top">
                <img src="<?= e(APP_URL) ?>/<?= e($leader['photo']) ?>"
                     alt="Portrait of <?= e($leader['name']) ?>, <?= e($leader['title']) ?>"
                     class="leader-panel-photo" loading="lazy" width="128" height="128">
            </div>

            <div class="p-5 text-center flex-1 flex flex-col">
                <h2 class="font-bold text-primary-dark leading-tight"><?= e($leader['name']) ?></h2>
                <div class="text-xs font-bold text-gold-deep uppercase tracking-wider mt-1.5"><?= e($leader['title']) ?></div>

                <div class="mt-4 pt-4 border-t border-gray-100 space-y-2.5 text-sm text-left mt-auto">
                    <?php if (!empty($leader['phone'])): ?>
                        <a class="flex items-center gap-2.5 no-underline text-gray-600" href="tel:<?= e($leader['phone']) ?>">
                            <?= icon('phone', 'w-4 h-4 text-gold-deep shrink-0') ?><?= e($leader['phone']) ?>
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($leader['email'])): ?>
                        <a class="flex items-start gap-2.5 no-underline text-gray-600 min-w-0" href="mailto:<?= e($leader['email']) ?>">
                            <?= icon('mail', 'w-4 h-4 text-gold-deep shrink-0 mt-0.5') ?><span class="break-all text-xs"><?= e($leader['email']) ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="mb-8">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('scale', 'w-3.5 h-3.5') ?> How we govern</span>
        <h2 class="section-title">What each role is responsible for</h2>
        <p class="section-sub">Duties are separated on purpose &mdash; the person who records money
        is not the person who approves it.</p>
    </div>

    <div class="card">
        <div class="grid gap-6 sm:gap-7 sm:grid-cols-2">
            <div class="why-item">
                <span class="why-item-icon"><?= icon('leadership', 'w-5 h-5') ?></span>
                <div>
                    <h3>President</h3>
                    <p>Chairs meetings, represents the club, and signs off decisions taken by the
                    membership.</p>
                </div>
            </div>
            <div class="why-item">
                <span class="why-item-icon"><?= icon('users', 'w-5 h-5') ?></span>
                <div>
                    <h3>Vice President</h3>
                    <p>Deputises for the president and supports member relations and discipline.</p>
                </div>
            </div>
            <div class="why-item">
                <span class="why-item-icon"><?= icon('document', 'w-5 h-5') ?></span>
                <div>
                    <h3>Secretary</h3>
                    <p>Keeps the register, records minutes and attendance, and handles club
                    correspondence.</p>
                </div>
            </div>
            <div class="why-item">
                <span class="why-item-icon"><?= icon('savings', 'w-5 h-5') ?></span>
                <div>
                    <h3>Accountant</h3>
                    <p>Records deposits, withdrawals, loan payments and expenses, and prepares the
                    financial reports.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="cta-band">
    <span class="hero-eyebrow"><?= icon('chat', 'w-3.5 h-3.5') ?> Talk to us</span>
    <h2 class="text-2xl sm:text-3xl font-bold text-white mb-3 tracking-tight">Need to reach the committee?</h2>
    <p class="text-white/75 max-w-xl mx-auto mb-7 text-sm sm:text-base leading-relaxed">
        Send a message and it lands in the committee's inbox &mdash; or call any member above directly.
    </p>
    <div class="hero-actions">
        <a class="nav-cta-solid justify-center" href="<?= e(APP_URL) ?>/contact.php">
            <?= icon('mail', 'w-4 h-4') ?> Contact Us
        </a>
        <a class="btn btn-ghost justify-center inline-flex items-center gap-2" href="<?= e(APP_URL) ?>/about.php">
            <?= icon('info', 'w-4 h-4') ?> About the Club
        </a>
    </div>
</section>

<?php else: ?>
<div class="card text-center py-12">
    <span class="mx-auto grid place-items-center w-14 h-14 rounded-xl bg-primary-light text-primary mb-4">
        <?= icon('leadership', 'w-7 h-7') ?>
    </span>
    <h2 class="text-lg font-bold text-primary-dark mb-1">Leadership details coming soon</h2>
    <p class="text-sm text-gray-500">The committee list will be published here once elections are confirmed.</p>
</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
