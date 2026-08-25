<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

http_response_code(404);

$pageTitle = 'Page Not Found';
$pageDescription = 'The page you were looking for does not exist. Head back to the '
    . 'IKIZERE FUNDS Club home page or use the links below.';

require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <span class="hero-eyebrow"><?= icon('info', 'w-3.5 h-3.5') ?> Error 404</span>
    <h1>This page took<br><span class="hero-highlight">an unplanned withdrawal.</span></h1>
    <p>We could not find the page you asked for. It may have been renamed, moved, or never
    existed &mdash; but everything else is exactly where you left it.</p>
    <div class="hero-actions">
        <a class="nav-cta-solid justify-center" href="<?= e(APP_URL) ?>/index.php">
            <?= icon('home', 'w-4 h-4') ?> Back to Home
        </a>
        <a class="btn btn-ghost justify-center inline-flex items-center gap-2" href="<?= e(APP_URL) ?>/contact.php">
            <?= icon('mail', 'w-4 h-4') ?> Contact Us
        </a>
    </div>
</section>

<section class="mb-10">
    <div class="section-head">
        <span class="section-eyebrow"><?= icon('arrow-right', 'w-3.5 h-3.5') ?> Try one of these</span>
        <h2 class="section-title">Popular pages</h2>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php
        $suggestions = [
            ['about.php',         'info',       'About Us',      'Our story, mission and how the club works'],
            ['membership.php',    'user-plus',  'Membership',    'Requirements, benefits and how to join'],
            ['announcements.php', 'megaphone',  'Announcements', 'Latest news from the committee'],
            ['contact.php',       'mail',       'Contact',       'Reach the club directly'],
        ];
        foreach ($suggestions as [$href, $ico, $label, $desc]):
        ?>
            <a class="svc-card no-underline" href="<?= e(APP_URL) ?>/<?= e($href) ?>">
                <div class="svc-icon"><?= icon($ico, 'w-6 h-6') ?></div>
                <h3><?= e($label) ?></h3>
                <p><?= e($desc) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
