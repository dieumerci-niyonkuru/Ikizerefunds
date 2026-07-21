<?php
// Full-screen branded loading overlay. Masks the brief unstyled flash while
// the Tailwind CDN script processes on first paint, then fades out on
// window load. Re-appears (with a custom message via each form's
// data-loading-text attribute) whenever a form on the page is submitted, so
// there's no dead moment while the server processes the request.
?>
<div id="page-loader" style="position:fixed;inset:0;z-index:9999;background:linear-gradient(135deg,#16234B,#0D1730);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;transition:opacity .35s ease;">
    <?php if ($siteLogo): ?>
        <img src="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>" alt="" style="height:64px;width:64px;border-radius:14px;background:#fff;padding:8px;object-fit:contain;animation:ikzPulse 1.3s ease-in-out infinite;">
    <?php else: ?>
        <div style="height:64px;width:64px;border-radius:14px;background:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;color:#16234B;font-size:20px;animation:ikzPulse 1.3s ease-in-out infinite;"><?= e(strtoupper(substr($siteName, 0, 2))) ?></div>
    <?php endif; ?>
    <div style="color:#fff;font-size:13px;letter-spacing:.02em;opacity:.85;" id="page-loader-text">Loading&hellip;</div>
</div>
<noscript><style>#page-loader { display: none !important; }</style></noscript>
<style>
@keyframes ikzPulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.08); opacity: .8; } }
</style>
<script>
(function () {
    var loader = document.getElementById('page-loader');
    if (!loader) { return; }
    var hideLoader = function () {
        loader.style.opacity = '0';
        loader.style.pointerEvents = 'none';
    };
    window.addEventListener('load', hideLoader);
    setTimeout(hideLoader, 5000);
    Array.prototype.forEach.call(document.querySelectorAll('form'), function (form) {
        form.addEventListener('submit', function () {
            var text = document.getElementById('page-loader-text');
            if (text) { text.textContent = form.dataset.loadingText || 'Please wait…'; }
            loader.style.opacity = '1';
            loader.style.pointerEvents = 'auto';
        });
    });
})();
</script>
