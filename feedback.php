<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_feedback') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '') ?: null;
    $email = trim($_POST['email'] ?? '') ?: null;
    $message = trim($_POST['message'] ?? '');

    if ($message === '') {
        setFlash('error', 'Please share your idea before submitting.');
    } elseif ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('error', 'That email address does not look right. Leave it blank if you prefer.');
    } else {
        $stmt = db()->prepare('INSERT INTO feedback (name, email, message) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, $message]);
        setFlash('success', 'Thank you! Your idea has been shared with our leadership.');
    }
    redirect('feedback.php');
}

$pageTitle = 'Share an Idea';
$pageDescription = 'Send a suggestion or idea to the IKIZERE FUNDS Club committee. '
    . 'Every submission reaches leadership directly, and you can stay anonymous.';

require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <span class="section-eyebrow"><?= icon('bulb', 'w-3.5 h-3.5') ?> Your voice</span>
    <h1 class="section-title text-2xl sm:text-3xl">Share an idea</h1>
    <p class="section-sub">Have a suggestion for <?= e($siteName) ?>? Savings plans, meeting times,
    how loans are reviewed &mdash; anything that could make the club work better for members.</p>
</section>

<section class="grid gap-6 lg:grid-cols-5 mb-6">
    <div class="card mb-0 lg:col-span-3">
        <h2 class="text-lg font-bold text-primary-dark mb-1">Tell us what you think</h2>
        <p class="text-sm text-gray-500 mb-5">Name and email are optional &mdash; leave them blank
        to stay anonymous. Only the message is required.</p>

        <form method="post" action="" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="action" value="submit_feedback">

            <div class="grid gap-x-4 sm:grid-cols-2">
                <div>
                    <label for="name">Your name <span class="text-gray-500 font-normal">(optional)</span></label>
                    <input type="text" id="name" name="name" autocomplete="name" maxlength="150"
                           placeholder="e.g. Uwase Claudine">
                </div>
                <div>
                    <label for="email">Your email <span class="text-gray-500 font-normal">(optional)</span></label>
                    <input type="email" id="email" name="email" autocomplete="email" maxlength="150"
                           placeholder="you@example.com">
                </div>
            </div>

            <label for="message">Your idea <span class="text-red-600">*</span></label>
            <textarea id="message" name="message" rows="7" required maxlength="2000"
                      placeholder="What would you change, add, or do differently?"></textarea>

            <button type="submit" class="nav-cta-solid">
                <?= icon('bulb', 'w-4 h-4') ?> Submit idea
            </button>
        </form>
    </div>

    <div class="lg:col-span-2 grid gap-4 content-start">
        <div class="svc-card">
            <div class="svc-icon"><?= icon('lock', 'w-6 h-6') ?></div>
            <h3>Anonymous if you want</h3>
            <p>Skip the name and email fields and the committee sees only your message.</p>
        </div>
        <div class="svc-card">
            <div class="svc-icon"><?= icon('users', 'w-6 h-6') ?></div>
            <h3>It reaches real people</h3>
            <p>Submissions land in the committee's inbox and are reviewed at the monthly sitting.</p>
        </div>
        <div class="svc-card">
            <div class="svc-icon"><?= icon('megaphone', 'w-6 h-6') ?></div>
            <h3>Decisions get published</h3>
            <p>Changes the committee adopts are announced on the announcements page.</p>
        </div>
    </div>
</section>

<section class="card">
    <div class="flex flex-col sm:flex-row sm:items-center gap-5">
        <span class="shrink-0 grid place-items-center w-14 h-14 rounded-xl bg-primary text-gold">
            <?= icon('mail', 'w-7 h-7') ?>
        </span>
        <div class="flex-1 min-w-0">
            <h2 class="text-lg font-bold text-primary-dark mb-1">Need an answer, not just a suggestion?</h2>
            <p class="text-sm text-gray-500">Use the contact page to reach a specific committee member directly.</p>
        </div>
        <a href="<?= e(APP_URL) ?>/contact.php" class="link-more shrink-0">
            Contact us <?= icon('arrow-right', 'w-4 h-4') ?>
        </a>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
