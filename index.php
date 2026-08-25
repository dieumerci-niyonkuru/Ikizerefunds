<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$announcements = db()->query(
    'SELECT title, content, posted_at FROM announcements
     WHERE is_published = 1 ORDER BY posted_at DESC LIMIT 3'
)->fetchAll();
$leadership = require __DIR__ . '/includes/leadership.php';

// Headline figures. Deliberately non-financial — a brand-new club would
// otherwise advertise "RWF 0 saved", which reads worse than saying nothing.
$memberCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$meetingsHeld = (int) db()->query("SELECT COUNT(*) FROM meetings WHERE status = 'completed'")->fetchColumn();

$faqs = [
    ['Who can join the club?',
     'Membership is open to eligible applicants at Tumba College and the surrounding Rulindo '
     . 'community. Send a request through the membership form and the committee reviews it at '
     . 'its next sitting. You are notified either way.'],
    ['How much do I need to contribute?',
     'Contributions follow the schedule agreed by the membership. The amount and the due date are '
     . 'confirmed to you when your application is approved, and every deposit you make is recorded '
     . 'against your member number the same day.'],
    ['When can I borrow?',
     'Once you are an active member in good standing you can apply for a loan against your '
     . 'savings position. The committee reviews each application, and an approved loan comes with '
     . 'a written repayment schedule showing every instalment.'],
    ['Can I see my own records?',
     'Yes. Every member gets a login to a personal dashboard showing savings history, loan status, '
     . 'repayment schedule, meeting attendance and notifications. You see your own records; '
     . 'leadership sees only what their role requires.'],
    ['What happens if I miss a repayment?',
     'Talk to the committee early. Late or missed repayments may attract a fine under the club '
     . 'rules and can affect future applications, but the committee would far rather agree a plan '
     . 'with you than apply penalties.'],
    ['Can I leave and take my savings?',
     'Yes. Withdrawal follows the notice period and conditions in the club rules, and any '
     . 'outstanding loan balance is settled first. Your record stays in the books for the club\'s '
     . 'own audit history.'],
];

$pageDescription = 'IKIZERE FUNDS Club is a member-owned savings and credit club at Tumba College, '
    . 'Rulindo District, Rwanda. Save monthly, borrow transparently, and track every franc from '
    . 'your own member dashboard.';

require __DIR__ . '/includes/header.php';

$img = siteImages();
?>
<section class="hero hero-split">
    <div class="hero-copy">
        <span class="hero-eyebrow"><?= icon('sparkles', 'w-3.5 h-3.5') ?> <?= e($siteName) ?></span>

        <h1>Save together,<br><span class="hero-highlight">grow together.</span></h1>

        <p>A member-owned savings and credit club at Tumba College, Rulindo &mdash; built on
        financial discipline, open books, and the steady growth of every member who joins us.</p>

        <div class="hero-actions">
            <?php if (!isLoggedIn()): ?>
                <a class="btn-gold" href="<?= e(APP_URL) ?>/membership.php">
                    <?= icon('user-plus', 'w-4 h-4') ?> Join the Club
                </a>
                <a class="btn btn-ghost inline-flex items-center justify-center gap-2 min-h-[44px] rounded-full px-5" href="<?= e(APP_URL) ?>/login.php">
                    <?= icon('lock', 'w-4 h-4') ?> Member Login
                </a>
            <?php else: ?>
                <a class="btn-gold" href="<?= e(APP_URL) ?>/dashboard.php">
                    <?= icon('reports', 'w-4 h-4') ?> Go to Dashboard
                </a>
            <?php endif; ?>
        </div>

        <div class="hero-trust">
            <div class="hero-trust-item"><?= icon('scale', 'w-5 h-5') ?> Transparent records</div>
            <div class="hero-trust-item"><?= icon('users', 'w-5 h-5') ?> Member-owned</div>
            <div class="hero-trust-item"><?= icon('shield', 'w-5 h-5') ?> Secure &amp; private</div>
        </div>
    </div>

    <div class="hero-figure">
        <img src="<?= e(siteImage($img['hero']['id'], 1024)) ?>"
             srcset="<?= e(siteImageSrcset($img['hero']['id'], [640, 1024])) ?>"
             sizes="(min-width: 1024px) 45vw, 100vw"
             alt="<?= e($img['hero']['alt']) ?>"
             fetchpriority="high" decoding="async" width="1024" height="1024">
    </div>
</section>

