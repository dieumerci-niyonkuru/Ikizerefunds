<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$leadership = require __DIR__ . '/includes/leadership.php';

require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Leadership</h1>
    <p>The leaders below oversee <?= e($siteName) ?>'s day-to-day operations and
    financial accountability to members.</p>
</div>

<div class="card">
    <div class="dashboard-grid">
        <?php foreach ($leadership as $leader): ?>
            <div class="text-center">
                <img src="<?= e(APP_URL) ?>/<?= e($leader['photo']) ?>" alt="<?= e($leader['name']) ?>"
                     class="w-32 h-32 rounded-full object-cover object-top mx-auto mb-3 border border-gray-200">
                <div class="font-semibold"><?= e($leader['name']) ?></div>
                <div class="text-gray-500 text-sm mb-1"><?= e($leader['title']) ?></div>
                <?php if (!empty($leader['phone'])): ?>
                    <div class="text-xs text-gray-500">TEL: <?= e($leader['phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($leader['email'])): ?>
                    <a href="mailto:<?= e($leader['email']) ?>" class="text-xs"><?= e($leader['email']) ?></a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$leadership): ?>
            <p>Leadership details will be published soon.</p>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
