<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$rows = db()->query('SELECT setting_key, setting_value FROM club_settings')->fetchAll();
$settings = array_column($rows, 'setting_value', 'setting_key');

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
        <?php if (!empty($settings['club_address'])): ?>
            <div class="card">
                <h3 class="mb-1">Address</h3>
                <p><?= e($settings['club_address']) ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php if (empty($settings['club_email']) && empty($settings['club_phone']) && empty($settings['club_address'])): ?>
        <p>Contact details haven't been published yet. Please check back soon.</p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