<section class="grid grid-cols-3 gap-3 sm:gap-4 mb-10 reveal">
    <div class="stat-card">
        <div class="stat-card-icon"><?= icon('users', 'w-5 h-5') ?></div>
        <div class="stat-card-value"><?= e(number_format($memberCount)) ?></div>
        <div class="stat-card-label">Active Members</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><?= icon('leadership', 'w-5 h-5') ?></div>
        <div class="stat-card-value"><?= e(number_format(count($leadership))) ?></div>
        <div class="stat-card-label">Elected Leaders</div>
    </div>
    <div class="stat-card">
        <div class="stat-card-icon"><?= icon('calendar', 'w-5 h-5') ?></div>
        <div class="stat-card-value"><?= e(number_format($meetingsHeld)) ?></div>
        <div class="stat-card-label">Meetings Held</div>
    </div>
</section>

<section class="mb-10 reveal">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('sparkles', 'w-3.5 h-3.5') ?> What we do</span>
        <h2 class="section-title">Everything the club runs on, in one place</h2>
        <p class="section-sub">Contributions, credit, meetings and reporting are all handled by the same
        system &mdash; so every figure a member sees is the figure the committee sees.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php
        $services = [
            ['savings',  'Savings',  'Monthly contributions and balances tracked automatically, with a full deposit and withdrawal history for every member.'],
            ['loans',    'Loans',    'Apply online, get reviewed by club leadership, and follow a transparent repayment schedule from the first instalment to the last.'],
            ['calendar', 'Meetings', 'Schedules, attendance and minutes recorded and available to every member, so nobody misses what was decided.'],
            ['reports',  'Reports',  'Financial and membership reports generated from real transaction records — ready to print or save as PDF.'],
        ];
        foreach ($services as $i => [$ico, $title, $body]):
            $photo = $img['services'][$i] ?? null;
        ?>
            <article class="svc-card svc-card-photo">
                <?php if ($photo): ?>
                    <div class="svc-photo">
                        <img src="<?= e(siteImage($photo['id'], 640)) ?>"
                             srcset="<?= e(siteImageSrcset($photo['id'], [420, 640])) ?>"
                             sizes="(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw"
                             alt="<?= e($photo['alt']) ?>"
                             loading="lazy" decoding="async" width="640" height="420">
                        <span class="svc-photo-icon"><?= icon($ico, 'w-5 h-5') ?></span>
                    </div>
                <?php endif; ?>
                <div class="svc-body">
                    <h3><?= e($title) ?></h3>
                    <p><?= e($body) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="grid gap-6 lg:grid-cols-2 lg:items-center mb-10 reveal">
    <div class="media-panel aspect-[4/3] lg:aspect-[5/4] lg:max-h-[520px]">
        <img src="<?= e(siteImage($img['ledger']['id'], 1024)) ?>"
             srcset="<?= e(siteImageSrcset($img['ledger']['id'], [640, 1024])) ?>"
             sizes="(min-width: 1024px) 50vw, 100vw"
             alt="<?= e($img['ledger']['alt']) ?>"
             loading="lazy" decoding="async" width="1024" height="768">
        <div class="media-badge">
            <div class="flex items-center gap-3">
                <span class="shrink-0 grid place-items-center w-9 h-9 rounded-lg bg-gold text-primary-dark">
                    <?= icon('scale', 'w-5 h-5') ?>
                </span>
                <p class="text-xs sm:text-sm font-semibold leading-snug">
                    Every figure a member sees is the figure the committee sees.
                </p>
            </div>
        </div>
    </div>

    <div>
        <span class="section-eyebrow"><?= icon('users', 'w-3.5 h-3.5') ?> Built around members</span>
        <h2 class="section-title text-xl sm:text-2xl">A club that shows its working</h2>
        <p class="section-sub mb-6">Savings groups usually fail on bookkeeping, not on goodwill.
        We removed that risk by putting every contribution, loan and decision into one system
        from the first day.</p>

        <ul class="space-y-4">
            <?php
            $promises = [
                ['check',  'Recorded the same day', 'Your deposit is entered against your member number when it is received, not at month end.'],
                ['scale',  'Corrections, not rewrites', 'Mistakes are fixed by adjusting entry, so the history stays intact and auditable.'],
                ['lock',   'Your records are yours', 'Role-based access means members see their own data and leadership sees only what their role needs.'],
                ['clock',  'No waiting for the ledger', 'Balances, repayment schedules and reports are available whenever you log in.'],
            ];
            foreach ($promises as [$ico, $title, $body]):
            ?>
                <li class="why-item">
                    <span class="why-item-icon"><?= icon($ico, 'w-5 h-5') ?></span>
                    <div>
                        <h3><?= e($title) ?></h3>
                        <p><?= e($body) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="mb-10 reveal">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('arrow-right', 'w-3.5 h-3.5') ?> How it works</span>
        <h2 class="section-title">Four steps from joining to growing</h2>
        <p class="section-sub">No paperwork queues and no guesswork &mdash; here is the whole path.</p>
    </div>

    <div class="step-track grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <article class="step">
            <div class="step-num">1</div>
            <h3>Send a request</h3>
            <p>Fill in the membership form online. Leadership reviews it and you are notified either way.</p>
        </article>
        <article class="step">
            <div class="step-num">2</div>
            <h3>Save regularly</h3>
            <p>Make your monthly contribution. Every deposit is recorded against your name the same day.</p>
        </article>
        <article class="step">
            <div class="step-num">3</div>
            <h3>Borrow when needed</h3>
            <p>Apply for a loan against your standing. Approved amounts come with a clear repayment plan.</p>
        </article>
        <article class="step">
            <div class="step-num">4</div>
            <h3>Watch it grow</h3>
            <p>Track your balance, repayments and the club's reports from your own dashboard.</p>
        </article>
    </div>
