<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/club_info.php';
require_once __DIR__ . '/images.php';

// Cache static pages for returning visitors
if (!isLoggedIn()) {
    header('Cache-Control: public, max-age=300');
}

$user = function_exists('currentUser') ? currentUser() : null;

$siteName = APP_NAME;
$siteLogo = 'assets/images/logo.png';
if (function_exists('db')) {
    // Cache settings in a file to avoid DB query on every page load
    $cacheFile = sys_get_temp_dir() . '/ikizere_settings.cache';
    $settings = [];
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        $settings = (array) json_decode(file_get_contents($cacheFile), true);
    } else {
        $rows = db()->query('SELECT setting_key, setting_value FROM club_settings')->fetchAll();
        $settings = array_column($rows, 'setting_value', 'setting_key');
        @file_put_contents($cacheFile, json_encode($settings));
    }
    $siteName = $settings['club_name'] ?? APP_NAME;
    if (!empty($settings['logo_path'])) { $siteLogo = $settings['logo_path']; }
}

$club = clubInfo($settings ?? []);

$navItems = $user ? require __DIR__ . '/nav.php' : [];
$publicNavItems = $user ? [] : require __DIR__ . '/public_nav.php';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentFile = $requestPath ? basename($requestPath) : 'index.php';
if ($currentFile === '' || $currentFile === '/') {
    $currentFile = 'index.php';
}

// Per-page SEO. A page sets $pageTitle / $pageDescription before including
// this file; anything it leaves out falls back to the site defaults.
$metaTitle = isset($pageTitle) && $pageTitle !== ''
    ? $pageTitle . ' | ' . $siteName
    : $siteName . ' — Savings & Credit Club, Tumba College';
