<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$rows = db()->query('SELECT setting_key, setting_value FROM club_settings')->fetchAll();
$settings = array_column($rows, 'setting_value', 'setting_key');
$leadership = require __DIR__ . '/includes/leadership.php';

require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Contact Us</h1>
    <p>Have a question about membership, savings, or loans? Reach out to <?= e($siteName) ?> directly.</p>
</div>

<div class="card">
    <div class="dashboard-grid">
        <?php if (!empty($settings['club_email'])): ?>
            <div class="card">
                <h3 class="mb-1">Email</h3>
                <a href="mailto:<?= e($settings['club_email']) ?>"><?= e($settings['club_email']) ?></a>
            </div>
        <?php endif; ?>
        <?php if (!empty($settings['club_phone'])): ?>
            <div class="card">
                <h3 class="mb-1">Phone</h3>
                <a href="tel:<?= e($settings['club_phone']) ?>"><?= e($settings['club_phone']) ?></a>
            </div>
        <?php endif; ?>
        <div class="card">
            <h3 class="mb-1">&#128205; Location</h3>
            <p class="text-gray-600">Tumba College, Rulindo District<br>Northern Province, Rwanda</p>
        </div>
    </div>
    <?php if (empty($settings['club_email']) && empty($settings['club_phone'])): ?>
        <p class="text-gray-500 mt-3">Club contact details haven't been published yet. You can reach our committee members directly below.</p>
    <?php endif; ?>
</div>

<?php if ($leadership): ?>
<div class="card">
    <h2>Committee Contacts</h2>
    <div class="grid sm:grid-cols-2 gap-4 mt-3">
        <?php foreach ($leadership as $leader): ?>
            <div class="card">
                <div class="flex items-center gap-3">
                    <img src="<?= e(APP_URL) ?>/<?= e($leader['photo']) ?>" alt="<?= e($leader['name']) ?>"
                         class="w-12 h-12 rounded-full object-cover object-top border border-gray-200">
                    <div>
                        <div class="font-semibold text-sm"><?= e($leader['name']) ?></div>
                        <div class="text-gray-500 text-xs"><?= e($leader['title']) ?></div>
                        <?php if (!empty($leader['phone'])): ?>
                            <a href="tel:<?= e($leader['phone']) ?>" class="text-xs text-primary font-semibold">TEL: <?= e($leader['phone']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($leader['email'])): ?>
                            <div><a href="mailto:<?= e($leader['email']) ?>" class="text-xs text-primary"><?= e($leader['email']) ?></a></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="card text-center">
    <h2>Visit Us</h2>
    <p class="text-gray-600">We are located at <strong>Tumba College, Rulindo District, Northern Province, Rwanda</strong>.</p>
    <p class="text-gray-500 text-sm mt-2">Feel free to visit us during working hours or reach out to any committee member.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
