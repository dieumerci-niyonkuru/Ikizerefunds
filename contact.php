<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// The contact form writes to the same inbound-message table the "Share an Idea"
// page uses, so leadership reads everything from one place.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'contact_message') {
    verifyCsrf();
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    $errors = [];
    if ($name === '') {
        $errors[] = 'your name';
    }
    if ($message === '') {
        $errors[] = 'a message';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'a valid email address';
    }

    if ($errors) {
        setFlash('error', 'Please provide ' . implode(' and ', $errors) . '.');
    } else {
        $body = $subject !== '' ? '[' . $subject . '] ' . $message : $message;
        db()->prepare('INSERT INTO feedback (name, email, message) VALUES (?, ?, ?)')
            ->execute([$name, $email !== '' ? $email : null, $body]);
        setFlash('success', 'Thank you — your message has reached the committee. We will reply soon.');
    }
    redirect('contact.php');
}

$rows = db()->query('SELECT setting_key, setting_value FROM club_settings')->fetchAll();
$settings = array_column($rows, 'setting_value', 'setting_key');
$leadership = require __DIR__ . '/includes/leadership.php';

$pageTitle = 'Contact Us';
$pageDescription = 'Reach IKIZERE FUNDS Club at Tumba College, Rulindo District — '
    . 'email, phone, location and direct committee contacts.';

require __DIR__ . '/includes/header.php';

$mapQuery = rawurlencode($club['mapQuery']);
?>
<section class="page-head">
    <span class="section-eyebrow"><?= icon('mail', 'w-3.5 h-3.5') ?> Get in touch</span>
    <h1 class="section-title text-2xl sm:text-3xl">Contact <?= e($siteName) ?></h1>
    <p class="section-sub">Questions about membership, savings or loans? Reach the committee
    directly &mdash; by phone, by email, or with the form below.</p>
</section>

<section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6">
    <?php if ($club['email']): ?>
        <a class="svc-card no-underline" href="mailto:<?= e($club['email']) ?>">
            <div class="svc-icon"><?= icon('mail', 'w-6 h-6') ?></div>
            <h3>Email us</h3>
            <p class="break-all"><?= e($club['email']) ?></p>
        </a>
    <?php endif; ?>

    <?php if ($club['phone']): ?>
        <a class="svc-card no-underline" href="tel:<?= e(str_replace(' ', '', $club['phone'])) ?>">
            <div class="svc-icon"><?= icon('phone', 'w-6 h-6') ?></div>
            <h3>Call us</h3>
            <p><?= e($club['phone']) ?></p>
        </a>
    <?php endif; ?>

    <div class="svc-card">
        <div class="svc-icon"><?= icon('map-pin', 'w-6 h-6') ?></div>
        <h3>Visit us</h3>
        <p>Tumba College, Rulindo District,<br>Northern Province, Rwanda</p>
    </div>

    <div class="svc-card">
        <div class="svc-icon"><?= icon('clock', 'w-6 h-6') ?></div>
        <h3>When we meet</h3>
        <p>The committee sits monthly. Dates are posted on the announcements page.</p>
    </div>
</section>

<section class="grid gap-6 lg:grid-cols-5 mb-6">
    <div class="card mb-0 lg:col-span-3">
        <h2 class="text-lg font-bold text-primary-dark mb-1">Send us a message</h2>
        <p class="text-sm text-gray-500 mb-5">Fill this in and it goes straight to the committee's
        inbox. Fields marked <span class="text-red-600">*</span> are required.</p>

        <form method="post" action="" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="contact_message">

            <div class="grid gap-x-4 sm:grid-cols-2">
                <div>
                    <label for="name">Your name <span class="text-red-600">*</span></label>
                    <input type="text" id="name" name="name" required autocomplete="name"
                           maxlength="150" placeholder="e.g. Uwase Claudine">
                </div>
                <div>
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" autocomplete="email"
                           maxlength="150" placeholder="you@example.com">
                </div>
            </div>

            <label for="subject">Subject</label>
            <select id="subject" name="subject">
                <option value="">Choose a topic&hellip;</option>
                <option>Membership enquiry</option>
                <option>Savings question</option>
                <option>Loan question</option>
                <option>Meetings &amp; attendance</option>
                <option>Something else</option>
            </select>

            <label for="message">Your message <span class="text-red-600">*</span></label>
            <textarea id="message" name="message" rows="6" required maxlength="2000"
                      placeholder="Tell us how we can help&hellip;"></textarea>

            <button type="submit" class="nav-cta-solid">
                <?= icon('mail', 'w-4 h-4') ?> Send message
            </button>
        </form>
    </div>

    <div class="card mb-0 lg:col-span-2 flex flex-col">
        <h2 class="text-lg font-bold text-primary-dark mb-1">Find us</h2>
        <p class="text-sm text-gray-500 mb-4">Tumba College, Rulindo District, Northern Province.</p>

        <div class="map-frame flex-1">
            <iframe
                src="https://maps.google.com/maps?q=<?= $mapQuery ?>&amp;output=embed"
                title="Map showing Tumba College, Rulindo District, Rwanda"
                loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                allowfullscreen></iframe>
        </div>

        <a class="link-more mt-4" target="_blank" rel="noopener noreferrer"
           href="https://www.google.com/maps/search/?api=1&amp;query=<?= $mapQuery ?>">
            Open in Google Maps <?= icon('arrow-right', 'w-4 h-4') ?>
        </a>
    </div>
</section>

<?php if ($leadership): ?>
<section class="mb-6">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('leadership', 'w-3.5 h-3.5') ?> Direct line</span>
        <h2 class="section-title">Committee contacts</h2>
        <p class="section-sub">Prefer to speak to someone specific? Reach any committee member directly.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <?php foreach ($leadership as $leader): ?>
            <article class="quote-card">
                <div class="flex items-center gap-3">
                    <img src="<?= e(APP_URL) ?>/<?= e($leader['photo']) ?>" alt="<?= e($leader['name']) ?>"
                         class="w-14 h-14 rounded-full object-cover object-top ring-2 ring-gray-100 shrink-0" loading="lazy">
                    <div class="min-w-0">
                        <div class="font-bold text-sm text-primary-dark leading-tight"><?= e($leader['name']) ?></div>
                        <div class="text-xs font-bold text-gold-deep uppercase tracking-wide mt-0.5"><?= e($leader['title']) ?></div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-gray-100 space-y-2 text-sm">
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
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="cta-band">
    <span class="hero-eyebrow"><?= icon('user-plus', 'w-3.5 h-3.5') ?> Ready to join?</span>
    <h2 class="text-2xl sm:text-3xl font-bold text-white mb-3 tracking-tight">Become a member</h2>
    <p class="text-white/75 max-w-xl mx-auto mb-7 text-sm sm:text-base leading-relaxed">
        Send a membership request and the committee will review it at its next sitting.
    </p>
    <div class="hero-actions">
        <a class="nav-cta-solid justify-center" href="<?= e(APP_URL) ?>/membership.php">
            <?= icon('user-plus', 'w-4 h-4') ?> Join the Club
        </a>
        <a class="btn btn-ghost justify-center inline-flex items-center gap-2" href="<?= e(APP_URL) ?>/feedback.php">
            <?= icon('bulb', 'w-4 h-4') ?> Share an Idea
        </a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