$metaDescription = $pageDescription ?? (
    $siteName . ' is a member-owned savings and credit club at Tumba College, Rulindo District, '
    . 'Rwanda. Save monthly, borrow transparently, and track every franc from your own dashboard.'
);
$canonical = rtrim(APP_URL, '/') . '/' . ($currentFile === 'index.php' ? '' : $currentFile);
$metaImage = rtrim(APP_URL, '/') . '/' . ($pageImage ?? $siteLogo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($metaDescription) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta name="theme-color" content="#16234B">
    <link rel="preconnect" href="https://images.unsplash.com" crossorigin>
    <link rel="dns-prefetch" href="https://images.unsplash.com">
    <meta name="robots" content="<?= $user ? 'noindex, nofollow' : 'index, follow' ?>">

    <?php if ($siteLogo): ?>
        <link rel="icon" href="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>" sizes="any">
        <link rel="apple-touch-icon" href="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>">
    <?php endif; ?>

    <title><?= e($metaTitle) ?></title>

    <!-- Social sharing previews -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($metaTitle) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($metaImage) ?>">
    <meta property="og:locale" content="en_RW">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($metaTitle) ?>">
    <meta name="twitter:description" content="<?= e($metaDescription) ?>">
    <meta name="twitter:image" content="<?= e($metaImage) ?>">

    <?php if (!$user): ?>
    <!-- Structured data so search results can show the club as an organisation -->
    <script type="application/ld+json">
    <?= json_encode(array_filter([
        '@context'    => 'https://schema.org',
        '@type'       => 'Organization',
        'name'        => $siteName,
        'url'         => rtrim(APP_URL, '/') . '/',
        'logo'        => rtrim(APP_URL, '/') . '/' . $siteLogo,
        'description' => $metaDescription,
        'email'       => $club['email'] ?: null,
        'telephone'   => $club['phone'] ?: null,
        'address'     => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => 'Tumba College',
            'addressLocality' => 'Rulindo District',
            'addressRegion'   => 'Northern Province',
            'addressCountry'  => 'RW',
        ],
    ]), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) ?>
    </script>
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#16234B', dark: '#0D1730', light: '#E9EBF3' },
                        // `deep` is the accessible gold for small text on light
                        // backgrounds — `dark` only clears 3:1 and fails AA at body sizes.
                        gold: { DEFAULT: '#C9A227', dark: '#A9861E', deep: '#755D11', light: '#F7EFD6' },
                    },
                },
            },
        };
    </script>
    <style type="text/tailwindcss">
        @layer components {
            body { @apply bg-gray-100 text-gray-900; }

            .topbar { @apply sticky top-0 z-30 flex h-[68px] items-center justify-between gap-2 sm:gap-4 bg-primary px-3 sm:px-6 text-white; }
            .brand { @apply flex items-center gap-2.5 text-sm sm:text-lg font-bold text-white no-underline min-w-0 shrink-0; }
            .brand-logo { @apply h-9 w-9 sm:h-11 sm:w-11 rounded-lg bg-white p-0.5 object-contain shadow-sm; }
            .brand-sub { @apply hidden lg:block text-[10px] font-medium uppercase tracking-[0.18em] text-gold/80; }

            .brand-public { @apply flex items-center gap-2.5 no-underline min-w-0 shrink-0; }
            .brand-public-logo { @apply h-10 w-10 sm:h-12 sm:w-12 object-contain shrink-0; }
            .brand-public-name { @apply block text-sm sm:text-lg font-extrabold text-primary-dark leading-tight truncate; }
            .brand-public-sub { @apply hidden sm:block text-[9px] font-bold uppercase tracking-[0.18em] text-gold-deep mt-0.5; }

            .skip-link { @apply sr-only; }
            .topbar-nav { @apply flex items-center gap-1 sm:gap-4 shrink-0; }
            .sidebar-toggle { @apply inline-block md:hidden bg-transparent border-0 text-2xl text-white cursor-pointer; }

            /* ---------- Public site header: utility strip + main bar ---------- */

            .utility-bar { @apply bg-primary-dark text-white/75 text-xs; }
            .utility-inner { @apply max-w-[1400px] mx-auto flex items-center justify-between gap-3 px-3 sm:px-6 h-9 sm:h-10; }
            .utility-link { @apply inline-flex items-center gap-1.5 no-underline whitespace-nowrap; }
            .utility-divider { @apply hidden sm:block w-px h-4 bg-white/20; }

            .mainbar { @apply sticky top-0 z-30 bg-white border-b border-gray-200; }
            .mainbar-inner { @apply max-w-[1400px] mx-auto flex items-center justify-between gap-3 px-3 sm:px-6 h-[64px] sm:h-[76px]; }

            .public-nav-links { @apply hidden lg:flex items-center gap-1 flex-1 justify-center; }
            .public-nav-link { @apply relative flex items-center gap-1.5 px-3 xl:px-4 py-2 text-[13px] xl:text-sm font-bold uppercase tracking-wide text-primary-dark no-underline whitespace-nowrap; }

            .nav-cta-outline { @apply hidden sm:inline-flex items-center gap-2 rounded-full border border-gray-300 bg-white px-4 min-h-[44px] text-sm font-semibold text-primary-dark no-underline whitespace-nowrap; }
            .nav-avatar { @apply sm:hidden grid place-items-center w-11 h-11 rounded-full border border-gray-300 text-primary-dark no-underline shrink-0; }
            .nav-cta-solid { @apply inline-flex items-center justify-center gap-2 rounded-full bg-gold px-4 sm:px-6 min-h-[44px] text-[13px] sm:text-sm font-bold text-primary-dark no-underline shadow-sm whitespace-nowrap; }

            .public-nav-toggle { @apply lg:hidden bg-transparent border-0 text-primary-dark cursor-pointer leading-none p-2 shrink-0 rounded-lg; }
            .public-nav-panel { @apply lg:hidden fixed top-0 right-0 bottom-0 w-[86%] max-w-[360px] bg-white shadow-2xl z-50 flex-col overflow-y-auto; }
            .nav-scrim { @apply lg:hidden fixed inset-0 bg-primary-dark/50 z-40 hidden; }

            .mobile-nav-group-header { @apply flex items-center justify-between w-full px-5 py-4 text-sm font-bold uppercase tracking-wide text-primary-dark cursor-pointer bg-transparent border-0 text-left border-b border-gray-100; }
            .mobile-nav-single { @apply flex items-center gap-3 px-5 py-4 text-sm font-bold uppercase tracking-wide text-primary-dark no-underline border-b border-gray-100; }

            .nav-dropdown { @apply relative; }
            .nav-dropdown-menu { @apply absolute top-full left-0 pt-2 w-[290px] z-40; }
            .nav-dropdown-inner { @apply bg-white rounded-xl shadow-2xl border border-gray-200/80 p-1.5 overflow-hidden; }
            .nav-dropdown-link { @apply flex items-start gap-3 px-3 py-2.5 rounded-lg text-sm text-gray-700 no-underline; }
            .nav-dropdown-icon { @apply shrink-0 grid place-items-center w-9 h-9 rounded-lg bg-primary-light text-primary; }
            .nav-dropdown-label { @apply block font-semibold text-gray-900 leading-tight; }
            .nav-dropdown-desc { @apply block text-xs text-gray-600 mt-0.5 leading-snug; }

            .btn-gold { @apply inline-flex items-center justify-center gap-2 bg-gold text-primary-dark font-bold border-0 rounded-full px-5 sm:px-6 min-h-[44px] text-sm cursor-pointer no-underline shadow-sm; }

            .app-shell { @apply flex min-h-[calc(100vh-68px)]; }
            .sidebar { @apply w-[230px] shrink-0 bg-white border-r border-gray-200 p-3 fixed md:static top-[68px] md:top-0 bottom-0 left-0 z-10 transition-transform duration-200 overflow-y-auto shadow-lg md:shadow-none; }
            .sidebar-link { @apply flex items-center gap-2 rounded-lg px-3 py-2 mb-1 text-sm text-gray-800 no-underline hover:bg-primary-light; }
            .sidebar-link.active { @apply bg-primary text-white font-semibold; }
            .sidebar-icon { @apply inline-block w-5 text-center; }
            .app-content { @apply flex-1 min-w-0; }

            .container { @apply max-w-[1400px] mx-auto my-5 sm:my-8 px-4 sm:px-6 lg:px-8; }
            .card { @apply bg-white border border-gray-200 rounded-xl p-4 sm:p-6 mb-4 sm:mb-6 shadow-sm; }

            .btn { @apply inline-block bg-primary text-white border-0 rounded-md px-4 sm:px-5 py-2 text-sm cursor-pointer no-underline hover:bg-primary-dark; }
            button:not(.sidebar-toggle):not(.public-nav-toggle):not(.mobile-nav-group-header):not(.btn-ghost):not(.btn-plain) { @apply inline-block bg-primary text-white border-0 rounded-md px-4 sm:px-5 py-2 text-sm cursor-pointer hover:bg-primary-dark; }
            .btn-ghost { @apply bg-transparent border border-white/60 hover:bg-white/15; }
            .btn-plain { @apply bg-transparent border-0 rounded-none p-0; }

            .flash { @apply rounded-xl px-4 py-3 mb-4 text-sm; }
            .flash-error { @apply bg-red-50 text-red-800; }
            .flash-success { @apply bg-green-50 text-green-800; }

            .toast-stack { @apply fixed top-4 right-4 z-[9000] flex flex-col gap-2 w-[calc(100%-2rem)] max-w-sm; }
            .toast { @apply flex items-start gap-2 rounded-xl px-4 py-3 text-sm text-white shadow-lg; animation: toastIn .25s ease-out; }
            .toast-success { @apply bg-green-600; }
            .toast-error { @apply bg-red-600; }
            .toast-icon { @apply font-bold; }
            .toast-message { @apply flex-1; }
            .toast-close { @apply text-white/80 hover:text-white cursor-pointer text-lg leading-none; }
            .toast-hide { animation: toastOut .3s ease-in forwards; }
            @keyframes toastIn { from { opacity: 0; transform: translateX(16px); } to { opacity: 1; transform: translateX(0); } }
            @keyframes toastOut { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(16px); } }

            .badge { @apply inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize whitespace-nowrap; }
            .badge-success { @apply bg-green-50 text-green-800; }
            .badge-warning { @apply bg-amber-50 text-amber-800; }
            .badge-danger { @apply bg-red-50 text-red-800; }
            .badge-neutral { @apply bg-gray-100 text-gray-600; }

            form label { @apply block mb-1 font-semibold text-sm; }
            form input[type="text"], form input[type="password"], form input[type="email"],
            form input[type="number"], form input[type="date"], form input[type="datetime-local"],
            form input[type="month"], form input[type="file"], form select, form textarea {
                @apply w-full px-3 py-2.5 mb-4 border border-gray-300 rounded-md text-sm bg-white focus:outline-primary;
            }
            /* Comfortable tap height for single-line fields on touch screens */
            form input:not([type="checkbox"]):not([type="radio"]), form select { @apply min-h-[44px]; }
            form small { @apply block -mt-3 mb-4 text-gray-500; }

            .filter-bar { @apply flex flex-col sm:flex-row flex-wrap items-stretch sm:items-end gap-3 mb-4; }
            .filter-bar > div { @apply flex flex-col flex-1 min-w-[140px]; }
            .filter-bar label { @apply text-xs font-semibold text-gray-500 mb-1; }
            .filter-bar input[type="text"], .filter-bar input[type="date"], .filter-bar select {
                @apply w-full sm:w-auto sm:min-w-[150px] px-3 py-2 mb-0 border border-gray-300 rounded-md text-sm bg-white;
            }
            .filter-bar button, .filter-bar a.btn { @apply mb-0; }

            .table-wrap { @apply overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0; }
            table { @apply w-full border-collapse min-w-[500px]; }
            th, td { @apply text-left px-2 py-2 border-b border-gray-200 text-sm; }
            th { @apply text-gray-500 font-semibold uppercase text-xs tracking-wide whitespace-nowrap; }
            tbody tr:hover { @apply bg-primary-light; }

            .dashboard-grid { @apply grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3; }
            .dashboard-grid .card { @apply mb-0; }
            .stat-grid { @apply grid gap-3 sm:gap-4 mb-6 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4; }
            .stat-grid .stat, .report-summary .stat { @apply bg-white border border-gray-200 rounded-xl p-3 sm:p-4; }
            .stat-grid .stat .label, .report-summary .stat .label { @apply text-xs uppercase tracking-wide text-gray-500; }
            .stat-grid .stat .value, .report-summary .stat .value { @apply text-xl sm:text-2xl font-bold text-primary-dark; }
            .report-summary { @apply grid gap-3 sm:gap-4 mb-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4; }

            .auth-card { @apply max-w-md mx-auto mt-6 sm:mt-12 px-3 sm:px-0; }

            /* ---------- Home page ---------- */

            /* Photographic backdrop for the hero / CTA band. The <img> sits under
               a navy veil so headline text keeps its contrast over any photo. */
            .hero-media, .cta-media { @apply absolute inset-0 w-full h-full object-cover; z-index: -3; }
            .hero-veil, .cta-veil { @apply absolute inset-0; z-index: -2; }

            .media-panel { @apply relative overflow-hidden rounded-2xl shadow-lg bg-primary-dark; }
            .media-panel img { @apply w-full h-full object-cover; }
            .media-badge { @apply absolute left-4 bottom-4 right-4 sm:left-5 sm:bottom-5 sm:right-5 rounded-xl bg-primary-dark/85 text-white px-4 py-3 backdrop-blur-sm; }

            .mosaic { @apply grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4; }
            .mosaic figure { @apply relative overflow-hidden rounded-xl bg-primary-dark aspect-[4/5] shadow-sm; }
            .mosaic img { @apply w-full h-full object-cover; }
            .mosaic figcaption { @apply absolute inset-x-0 bottom-0 p-3 sm:p-4 text-white text-xs sm:text-sm font-semibold leading-snug; }

            .hero { @apply relative isolate overflow-hidden bg-primary text-white rounded-2xl px-5 py-14 sm:px-10 sm:py-24 mb-6 text-center shadow-xl; }
            .hero h1 { @apply text-3xl sm:text-4xl md:text-5xl font-bold mb-4 leading-tight tracking-tight; }
            .hero p { @apply text-white/75 max-w-2xl mx-auto mb-7 text-sm sm:text-base leading-relaxed; }
            .hero-eyebrow { @apply inline-flex items-center gap-2 rounded-full border border-gold/40 bg-gold/10 px-3.5 py-1.5 text-[11px] sm:text-xs font-semibold uppercase tracking-[0.14em] text-gold mb-5; }
            .hero-actions { @apply flex flex-col sm:flex-row flex-wrap justify-center gap-3; }
            .hero-trust { @apply mt-9 pt-7 border-t border-white/10 grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-6 max-w-2xl mx-auto; }
            .hero-trust-item { @apply flex items-center justify-center gap-2.5 text-sm text-white/70; }

            /* Split hero: copy on the left, photograph on the right */
            .hero-split { @apply grid lg:grid-cols-[1.05fr_.95fr] gap-8 lg:gap-12 items-center text-left px-5 py-10 sm:px-10 sm:py-14; }
            .hero-split .hero-copy { @apply min-w-0; }
            .hero-split h1 { @apply text-3xl sm:text-4xl xl:text-5xl; }
            .hero-split p { @apply mx-0 max-w-xl; }
            .hero-split .hero-actions { @apply justify-start; }
            .hero-split .hero-trust { @apply mx-0 max-w-none grid-cols-1 sm:grid-cols-3 mt-8 pt-6; }
            .hero-split .hero-trust-item { @apply justify-start; }
            .hero-figure { @apply relative overflow-hidden rounded-2xl shadow-2xl aspect-[4/3] lg:aspect-square ring-1 ring-white/15; }
            .hero-figure img { @apply w-full h-full object-cover; }

            .section-head { @apply mb-5 sm:mb-6; }
            .section-eyebrow { @apply inline-flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] text-gold-deep mb-2; }
            .section-title { @apply text-xl sm:text-2xl font-bold text-primary-dark mb-1.5 tracking-tight; }
            .section-sub { @apply text-sm text-gray-600 max-w-2xl leading-relaxed; }

            .stat-card { @apply relative overflow-hidden bg-white border border-gray-200 rounded-xl p-4 sm:p-5 shadow-sm; }
            .stat-card-icon { @apply grid place-items-center w-10 h-10 rounded-lg bg-primary-light text-primary mb-3; }
            .stat-card-value { @apply text-2xl sm:text-3xl font-bold text-primary-dark leading-none tracking-tight; }
            .stat-card-unit { @apply text-sm font-semibold text-gray-500 ml-1; }
            .stat-card-label { @apply text-[11px] uppercase tracking-wider text-gray-500 mt-2 font-semibold; }

            .svc-card { @apply relative overflow-hidden bg-white border border-gray-200 rounded-xl p-5 sm:p-6 shadow-sm h-full; }
            .svc-icon { @apply grid place-items-center w-12 h-12 rounded-xl bg-primary text-gold mb-4 shadow-sm; }
            .svc-card h3 { @apply text-base font-bold text-primary-dark mb-1.5; }
            .svc-card p { @apply text-sm text-gray-500 leading-relaxed; }

            /* Photo-topped variant of the service card */
            .svc-card-photo { @apply p-0 flex flex-col; }
            .svc-photo { @apply relative aspect-[16/10] overflow-hidden bg-primary-dark; }
            .svc-photo img { @apply w-full h-full object-cover; }
            .svc-photo-icon { @apply absolute left-4 bottom-4 grid place-items-center w-10 h-10 rounded-lg bg-gold text-primary-dark shadow-md; }
            .svc-body { @apply p-5 flex-1; }

            .step { @apply relative bg-white border border-gray-200 rounded-xl p-5 shadow-sm h-full; }
            .step-num { @apply grid place-items-center w-9 h-9 rounded-full bg-gold text-primary-dark font-bold text-sm mb-3 shadow-sm; }
            .step h3 { @apply text-sm font-bold text-primary-dark mb-1.5; }
            .step p { @apply text-sm text-gray-500 leading-relaxed; }

            .why-item { @apply flex items-start gap-3.5; }
            .why-item-icon { @apply shrink-0 grid place-items-center w-9 h-9 rounded-lg bg-gold-light text-gold-dark; }
            .why-item h3 { @apply text-sm font-bold text-primary-dark mb-1; }
            .why-item p { @apply text-sm text-gray-600 leading-relaxed; }

            .leader-card { @apply block text-center no-underline; }
            .leader-photo { @apply w-24 h-24 sm:w-28 sm:h-28 rounded-full object-cover object-top mx-auto mb-3 ring-2 ring-gray-100 shadow-sm; }
            .leader-name { @apply font-bold text-sm text-primary-dark leading-tight; }
            .leader-title { @apply text-xs font-bold text-gold-deep uppercase tracking-wide mt-1; }
            .leader-phone { @apply text-xs text-gray-500 mt-1; }

            .ann-card { @apply flex gap-4 bg-white border border-gray-200 rounded-xl p-4 sm:p-5 shadow-sm; }
            .ann-panel { @apply bg-white border border-gray-200 rounded-xl p-5 sm:p-6 shadow-sm h-full; }
            .ann-date { @apply shrink-0 grid place-items-center w-14 h-14 rounded-xl bg-primary-light text-primary-dark leading-none; }
            .ann-date .d { @apply text-lg font-bold; }
            .ann-date .m { @apply text-[10px] font-bold uppercase tracking-wider text-primary/60 mt-1; }

            .cta-band { @apply relative isolate overflow-hidden rounded-2xl bg-primary text-white px-5 py-10 sm:px-10 sm:py-12 text-center shadow-xl mb-6; }

            .link-more { @apply inline-flex items-center gap-1.5 py-1.5 text-sm font-bold text-primary no-underline; }

            .page-head { @apply mb-5 sm:mb-6; }

            /* Long-form legal / article copy */
            .prose-club h2 { @apply text-base sm:text-lg font-bold text-primary-dark mt-7 mb-2 first:mt-0; }
            .prose-club p { @apply text-sm text-gray-600 leading-relaxed mb-3; }
            .prose-club ul { @apply list-disc pl-5 mb-4 space-y-1.5 text-sm text-gray-600 leading-relaxed; }
            .prose-club a { @apply text-primary font-semibold underline; }

            /* FAQ accordion */
            .faq-item { @apply bg-white border border-gray-200 rounded-xl overflow-hidden; }
            .faq-q { @apply flex items-center justify-between gap-4 w-full text-left px-4 sm:px-5 py-4 bg-transparent border-0 cursor-pointer text-sm sm:text-base font-bold text-primary-dark; }
            .faq-a { @apply px-4 sm:px-5 pb-4 text-sm text-gray-500 leading-relaxed; }

            /* Testimonials */
            .quote-card { @apply relative bg-white border border-gray-200 rounded-xl p-5 sm:p-6 shadow-sm h-full flex flex-col; }
            .quote-mark { @apply text-5xl leading-none font-serif text-gold/40 mb-1; }
            .quote-body { @apply text-sm text-gray-600 leading-relaxed flex-1; }
            .quote-who { @apply flex items-center gap-3 mt-4 pt-4 border-t border-gray-100; }

            /* Embedded map */
            .map-frame { @apply relative overflow-hidden rounded-xl border border-gray-200 bg-gray-100 min-h-[260px]; }

            /* Leadership profile panel */
            .leader-panel { @apply bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm flex flex-col; }
            .leader-panel-top { @apply relative pt-7 pb-4 grid place-items-center; }
            .leader-panel-photo { @apply w-28 h-28 rounded-full object-cover object-top ring-4 ring-white shadow-md relative z-10; }

            /* ---------- Footer ---------- */

            .site-footer { @apply mt-12; }
            .footer-main { @apply bg-primary-dark text-white; }
            .footer-heading { @apply text-[11px] font-bold uppercase tracking-[0.16em] text-gold mb-4; }
            .footer-list { @apply space-y-1 text-sm text-white/60; }
            .footer-contact li { @apply flex items-start gap-2.5 leading-relaxed py-1; }
            .social-dot { @apply grid place-items-center w-11 h-11 rounded-full bg-white/10 text-white no-underline; }
            /* Solid, not a black overlay — .site-footer is transparent, so an
               alpha layer here would composite over the light page background. */
            .footer-bottom { @apply bg-[#080F21] text-white; }

            .to-top { @apply fixed bottom-5 right-5 z-40 grid place-items-center w-11 h-11 rounded-full bg-primary text-white shadow-lg cursor-pointer border-0 opacity-0 pointer-events-none; }

            h1 { @apply text-xl sm:text-2xl font-bold mb-4; }
            h2 { @apply text-lg sm:text-xl font-bold mb-3; }

            td .btn, td button { @apply text-xs px-2 py-1; }
        }
    </style>
    <style>
        /* Compound selectors — Tailwind CDN cannot @apply these */

        /* Brand */
        .brand span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        /* Topbar nav */
        .topbar-nav a { color: #fff; text-decoration: none; }

        /* User chip */
        .user-chip { font-size: 0.875rem; display: none; }
        @media (min-width: 768px) { .user-chip { display: inline; } }
        .user-chip small { opacity: 0.8; }

        /* Skip link — invisible until focused, then parks over the header */
        .skip-link:focus {
            position: fixed; top: 12px; left: 12px; z-index: 100;
            width: auto; height: auto; margin: 0; overflow: visible; clip: auto; clip-path: none;
            white-space: normal; padding: 10px 18px; border-radius: 10px;
            background: #16234B; color: #fff; font-weight: 700; font-size: .875rem;
            box-shadow: 0 10px 26px -10px rgba(13,23,48,.7);
        }

        /* Visible keyboard focus everywhere */
        a:focus-visible, button:focus-visible, input:focus-visible,
        select:focus-visible, textarea:focus-visible, [tabindex]:focus-visible {
            outline: 3px solid #C9A227; outline-offset: 2px; border-radius: 6px;
        }

        /* Logged-in topbar — navy gradient, gold hairline, elevates once scrolled */
        .topbar {
            background-image: linear-gradient(115deg, #0D1730 0%, #16234B 55%, #1B2C5E 100%);
            box-shadow: 0 1px 0 rgba(255,255,255,.06);
            transition: box-shadow .25s ease;
        }
        .topbar::after {
            content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 2px;
            background: linear-gradient(90deg, transparent, rgba(201,162,39,.75) 30%, rgba(201,162,39,.75) 70%, transparent);
        }
        .topbar.scrolled { box-shadow: 0 6px 24px -8px rgba(13,23,48,.55); }
        .brand span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .brand-logo { transition: transform .25s ease; }
        .brand:hover .brand-logo { transform: scale(1.05) rotate(-3deg); }

        /* Public main bar — white, lifts off the page once scrolled */
        .mainbar { transition: box-shadow .25s ease; }
        .mainbar.scrolled { box-shadow: 0 8px 26px -14px rgba(13,23,48,.35); }
        .brand-public-logo { transition: transform .25s ease; }
        .brand-public:hover .brand-public-logo { transform: scale(1.06) rotate(-3deg); }

        /* Desktop tabs — gold underline slides in under the active tab */
        .public-nav-link { transition: color .18s ease; }
        .public-nav-link::after {
            content: ""; position: absolute; left: 12px; right: 12px; bottom: 0; height: 3px;
            border-radius: 3px 3px 0 0; background: #C9A227;
            transform: scaleX(0); transform-origin: center; transition: transform .22s ease;
        }
        .public-nav-link:hover { color: #A9861E; }
        .public-nav-link:hover::after { transform: scaleX(.55); }
        .public-nav-link.active { color: #16234B; }
        .public-nav-link.active::after { transform: scaleX(1); }
        .public-nav-link .caret { font-size: .55rem; opacity: .5; transition: transform .22s ease; }
        .nav-dropdown:hover .public-nav-link .caret { transform: rotate(180deg); }

        /* Desktop dropdown — animated, bridges the gap back up to its tab */
        .nav-dropdown-menu {
            opacity: 0; visibility: hidden; transform: translateY(-6px);
            transition: opacity .18s ease, transform .18s ease, visibility .18s;
        }
        .nav-dropdown:hover .nav-dropdown-menu,
        .nav-dropdown:focus-within .nav-dropdown-menu {
            opacity: 1; visibility: visible; transform: translateY(0);
        }
        .nav-dropdown-link { transition: background .15s ease; }
        .nav-dropdown-link:hover { background: #E9EBF3; }
        .nav-dropdown-link:hover .nav-dropdown-icon { background: #16234B; color: #C9A227; }
        .nav-dropdown-icon { transition: background .15s ease, color .15s ease; }
        .nav-dropdown-link.is-current { background: #E9EBF3; }
        .nav-dropdown-link.is-current .nav-dropdown-icon { background: #16234B; color: #C9A227; }

        /* Header CTAs */
        .nav-cta-outline { transition: border-color .18s ease, color .18s ease, background .18s ease; }
        .nav-cta-outline:hover { border-color: #16234B; background: #E9EBF3; }
        .nav-avatar { transition: border-color .18s ease, background .18s ease; }
        .nav-avatar:hover { border-color: #16234B; background: #E9EBF3; }
        .nav-cta-solid, .btn-gold { transition: background .18s ease, transform .18s ease, box-shadow .18s ease; }
        .nav-cta-solid:hover, .btn-gold:hover {
            background: #A9861E; transform: translateY(-1px);
            box-shadow: 0 10px 22px -10px rgba(201,162,39,.9);
        }
        .public-nav-toggle { transition: background .15s ease; }
        .public-nav-toggle:hover { background: #E9EBF3; }

        /* The parked drawer sits past the right edge; clip (not hidden) keeps
           it from widening the page while leaving position:sticky working. */
        html { overflow-x: clip; }

        /* Mobile drawer — slides in from the right over a scrim.
           visibility:hidden while closed also keeps its links out of the tab order. */
        #public-nav-panel {
            display: flex; transform: translateX(105%); visibility: hidden;
            transition: transform .28s cubic-bezier(.4,0,.2,1), visibility .28s;
        }
        #public-nav-panel.open { transform: translateX(0); visibility: visible; }
        .nav-scrim.open { display: block; animation: scrimIn .2s ease-out; }
        @keyframes scrimIn { from { opacity: 0; } to { opacity: 1; } }
        body.nav-open { overflow: hidden; }

        /* Mobile accordion */
        .mobile-nav-group-header { transition: background .15s ease; }
        .mobile-nav-group-header:hover { background: #F7F8FB; }
        .mobile-nav-group-header .arrow { color: #755D11; font-size: 0.7rem; transition: transform 0.2s; }
        .mobile-nav-group-header.open { background: #F7F8FB; }
        .mobile-nav-group-header.open .arrow { transform: rotate(180deg); }
        .mobile-nav-submenu { display: none; background: #F7F8FB; }
        .mobile-nav-submenu.open { display: block; }
        .mobile-nav-submenu a { display: flex; align-items: center; gap: 10px; padding: 13px 20px 13px 32px; font-size: 0.875rem; font-weight: 500; color: #4B5563; text-decoration: none; border-bottom: 1px solid #EDEFF3; transition: background .15s, color .15s; }
        .mobile-nav-submenu a:last-child { border-bottom: none; }
        .mobile-nav-submenu a:hover { background: #E9EBF3; color: #16234B; }
        .mobile-nav-submenu a.active-sub { font-weight: 700; color: #16234B; border-left: 3px solid #C9A227; padding-left: 29px; }
        .mobile-nav-single { transition: background .15s ease; }
        .mobile-nav-single:hover { background: #F7F8FB; }
        .mobile-nav-single.active { color: #16234B; border-left: 3px solid #C9A227; background: #F7F8FB; }

        /* ---------- Home page ---------- */

        /* Hero + CTA band: layered radial gold glow over a navy field */
        .hero, .cta-band { background-image: linear-gradient(135deg, #0D1730 0%, #16234B 50%, #1D2F63 100%); }
        .hero::before, .cta-band::before {
            content: ""; position: absolute; inset: 0; z-index: -1;
            background:
                radial-gradient(60% 55% at 15% 0%, rgba(201,162,39,.22), transparent 60%),
                radial-gradient(50% 50% at 90% 100%, rgba(201,162,39,.14), transparent 60%);
        }
        .hero::after {
            content: ""; position: absolute; inset: 0; z-index: -1; opacity: .5;
            background-image:
                linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
            background-size: 48px 48px;
            mask-image: radial-gradient(70% 70% at 50% 40%, #000, transparent 75%);
            -webkit-mask-image: radial-gradient(70% 70% at 50% 40%, #000, transparent 75%);
        }
        /* Navy veil over the hero photo — heavy enough that white text clears
           AA on any frame of the image, still light enough to read as a photo. */
        .hero-veil {
            background:
                linear-gradient(180deg, rgba(13,23,48,.80) 0%, rgba(13,23,48,.88) 55%, rgba(13,23,48,.94) 100%),
                radial-gradient(60% 55% at 15% 0%, rgba(201,162,39,.22), transparent 60%);
        }
        .cta-veil {
            background:
                linear-gradient(120deg, rgba(13,23,48,.93) 0%, rgba(22,35,75,.88) 55%, rgba(13,23,48,.93) 100%),
                radial-gradient(50% 60% at 85% 20%, rgba(201,162,39,.20), transparent 60%);
        }
        .hero-media, .cta-media { filter: saturate(.85); }

        .hero-highlight { color: #C9A227; }

        /* Media panels and mosaic tiles */
        .media-panel img, .mosaic img { transition: transform .5s cubic-bezier(.2,.7,.3,1); }
        .media-panel:hover img, .mosaic figure:hover img { transform: scale(1.05); }
        .mosaic figure::after {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(180deg, transparent 35%, rgba(13,23,48,.85) 100%);
        }
        .mosaic figcaption { z-index: 1; }

        /* Soft tinted band behind alternating sections */
        .band {
            position: relative; isolation: isolate;
            padding: 40px 0; margin: 40px 0; border-radius: 24px;
        }
        .band::before {
            content: ""; position: absolute; inset: 0; z-index: -1; border-radius: 24px;
            background:
                radial-gradient(70% 60% at 50% 0%, rgba(22,35,75,.06), transparent 70%),
                linear-gradient(180deg, #FFFFFF 0%, #F7F8FB 100%);
            border: 1px solid rgba(22,35,75,.07);
        }
        .hero-trust-item svg { color: #C9A227; flex-shrink: 0; }

        /* Cards lift on hover and reveal a gold top edge */
        .stat-card, .svc-card, .step, .ann-card {
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }
        .stat-card::before, .svc-card::before, .step::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px;
            background: linear-gradient(90deg, #C9A227, #A9861E);
            transform: scaleX(0); transform-origin: left; transition: transform .28s ease;
        }
        .ann-panel { transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease; }
        .ann-panel:hover { transform: translateY(-3px); box-shadow: 0 12px 28px -14px rgba(13,23,48,.45); border-color: rgba(201,162,39,.45); }
        .stat-card:hover, .svc-card:hover, .step:hover, .ann-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px -14px rgba(13,23,48,.45);
            border-color: rgba(201,162,39,.45);
        }
        .stat-card:hover::before, .svc-card:hover::before, .step:hover::before { transform: scaleX(1); }
        .svc-icon { transition: transform .22s ease; }
        .svc-card:hover .svc-icon { transform: translateY(-2px) scale(1.06); }

        /* Photo cards: the top edge sits over the image, so keep it clipped */
        .svc-card-photo { overflow: hidden; }
        .svc-photo img { transition: transform .5s cubic-bezier(.2,.7,.3,1); }
        .svc-card-photo:hover .svc-photo img { transform: scale(1.06); }
        .svc-photo::after {
            content: ""; position: absolute; inset: 0;
            background: linear-gradient(180deg, transparent 45%, rgba(13,23,48,.55) 100%);
        }
        .svc-photo-icon { z-index: 1; }

        /* Numbered steps joined by a connector on wide screens */
        .step-track { position: relative; }
        @media (min-width: 1024px) {
            .step-track::before {
                content: ""; position: absolute; top: 38px; left: 12%; right: 12%; height: 2px;
                background: repeating-linear-gradient(90deg, rgba(201,162,39,.5) 0 8px, transparent 8px 16px);
                z-index: 0;
            }
            .step { z-index: 1; }
        }

        .leader-photo { transition: transform .25s ease, box-shadow .25s ease; }
        .leader-card:hover .leader-photo { transform: translateY(-3px) scale(1.04); box-shadow: 0 0 0 3px #C9A227, 0 12px 24px -12px rgba(13,23,48,.5); }

        .link-more svg { transition: transform .2s ease; }
        .link-more:hover { color: #A9861E; }
        .link-more:hover svg { transform: translateX(4px); }

        /* ---------- Footer ---------- */
        /* Generous vertical padding gives these text links a real tap area */
        .footer-list a { display: inline-block; padding: 6px 0; color: inherit; text-decoration: none; transition: color .15s ease; }
        .footer-list a:hover { color: #C9A227; }
        .footer-contact a { padding: 3px 0; }
        .footer-bottom nav a { display: inline-block; padding: 8px 0; color: rgba(255,255,255,.78); text-decoration: none; transition: color .15s ease; }
        .footer-bottom nav a:hover { color: #C9A227; }
        .utility-link { padding: 6px 0; }
        .social-dot { transition: background .18s ease, transform .18s ease, color .18s ease; }
        .social-dot:hover { background: #C9A227; color: #0D1730; transform: translateY(-2px); }

        /* Back-to-top */
        .to-top { transition: opacity .25s ease, transform .25s ease, background .18s ease; transform: translateY(8px); }
        .to-top.show { opacity: 1; pointer-events: auto; transform: translateY(0); }
        .to-top:hover { background: #C9A227; color: #0D1730; }

        /* FAQ accordion */
        .faq-item { transition: border-color .2s ease, box-shadow .2s ease; }
        .faq-item:hover { border-color: rgba(201,162,39,.5); }
        .faq-q .faq-sign { color: #A9861E; font-size: 1.25rem; line-height: 1; transition: transform .22s ease; flex-shrink: 0; }
        .faq-q[aria-expanded="true"] { color: #A9861E; }
        .faq-q[aria-expanded="true"] .faq-sign { transform: rotate(45deg); }

        /* Embedded map fills its rounded frame */
        .map-frame iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }

        /* Leadership panels — navy band behind the portrait, lift on hover */
        .leader-panel { transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease; }
        .leader-panel:hover { transform: translateY(-3px); box-shadow: 0 14px 30px -16px rgba(13,23,48,.5); border-color: rgba(201,162,39,.45); }
        .leader-panel-top::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; height: 62px;
            background-image: linear-gradient(135deg, #0D1730, #1D2F63);
        }
        .leader-panel-photo { transition: transform .25s ease; }
        .leader-panel:hover .leader-panel-photo { transform: scale(1.05); }

        /* Testimonials */
        .quote-card { transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease; }
        .quote-card:hover { transform: translateY(-3px); box-shadow: 0 12px 28px -14px rgba(13,23,48,.45); border-color: rgba(201,162,39,.45); }

        /* Scroll reveal — only arms itself when JS is present, so no-JS still shows everything */
        .js-reveal .reveal { opacity: 0; transform: translateY(18px); }
        .js-reveal .reveal.shown { opacity: 1; transform: none; transition: opacity .6s ease, transform .6s cubic-bezier(.2,.7,.3,1); }

        @media (prefers-reduced-motion: reduce) {
            .js-reveal .reveal { opacity: 1 !important; transform: none !important; }
            *, *::before, *::after { animation-duration: .001ms !important; transition-duration: .001ms !important; }
        }

        /* Sidebar active link */
        .sidebar-link.active { background: #16234B; color: #fff; font-weight: 600; }
        .sidebar-link.active:hover { background: #16234B; }

        /* Fallback for any @media print */
        @media print {
            .js-reveal .reveal { opacity: 1 !important; transform: none !important; }
            .topbar, .site-footer, .no-print { display: none !important; }
            .app-shell, .app-content { display: block !important; }
            .container { margin: 0; max-width: 100%; padding: 0; }
            .card { border: 0; padding: 0; box-shadow: none; }
        }
    </style>
    <script>
        // Arm the scroll-reveal styles only when JS can undo them, and do it
        // before first paint so nothing flashes in and out.
        document.documentElement.classList.add('js-reveal');
    </script>
</head>
<body>
<?php require __DIR__ . '/page_loader.php'; ?>
<a class="skip-link" href="#main-content">Skip to main content</a>

<?php if ($user): ?>
<header class="topbar relative">
    <a class="brand" href="<?= e(APP_URL) ?>/index.php">
        <img src="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>" alt="" class="brand-logo" width="40" height="40" style="width:40px;height:40px;">
        <span class="min-w-0 leading-tight">
            <span class="block truncate"><?= e($siteName) ?></span>
            <span class="brand-sub">Tumba College &middot; Rulindo</span>
        </span>
    </a>

    <button class="sidebar-toggle no-print" type="button" aria-label="Toggle menu" onclick="document.getElementById('sidebar').classList.toggle('-translate-x-full')">&#9776;</button>
    <nav class="topbar-nav">
        <a href="<?= e(APP_URL) ?>/modules/members/profile.php" class="flex items-center gap-2 no-underline">
            <?php if (!empty($user['photo_path'])): ?>
                <img src="<?= e(APP_URL) ?>/<?= e($user['photo_path']) ?>" alt="" class="w-8 h-8 rounded-full object-cover border border-white/40">
            <?php else: ?>
                <span class="w-8 h-8 rounded-full bg-white/15 text-white flex items-center justify-center text-xs font-bold"><?= e(strtoupper(substr($user['full_name'], 0, 1))) ?></span>
            <?php endif; ?>
            <span class="user-chip"><?= e($user['full_name']) ?> <small>(<?= e(str_replace('_', ' ', $user['role_name'])) ?>)</small></span>
        </a>
        <a href="<?= e(APP_URL) ?>/logout.php" class="btn btn-ghost">Logout</a>
    </nav>
</header>

<?php else: ?>

<!-- Utility strip: how to reach the club, above the main navigation -->
<div class="utility-bar no-print">
    <div class="utility-inner">
        <div class="flex items-center gap-3 min-w-0">
            <?php if ($club['email']): ?>
                <a class="utility-link truncate" href="mailto:<?= e($club['email']) ?>">
                    <?= icon('mail', 'w-3.5 h-3.5 text-gold shrink-0') ?>
                    <span class="truncate"><?= e($club['email']) ?></span>
                </a>
            <?php endif; ?>
            <span class="utility-divider"></span>
            <span class="utility-link hidden sm:inline-flex">
                <?= icon('map-pin', 'w-3.5 h-3.5 text-gold shrink-0') ?><?= e($club['location']) ?>
            </span>
        </div>

        <div class="flex items-center gap-3 shrink-0">
            <?php if ($club['phone']): ?>
                <a class="utility-link" href="tel:<?= e(str_replace(' ', '', $club['phone'])) ?>">
                    <?= icon('phone', 'w-3.5 h-3.5 text-gold shrink-0') ?>
                    <span class="hidden sm:inline">CALL US</span>
                    <strong class="text-white font-bold"><?= e($club['phone']) ?></strong>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<header class="mainbar no-print">
    <div class="mainbar-inner">
        <a class="brand-public" href="<?= e(APP_URL) ?>/index.php" aria-label="<?= e($siteName) ?> — home">
            <img src="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>" alt="<?= e($siteName) ?> logo" class="brand-public-logo" width="48" height="48">
            <span class="min-w-0">
                <span class="brand-public-name"><?= e($siteName) ?></span>
                <span class="brand-public-sub">Savings &amp; Credit Club</span>
            </span>
        </a>

        <nav class="public-nav-links" aria-label="Main">
            <?php foreach ($publicNavItems as $item): ?>
                <?php if (!empty($item['children'])): ?>
                    <?php
                    $isActive = false;
                    foreach ($item['children'] as $child) {
                        if ($currentFile === $child['href']) { $isActive = true; break; }
                    }
                    ?>
                    <div class="nav-dropdown">
                        <a class="public-nav-link<?= $isActive ? ' active' : '' ?>" href="<?= e(APP_URL) ?>/<?= e($item['href']) ?>"
                           aria-haspopup="true" aria-expanded="<?= $isActive ? 'true' : 'false' ?>">
                            <?= e($item['label']) ?><span class="caret">&#9662;</span>
                        </a>
                        <div class="nav-dropdown-menu">
                            <div class="nav-dropdown-inner">
                                <?php foreach ($item['children'] as $child): ?>
                                    <a class="nav-dropdown-link<?= $currentFile === $child['href'] ? ' is-current' : '' ?>" href="<?= e(APP_URL) ?>/<?= e($child['href']) ?>">
                                        <span class="nav-dropdown-icon"><?= icon($child['icon'] ?? 'info', 'w-4 h-4') ?></span>
                                        <span class="min-w-0">
                                            <span class="nav-dropdown-label"><?= e($child['label']) ?></span>
                                            <?php if (!empty($child['desc'])): ?>
                                                <span class="nav-dropdown-desc"><?= e($child['desc']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="public-nav-link<?= $currentFile === $item['href'] ? ' active' : '' ?>" href="<?= e(APP_URL) ?>/<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <a href="<?= e(APP_URL) ?>/login.php" class="nav-cta-outline">
                <?= icon('users', 'w-4 h-4') ?> Member Login
            </a>
            <a href="<?= e(APP_URL) ?>/login.php" class="nav-avatar" aria-label="Member login">
                <?= icon('users', 'w-5 h-5') ?>
            </a>
            <a href="<?= e(APP_URL) ?>/membership.php" class="nav-cta-solid">
                <span class="sm:hidden">Join</span><span class="hidden sm:inline">Join the Club</span>
            </a>
            <button class="public-nav-toggle" type="button" aria-label="Open menu"
                    aria-controls="public-nav-panel" aria-expanded="false" data-nav-toggle>
                <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5"/></svg>
            </button>
        </div>
    </div>
</header>

<div class="nav-scrim" id="nav-scrim" data-nav-close></div>

<div id="public-nav-panel" class="public-nav-panel" aria-label="Mobile menu">
    <div class="flex items-center justify-between px-5 h-[64px] border-b border-gray-100">
        <span class="text-xs font-bold uppercase tracking-[0.18em] text-gold-deep">Menu</span>
        <button type="button" class="btn-plain text-primary-dark p-1" aria-label="Close menu" data-nav-close>
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    <?php foreach ($publicNavItems as $item): ?>
        <?php if (!empty($item['children'])): ?>
            <?php
            $isGroupActive = false;
            foreach ($item['children'] as $child) {
                if ($currentFile === $child['href']) { $isGroupActive = true; break; }
            }
            ?>
            <div class="mobile-nav-group">
                <button type="button" class="mobile-nav-group-header<?= $isGroupActive ? ' open' : '' ?>"
                        aria-expanded="<?= $isGroupActive ? 'true' : 'false' ?>"
                        onclick="var o=this.classList.toggle('open'); this.nextElementSibling.classList.toggle('open'); this.setAttribute('aria-expanded',o);">
                    <span class="flex items-center gap-3"><?= icon($item['icon'] ?? 'info', 'w-4 h-4 text-gold-dark') ?><?= e($item['label']) ?></span>
                    <span class="arrow">&#9662;</span>
                </button>
                <div class="mobile-nav-submenu<?= $isGroupActive ? ' open' : '' ?>">
                    <?php foreach ($item['children'] as $child): ?>
                        <a href="<?= e(APP_URL) ?>/<?= e($child['href']) ?>" class="<?= $currentFile === $child['href'] ? 'active-sub' : '' ?>" data-nav-close>
                            <?= icon($child['icon'] ?? 'info', 'w-4 h-4 opacity-60') ?><?= e($child['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <a class="mobile-nav-single<?= $currentFile === $item['href'] ? ' active' : '' ?>" href="<?= e(APP_URL) ?>/<?= e($item['href']) ?>" data-nav-close>
                <?= icon($item['icon'] ?? 'info', 'w-4 h-4 text-gold-dark') ?><?= e($item['label']) ?>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>

    <a class="mobile-nav-single" href="<?= e(APP_URL) ?>/login.php" data-nav-close>
        <?= icon('lock', 'w-4 h-4 text-gold-dark') ?>Member Login
    </a>

    <div class="p-5 mt-auto">
        <a class="nav-cta-solid w-full justify-center" href="<?= e(APP_URL) ?>/membership.php" data-nav-close>
            <?= icon('user-plus', 'w-4 h-4') ?> Join the Club
        </a>

        <div class="mt-5 pt-5 border-t border-gray-100 space-y-2.5 text-sm text-gray-500">
            <?php if ($club['phone']): ?>
                <a class="flex items-center gap-2.5 no-underline" href="tel:<?= e(str_replace(' ', '', $club['phone'])) ?>">
                    <?= icon('phone', 'w-4 h-4 text-gold-dark shrink-0') ?><?= e($club['phone']) ?>
                </a>
            <?php endif; ?>
            <?php if ($club['email']): ?>
                <a class="flex items-center gap-2.5 no-underline min-w-0" href="mailto:<?= e($club['email']) ?>">
                    <?= icon('mail', 'w-4 h-4 text-gold-dark shrink-0') ?><span class="truncate"><?= e($club['email']) ?></span>
                </a>
            <?php endif; ?>
            <div class="flex items-center gap-2.5">
                <?= icon('map-pin', 'w-4 h-4 text-gold-dark shrink-0') ?><?= e($club['location']) ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var bar = document.querySelector('.mainbar') || document.querySelector('.topbar');
    var panel = document.getElementById('public-nav-panel');
    var scrim = document.getElementById('nav-scrim');
    var toggle = document.querySelector('[data-nav-toggle]');

    // Lift the header off the page once the visitor scrolls.
    if (bar) {
        var onScroll = function () { bar.classList.toggle('scrolled', window.scrollY > 8); };
        addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    if (!panel || !toggle) return;

    var open = function () {
        panel.classList.add('open');
        if (scrim) scrim.classList.add('open');
        document.body.classList.add('nav-open');
        toggle.setAttribute('aria-expanded', 'true');
    };

    var close = function () {
        panel.classList.remove('open');
        if (scrim) scrim.classList.remove('open');
        document.body.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', function () {
        panel.classList.contains('open') ? close() : open();
    });

    // The scrim, the close button, and every link inside the drawer dismiss it.
    document.querySelectorAll('[data-nav-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });

    document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') close(); });
    addEventListener('resize', function () { if (innerWidth >= 1024) close(); });
})();
</script>

<?php $flashes = getFlashes(); ?>
<?php if ($user): ?>
<div class="app-shell">
    <aside class="sidebar no-print -translate-x-full md:translate-x-0" id="sidebar">
        <nav>
            <?php foreach ($navItems as $item): ?>
                <?php if (userCanSeeNavItem($user, $item)): ?>
                    <?php $active = $requestPath && str_ends_with($requestPath, $item['href']); ?>
                    <a class="sidebar-link<?= $active ? ' active' : '' ?>" href="<?= e(APP_URL) ?>/<?= e($item['href']) ?>">
                        <span class="sidebar-icon"><?= $item['icon'] ?></span> <?= e($item['label']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main class="app-content" id="main-content">
        <div class="container">
        <?php require __DIR__ . '/flash_toasts.php'; ?>
<?php else: ?>
    <main class="container" id="main-content">
        <?php require __DIR__ . '/flash_toasts.php'; ?>
<?php endif; ?>