</section>

<section class="mb-10 reveal">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('sparkles', 'w-3.5 h-3.5') ?> Our community</span>
        <h2 class="section-title">Saving alongside people like you</h2>
        <p class="section-sub">Students and staff at Tumba College, building the habit together.</p>
    </div>

    <div class="mosaic">
        <?php
        $captions = ['Monthly meetings', 'Track it anywhere', 'Saving for what is next', 'Goals reached together'];
        foreach ($img['community'] as $i => $photo):
        ?>
            <figure>
                <img src="<?= e(siteImage($photo['id'], 640)) ?>"
                     srcset="<?= e(siteImageSrcset($photo['id'], [420, 640])) ?>"
                     sizes="(min-width: 1024px) 25vw, 50vw"
                     alt="<?= e($photo['alt']) ?>"
                     loading="lazy" decoding="async" width="640" height="800">
                <figcaption><?= e($captions[$i] ?? '') ?></figcaption>
            </figure>
        <?php endforeach; ?>
    </div>
</section>

<section class="mb-10 reveal">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('shield', 'w-3.5 h-3.5') ?> Why members stay</span>
        <h2 class="section-title">Built for trust, not just bookkeeping</h2>
    </div>

    <div class="card">
        <div class="grid gap-6 sm:gap-7 sm:grid-cols-2">
            <div class="why-item">
                <span class="why-item-icon"><?= icon('scale', 'w-5 h-5') ?></span>
                <div>
                    <h3>Open books</h3>
                    <p>Balances, loans and expenses all trace back to a recorded transaction. Nothing is
                    adjusted quietly.</p>
                </div>
            </div>
            <div class="why-item">
                <span class="why-item-icon"><?= icon('users', 'w-5 h-5') ?></span>
                <div>
                    <h3>Owned by members</h3>
                    <p>The club belongs to the people saving in it. Leadership serves fixed terms and
                    answers to the membership.</p>
                </div>
            </div>
            <div class="why-item">
                <span class="why-item-icon"><?= icon('lock', 'w-5 h-5') ?></span>
                <div>
                    <h3>Your data stays yours</h3>
                    <p>Accounts are protected with hashed passwords and role-based access. Members see
                    their own records.</p>
                </div>
            </div>
            <div class="why-item">
                <span class="why-item-icon"><?= icon('chat', 'w-5 h-5') ?></span>
                <div>
                    <h3>A direct line</h3>
                    <p>Message leadership from inside the system and get an answer on the record, not in
                    a side conversation.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($leadership): ?>
<section class="mb-10 reveal">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('leadership', 'w-3.5 h-3.5') ?> The committee</span>
        <h2 class="section-title">Our leadership</h2>
        <p class="section-sub">Elected by the membership to run the club's day-to-day business.</p>
    </div>

    <div class="card">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
            <?php foreach (array_slice($leadership, 0, 4) as $leader): ?>
                <a class="leader-card" href="<?= e(APP_URL) ?>/leadership.php">
                    <img src="<?= e(APP_URL) ?>/<?= e($leader['photo']) ?>" alt="<?= e($leader['name']) ?>"
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
                Meet the full team <?= icon('arrow-right', 'w-4 h-4') ?>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($announcements): ?>
