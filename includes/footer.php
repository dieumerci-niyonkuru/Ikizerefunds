<?php if (!empty($user)): ?>
        </div>
    </main>
</div>
<?php else: ?>
    </main>
<?php endif; ?>
<footer class="site-footer no-print bg-white border-t border-gray-200 mt-10">
    <div class="container">
        <div class="grid gap-6 grid-cols-2 lg:grid-cols-4 pb-4">
            <div>
                <div class="font-bold mb-2"><?= e($siteName ?? APP_NAME) ?></div>
                <p class="text-gray-500 text-sm">A savings and credit club at Tumba College, Rulindo District, Northern Province, Rwanda.</p>
            </div>
            <div>
                <div class="font-semibold text-sm mb-2">Quick Links</div>
                <ul class="text-sm space-y-1 text-gray-600">
                    <li><a href="<?= e(APP_URL) ?>/about.php">About</a></li>
                    <li><a href="<?= e(APP_URL) ?>/membership.php">Membership</a></li>
                    <li><a href="<?= e(APP_URL) ?>/leadership.php">Leadership</a></li>
                    <li><a href="<?= e(APP_URL) ?>/announcements.php">Announcements</a></li>
                    <li><a href="<?= e(APP_URL) ?>/feedback.php">Share an Idea</a></li>
                    <li><a href="<?= e(APP_URL) ?>/contact.php">Contact</a></li>
                </ul>
            </div>
            <div>
                <div class="font-semibold text-sm mb-2">Location</div>
                <ul class="text-sm space-y-1 text-gray-600">
                    <li>Tumba College</li>
                    <li>Rulindo District</li>
                    <li>Northern Province, Rwanda</li>
                </ul>
            </div>
            <div>
                <div class="font-semibold text-sm mb-2">Contact</div>
                <ul class="text-sm space-y-1 text-gray-600">
                    <?php if (!empty($settings['club_email'])): ?>
                        <li><a href="mailto:<?= e($settings['club_email']) ?>"><?= e($settings['club_email']) ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['club_phone'])): ?>
                        <li><a href="tel:<?= e($settings['club_phone']) ?>"><?= e($settings['club_phone']) ?></a></li>
                    <?php endif; ?>
                    <li class="text-gray-400">Tumba College, Rulindo</li>
                </ul>
            </div>
        </div>
        <div class="pt-4 border-t border-gray-100 pb-6">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="text-sm text-gray-500">
                    &copy; <?= date('Y') ?> <?= e($siteName ?? APP_NAME) ?> &mdash; Tumba College, Rulindo District. All rights reserved.
                </div>
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <span>Developed by</span>
                    <span class="font-bold text-primary">Dieu Merci</span>
                </div>
            </div>
        </div>
    </div>
</footer>
</body>
</html>
