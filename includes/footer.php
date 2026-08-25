<?php if (!empty($user)): ?>
        </div>
    </main>
</div>
<?php else: ?>
    </main>
<?php endif; ?>
<?php
$footerClub = clubInfo($settings ?? []);

// Social links appear only once leadership has filled them in under Settings,
// so the footer never shows a dead icon.
$socials = array_filter([
    'facebook'  => $settings['club_facebook']  ?? '',
    'twitter'   => $settings['club_twitter']   ?? '',
    'instagram' => $settings['club_instagram'] ?? '',
    'linkedin'  => $settings['club_linkedin']  ?? '',
    'whatsapp'  => !empty($footerClub['phone'])
        ? 'https://wa.me/' . preg_replace('/\D/', '', ($footerClub['phone'][0] === '0' ? '25' : '') . $footerClub['phone'])
        : '',
]);
?>
<footer class="site-footer no-print">
    <div class="footer-main">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
            <div class="grid gap-8 sm:gap-10 grid-cols-2 lg:grid-cols-4">

                <div class="col-span-2 lg:col-span-1">
                    <a class="flex items-center gap-3 no-underline mb-4" href="<?= e(APP_URL) ?>/index.php">
                        <img src="<?= e(APP_URL) ?>/<?= e($siteLogo ?? 'assets/images/logo.png') ?>"
                             alt="<?= e($siteName ?? APP_NAME) ?> logo"
                             class="h-11 w-11 object-contain shrink-0" width="44" height="44" loading="lazy">
                        <span class="min-w-0">
                            <span class="block text-base font-extrabold text-white leading-tight"><?= e($siteName ?? APP_NAME) ?></span>
                            <span class="block text-[9px] font-bold uppercase tracking-[0.18em] text-gold mt-0.5">Savings &amp; Credit Club</span>
                        </span>
                    </a>
                    <p class="text-sm text-white/60 leading-relaxed">
                        A member-owned savings and credit club at Tumba College, Rulindo District,
                        Northern Province, Rwanda &mdash; built on financial discipline and open books.
                    </p>

                    <?php if ($socials): ?>
                        <div class="flex items-center gap-2.5 mt-5">
                            <?php foreach ($socials as $network => $url): ?>
                                <a class="social-dot" href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer"
                                   aria-label="<?= e(ucfirst($network)) ?>"><?= brandIcon($network, 'w-4 h-4') ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <h2 class="footer-heading">Explore</h2>
                    <ul class="footer-list">
                        <li><a href="<?= e(APP_URL) ?>/index.php">Home</a></li>
                        <li><a href="<?= e(APP_URL) ?>/about.php">About Us</a></li>
                        <li><a href="<?= e(APP_URL) ?>/membership.php">Membership</a></li>
                        <li><a href="<?= e(APP_URL) ?>/leadership.php">Leadership</a></li>
                        <li><a href="<?= e(APP_URL) ?>/announcements.php">Announcements</a></li>
                        <li><a href="<?= e(APP_URL) ?>/feedback.php">Share an Idea</a></li>
                    </ul>
                </div>

                <div>
                    <h2 class="footer-heading">What we do</h2>
                    <ul class="footer-list">
                        <li><a href="<?= e(APP_URL) ?>/about.php">Monthly savings</a></li>
                        <li><a href="<?= e(APP_URL) ?>/about.php">Member loans</a></li>
                        <li><a href="<?= e(APP_URL) ?>/about.php">Meetings &amp; minutes</a></li>
                        <li><a href="<?= e(APP_URL) ?>/about.php">Financial reports</a></li>
                        <li><a href="<?= e(APP_URL) ?>/login.php">Member dashboard</a></li>
                    </ul>
                </div>

                <div>
                    <h2 class="footer-heading">Get in touch</h2>
                    <ul class="footer-list footer-contact">
                        <?php if ($footerClub['email']): ?>
                            <li>
                                <?= icon('mail', 'w-4 h-4 text-gold shrink-0 mt-0.5') ?>
                                <a class="break-all" href="mailto:<?= e($footerClub['email']) ?>"><?= e($footerClub['email']) ?></a>
                            </li>
                        <?php endif; ?>
                        <?php if ($footerClub['phone']): ?>
                            <li>
                                <?= icon('phone', 'w-4 h-4 text-gold shrink-0 mt-0.5') ?>
                                <a href="tel:<?= e(str_replace(' ', '', $footerClub['phone'])) ?>"><?= e($footerClub['phone']) ?></a>
                            </li>
                        <?php endif; ?>
                        <li>
                            <?= icon('map-pin', 'w-4 h-4 text-gold shrink-0 mt-0.5') ?>
                            <span>Tumba College, Rulindo District,<br>Northern Province, Rwanda</span>
                        </li>
                        <li>
                            <?= icon('clock', 'w-4 h-4 text-gold shrink-0 mt-0.5') ?>
                            <span>Committee meets monthly &mdash; see announcements</span>
                        </li>
                    </ul>

                    <a class="nav-cta-solid mt-5 w-full justify-center" href="<?= e(APP_URL) ?>/membership.php">
                        <?= icon('user-plus', 'w-4 h-4') ?> Join the Club
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="footer-bottom">
        <div class="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8 py-5">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
                <div class="text-xs text-white/70">
                    &copy; <?= date('Y') ?> <?= e($siteName ?? APP_NAME) ?>. All rights reserved.
                </div>

                <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs" aria-label="Legal">
                    <a href="<?= e(APP_URL) ?>/privacy.php">Privacy Policy</a>
                    <a href="<?= e(APP_URL) ?>/terms.php">Terms &amp; Conditions</a>
                    <a href="<?= e(APP_URL) ?>/contact.php">Contact</a>
                </nav>

                <div class="flex items-center gap-2 text-xs text-white/70">
                    <span>Developed by</span>
                    <span class="font-bold text-gold">Dieu Merci</span>
                </div>
            </div>
        </div>
    </div>