<section class="mb-10 reveal">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('megaphone', 'w-3.5 h-3.5') ?> Latest news</span>
        <h2 class="section-title">Announcements</h2>
    </div>

    <div class="grid gap-4">
        <?php foreach ($announcements as $a): ?>
            <?php $posted = strtotime($a['posted_at']); ?>
            <article class="ann-card">
                <div class="ann-date">
                    <span class="d"><?= e(date('j', $posted)) ?></span>
                    <span class="m"><?= e(date('M', $posted)) ?></span>
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-primary-dark mb-1"><?= e($a['title']) ?></h3>
                    <p class="text-sm text-gray-500 leading-relaxed"><?= nl2br(e($a['content'])) ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="text-center mt-5">
        <a href="<?= e(APP_URL) ?>/announcements.php" class="link-more">
            View all announcements <?= icon('arrow-right', 'w-4 h-4') ?>
        </a>
    </div>
</section>
<?php endif; ?>

<section class="mb-10 reveal">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('chat', 'w-3.5 h-3.5') ?> Questions</span>
        <h2 class="section-title">Frequently asked</h2>
        <p class="section-sub">The things people ask the committee most often, answered plainly.
        Anything missing? <a class="text-primary font-semibold underline" href="<?= e(APP_URL) ?>/contact.php">Ask us directly</a>.</p>
    </div>

    <div class="grid gap-3">
        <?php foreach ($faqs as $i => [$question, $answer]): ?>
            <div class="faq-item">
                <button type="button" class="faq-q" aria-expanded="false" aria-controls="faq-a-<?= $i ?>" id="faq-q-<?= $i ?>">
                    <span><?= e($question) ?></span>
                    <span class="faq-sign" aria-hidden="true">+</span>
                </button>
                <div class="faq-a" id="faq-a-<?= $i ?>" role="region" aria-labelledby="faq-q-<?= $i ?>" hidden>
                    <?= e($answer) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script type="application/ld+json">
    <?= json_encode([
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(static fn(array $f): array => [
            '@type'          => 'Question',
            'name'           => $f[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
        ], $faqs),
    ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>
    </script>
</section>

<section class="cta-band reveal">
    <img class="cta-media"
         src="<?= e(siteImage($img['cta']['id'], 1600)) ?>"
         srcset="<?= e(siteImageSrcset($img['cta']['id'])) ?>"
         sizes="100vw" alt="" aria-hidden="true"
         loading="lazy" decoding="async" width="1600" height="900">
    <div class="cta-veil"></div>

    <span class="hero-eyebrow"><?= icon('sparkles', 'w-3.5 h-3.5') ?> Membership is open</span>
    <h2 class="text-2xl sm:text-3xl font-bold text-white mb-3 tracking-tight">Ready to get involved?</h2>
    <p class="text-white/75 max-w-xl mx-auto mb-7 text-sm sm:text-base leading-relaxed">
        Join <?= e($siteName) ?> at Tumba College, Rulindo and start your journey towards
        financial discipline &mdash; alongside people saving for the same reasons you are.
    </p>
    <div class="hero-actions">
        <a class="btn-gold justify-center" href="<?= e(APP_URL) ?>/membership.php">
            <?= icon('user-plus', 'w-4 h-4') ?> Join Now
        </a>
        <a class="btn btn-ghost justify-center inline-flex items-center gap-2" href="<?= e(APP_URL) ?>/contact.php">
            <?= icon('mail', 'w-4 h-4') ?> Contact Us
        </a>
    </div>
</section>

<section class="card reveal">
    <div class="flex flex-col sm:flex-row sm:items-center gap-5">
        <span class="shrink-0 grid place-items-center w-14 h-14 rounded-xl bg-primary text-gold">
            <?= icon('map-pin', 'w-7 h-7') ?>
        </span>
        <div class="flex-1 min-w-0">
            <h2 class="text-lg font-bold text-primary-dark mb-1">Visit us</h2>
            <p class="text-sm text-gray-500">Tumba College, Rulindo District, Northern Province, Rwanda</p>
        </div>
        <a href="<?= e(APP_URL) ?>/contact.php" class="link-more shrink-0">
            Get directions <?= icon('arrow-right', 'w-4 h-4') ?>
        </a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
