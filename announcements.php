<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$announcements = db()->query(
    'SELECT announcements.title, announcements.content, announcements.posted_at,
            users.full_name AS posted_by_name, users.photo_path AS posted_by_photo
     FROM announcements
     LEFT JOIN users ON users.id = announcements.posted_by
     WHERE announcements.is_published = 1
     ORDER BY announcements.posted_at DESC'
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
                <div class="flex items-center gap-2 text-gray-500 text-sm mb-2">
                    <?php if (!empty($a['posted_by_name'])): ?>
                        <?= avatarHtml($a['posted_by_photo'] ?? null, $a['posted_by_name'], 'w-6 h-6 text-[10px]') ?>
                        <span><?= e($a['posted_by_name']) ?></span>
                        <span>&middot;</span>
                    <?php endif; ?>
                    <small><?= e($a['posted_at']) ?></small>
                </div>
                <p><?= nl2br(e($a['content'])) ?></p>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
