<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$announcements = db()->query(
    'SELECT title, content, posted_at FROM announcements
     WHERE is_published = 1 ORDER BY posted_at DESC'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Announcements</h1>
    <p>The latest news and updates from <?= e($siteName) ?>.</p>
</div>

<div class="card">
    <?php if (!$announcements): ?>
        <p>No announcements yet. Check back soon.</p>
    <?php else: ?>
        <?php foreach ($announcements as $a): ?>
            <div class="pb-4 mb-4 border-b border-gray-200 last:border-0 last:pb-0 last:mb-0">
                <h3 class="mb-1"><?= e($a['title']) ?></h3>
                <small class="text-gray-500"><?= e($a['posted_at']) ?></small>
                <p><?= nl2br(e($a['content'])) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