</footer>

<button type="button" id="to-top" class="to-top no-print" aria-label="Back to top">
    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
</button>

<script>
(function () {
    var items = document.querySelectorAll('.reveal');

    if (items.length) {
        var showAll = function () { items.forEach(function (el) { el.classList.add('shown'); }); };

        if (!('IntersectionObserver' in window) ||
            matchMedia('(prefers-reduced-motion: reduce)').matches) {
            showAll();
        } else {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    entry.target.classList.add('shown');
                    io.unobserve(entry.target);
                });
            }, { rootMargin: '0px 0px -8% 0px', threshold: 0.06 });

            items.forEach(function (el) { io.observe(el); });

            // Safety net: content must never stay invisible. If the observer has
            // not revealed anything while the page is on screen, drop the effect.
            setTimeout(function () {
                if (document.visibilityState !== 'visible') return;
                if (!document.querySelector('.reveal.shown')) showAll();
            }, 2500);
        }

        // Printing does not scroll, so reveal everything before the dialog opens.
        addEventListener('beforeprint', showAll);
    }

    // Back-to-top button appears after a screenful of scrolling.
    var top = document.getElementById('to-top');
    if (top) {
        var sync = function () { top.classList.toggle('show', window.scrollY > 600); };
        addEventListener('scroll', sync, { passive: true });
        sync();
        top.addEventListener('click', function () {
            var reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
        });
    }

    // FAQ accordion — one open at a time, keyboard accessible via <button>.
    document.querySelectorAll('.faq-q').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var panel = btn.nextElementSibling;
            var isOpen = btn.getAttribute('aria-expanded') === 'true';

            document.querySelectorAll('.faq-q[aria-expanded="true"]').forEach(function (other) {
                other.setAttribute('aria-expanded', 'false');
                other.nextElementSibling.hidden = true;
            });

            if (!isOpen) {
                btn.setAttribute('aria-expanded', 'true');
                panel.hidden = false;
            }
        });
    });
})();
</script>
</body>
</html>
