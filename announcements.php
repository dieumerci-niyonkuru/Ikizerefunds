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

$pageTitle = 'Announcements';
$pageDescription = 'Latest news, notices and decisions published by the IKIZERE FUNDS Club committee.';

require __DIR__ . '/includes/header.php';
?>
<section class="page-head">
    <span class="section-eyebrow"><?= icon('megaphone', 'w-3.5 h-3.5') ?> Latest news</span>
    <h1 class="section-title text-2xl sm:text-3xl">Announcements</h1>
    <p class="section-sub">News, notices and decisions from <?= e($siteName) ?>'s committee &mdash;
    published here so every member sees the same information at the same time.</p>
</section>

<?php if (!$announcements): ?>
    <div class="card text-center py-14">
        <span class="mx-auto grid place-items-center w-16 h-16 rounded-2xl bg-primary-light text-primary mb-4">
            <?= icon('megaphone', 'w-8 h-8') ?>
        </span>
        <h2 class="text-lg font-bold text-primary-dark mb-2">No announcements yet</h2>
        <p class="text-sm text-gray-500 max-w-md mx-auto mb-6">
            The committee has not published anything yet. Notices about meetings, contribution
            deadlines and club decisions will appear here first.
        </p>
        <div class="flex flex-wrap justify-center gap-3">
            <a class="nav-cta-solid" href="<?= e(APP_URL) ?>/membership.php">
                <?= icon('user-plus', 'w-4 h-4') ?> Join the Club
            </a>
            <a class="nav-cta-outline sm:inline-flex" href="<?= e(APP_URL) ?>/contact.php">
                <?= icon('mail', 'w-4 h-4') ?> Contact the committee
            </a>
        </div>
    </div>
<?php else: ?>
    <section class="grid gap-5 lg:grid-cols-2 mb-6">
        <?php foreach ($announcements as $a): ?>
            <?php $posted = strtotime($a['posted_at']); ?>
            <article class="ann-panel">
                <div class="flex items-start gap-4">
                    <div class="ann-date shrink-0">
                        <span class="d"><?= e(date('j', $posted)) ?></span>
                        <span class="m"><?= e(date('M', $posted)) ?></span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-base sm:text-lg font-bold text-primary-dark leading-snug mb-2">
                            <?= e($a['title']) ?>
                        </h2>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500">
                            <?php if (!empty($a['posted_by_name'])): ?>
                                <?= avatarHtml($a['posted_by_photo'] ?? null, $a['posted_by_name'], 'w-6 h-6 text-[10px]') ?>
                                <span class="font-semibold text-gray-500"><?= e($a['posted_by_name']) ?></span>
                                <span aria-hidden="true">&middot;</span>
                            <?php endif; ?>
                            <time datetime="<?= e(date('c', $posted)) ?>"><?= e(date('j F Y, H:i', $posted)) ?></time>
                        </div>
                    </div>
                </div>

                <p class="text-sm text-gray-600 leading-relaxed mt-4 pt-4 border-t border-gray-100">
                    <?= nl2br(e($a['content'])) ?>
                </p>
            </article>
        <?php endforeach; ?>
    </section>

    <div class="card text-center">
        <p class="text-sm text-gray-500 mb-3">
            Showing all <?= count($announcements) ?> published
            <?= count($announcements) === 1 ? 'announcement' : 'announcements' ?>.
        </p>
        <a href="<?= e(APP_URL) ?>/contact.php" class="link-more justify-center">
            Question about a notice? Contact us <?= icon('arrow-right', 'w-4 h-4') ?>
        </a>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
