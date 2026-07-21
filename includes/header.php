<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

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

$navItems = $user ? require __DIR__ . '/nav.php' : [];
$publicNavItems = $user ? [] : require __DIR__ . '/public_nav.php';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$currentFile = $requestPath ? basename($requestPath) : 'index.php';
if ($currentFile === '' || $currentFile === '/') {
    $currentFile = 'index.php';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($siteName) ?> &mdash; a savings and credit club management system: members, savings, loans, meetings, and reports.">
    <?php if ($siteLogo): ?><link rel="icon" href="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>"><?php endif; ?>
    <title><?= e($siteName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#16234B', dark: '#0D1730', light: '#E9EBF3' },
                        gold: { DEFAULT: '#C9A227', dark: '#A9861E', light: '#F7EFD6' },
                    },
                },
            },
        };
    </script>
    <style type="text/tailwindcss">
        @layer components {
            body { @apply bg-gray-100 text-gray-900; }

            .topbar { @apply sticky top-0 z-20 flex h-[60px] items-center justify-between gap-2 sm:gap-4 bg-primary px-2 sm:px-5 text-white shadow; }
            .brand { @apply flex items-center gap-2 text-sm sm:text-lg font-bold text-white no-underline min-w-0 shrink-0; }
            .brand-logo { @apply h-8 w-8 sm:h-10 sm:w-10 rounded-md bg-white p-0.5 object-contain shadow-sm; }
            .topbar-nav { @apply flex items-center gap-1 sm:gap-4 shrink-0; }
            .sidebar-toggle { @apply inline-block md:hidden bg-transparent border-0 text-2xl text-white cursor-pointer; }

            .public-nav-links { @apply hidden md:flex items-center gap-0; }
            .public-nav-link { @apply px-3 py-2 rounded-md text-sm font-medium text-white/90 no-underline hover:bg-white/10 transition-colors; }
            .public-nav-toggle { @apply md:hidden bg-transparent border-0 text-2xl text-white cursor-pointer leading-none p-1 shrink-0; }
            .public-nav-panel { @apply md:hidden absolute top-full left-0 right-0 bg-primary-dark shadow-xl z-30 flex flex-col max-h-[70vh] overflow-y-auto; }

            .mobile-nav-group-header { @apply flex items-center justify-between w-full px-4 py-3 text-base font-medium text-white cursor-pointer bg-transparent border-0 text-left hover:bg-white/10 transition-colors; }
            .mobile-nav-single { @apply block px-4 py-3 text-base text-white/90 no-underline border-b border-white/5 hover:bg-white/10 transition-colors; }

            .nav-dropdown { @apply relative; }
            .nav-dropdown-menu { @apply absolute top-full left-0 bg-white rounded-lg shadow-xl border border-gray-200 py-1 min-w-[200px] hidden z-40; }
            .nav-dropdown-link { @apply block px-4 py-2.5 text-sm text-gray-700 no-underline hover:bg-primary-light; }

            .app-shell { @apply flex min-h-[calc(100vh-60px)]; }
            .sidebar { @apply w-[230px] shrink-0 bg-white border-r border-gray-200 p-3 fixed md:static top-[60px] md:top-0 bottom-0 left-0 z-10 transition-transform duration-200 overflow-y-auto shadow-lg md:shadow-none; }
            .sidebar-link { @apply flex items-center gap-2 rounded-lg px-3 py-2 mb-1 text-sm text-gray-800 no-underline hover:bg-primary-light; }
            .sidebar-link.active { @apply bg-primary text-white font-semibold; }
            .sidebar-icon { @apply inline-block w-5 text-center; }
            .app-content { @apply flex-1 min-w-0; }

            .container { @apply max-w-5xl mx-auto my-4 sm:my-6 px-3 sm:px-5; }
            .card { @apply bg-white border border-gray-200 rounded-xl p-4 sm:p-6 mb-4 sm:mb-6 shadow-sm; }

            .btn { @apply inline-block bg-primary text-white border-0 rounded-md px-4 sm:px-5 py-2 text-sm cursor-pointer no-underline hover:bg-primary-dark; }
            button:not(.sidebar-toggle):not(.btn-ghost):not(.btn-plain) { @apply inline-block bg-primary text-white border-0 rounded-md px-4 sm:px-5 py-2 text-sm cursor-pointer hover:bg-primary-dark; }
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
                @apply w-full px-3 py-2 mb-4 border border-gray-300 rounded-md text-sm bg-white focus:outline-primary;
            }
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

            .hero { @apply bg-gradient-to-br from-primary to-primary-dark text-white rounded-xl p-6 sm:p-10 mb-6 text-center shadow; }
            .hero h1 { @apply text-2xl sm:text-3xl md:text-4xl font-bold mb-3; }
            .hero p { @apply text-white/90 max-w-xl mx-auto mb-5 text-sm sm:text-base; }

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

        /* Public nav links — desktop */
        .public-nav-link.active { background: rgba(255,255,255,0.15); color: #fff; font-weight: 600; }
        .nav-dropdown:hover .nav-dropdown-menu { display: block; }

        /* Mobile nav panel */
        #public-nav-panel { display: none; }
        #public-nav-panel.open { display: flex; }

        /* Mobile accordion */
        .mobile-nav-group-header .arrow { color: rgba(255,255,255,0.5); font-size: 0.75rem; transition: transform 0.2s; }
        .mobile-nav-group-header.open .arrow { transform: rotate(180deg); }
        .mobile-nav-submenu { display: none; background: rgba(255,255,255,0.05); }
        .mobile-nav-submenu.open { display: block; }
        .mobile-nav-submenu a { display: block; padding: 12px 32px 12px 32px; font-size: 0.875rem; color: rgba(255,255,255,0.8); text-decoration: none; border-bottom: 1px solid rgba(255,255,255,0.05); transition: background 0.15s; }
        .mobile-nav-submenu a:last-child { border-bottom: none; }
        .mobile-nav-submenu a:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .mobile-nav-submenu a.active-sub { font-weight: 600; color: #fff; }
        .mobile-nav-single.active { background: rgba(255,255,255,0.15); color: #fff; font-weight: 600; }

        /* Sidebar active link */
        .sidebar-link.active { background: #16234B; color: #fff; font-weight: 600; }
        .sidebar-link.active:hover { background: #16234B; }

        /* Fallback for any @media print */
        @media print {
            .topbar, .site-footer, .no-print { display: none !important; }
            .app-shell, .app-content { display: block !important; }
            .container { margin: 0; max-width: 100%; padding: 0; }
            .card { border: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/page_loader.php'; ?>
<header class="topbar relative">
    <a class="brand" href="<?= e(APP_URL) ?>/index.php">
        <img src="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>" alt="" class="brand-logo" width="40" height="40" style="width:40px;height:40px;">
        <span><?= e($siteName) ?></span>
    </a>

    <?php if ($user): ?>
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
    <?php else: ?>
        <nav class="public-nav-links">
            <?php foreach ($publicNavItems as $item): ?>
                <?php if (!empty($item['children'])): ?>
                    <?php
                    $isActive = false;
                    foreach ($item['children'] as $child) {
                        if ($currentFile === $child['href']) { $isActive = true; break; }
                    }
                    ?>
                    <div class="nav-dropdown">
                        <a class="public-nav-link<?= $isActive ? ' active' : '' ?>" href="<?= e(APP_URL) ?>/<?= e($item['href']) ?>"><?= e($item['label']) ?> &#9662;</a>
                        <div class="nav-dropdown-menu">
                            <?php foreach ($item['children'] as $child): ?>
                                <a class="nav-dropdown-link<?= $currentFile === $child['href'] ? ' font-semibold bg-primary-light' : '' ?>" href="<?= e(APP_URL) ?>/<?= e($child['href']) ?>"><?= e($child['label']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="public-nav-link<?= $currentFile === $item['href'] ? ' active' : '' ?>" href="<?= e(APP_URL) ?>/<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <div class="flex items-center gap-2 shrink-0">
            <a href="<?= e(APP_URL) ?>/login.php" class="btn hidden sm:inline-block">Login</a>
            <button class="public-nav-toggle no-print" type="button" aria-label="Toggle menu" onclick="document.getElementById('public-nav-panel').classList.toggle('open')">&#9776;</button>
        </div>
        <div id="public-nav-panel" class="public-nav-panel">
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
                                onclick="this.classList.toggle('open'); this.nextElementSibling.classList.toggle('open');">
                            <span><?= e($item['label']) ?></span>
                            <span class="arrow">&#9662;</span>
                        </button>
                        <div class="mobile-nav-submenu<?= $isGroupActive ? ' open' : '' ?>">
                            <?php foreach ($item['children'] as $child): ?>
                                <a href="<?= e(APP_URL) ?>/<?= e($child['href']) ?>" class="<?= $currentFile === $child['href'] ? 'active-sub' : '' ?>" onclick="document.getElementById('public-nav-panel').classList.remove('open')"><?= e($child['label']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <a class="mobile-nav-single<?= $currentFile === $item['href'] ? ' active' : '' ?>" href="<?= e(APP_URL) ?>/<?= e($item['href']) ?>" onclick="document.getElementById('public-nav-panel').classList.remove('open')"><?= e($item['label']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a class="mobile-nav-single sm:hidden" href="<?= e(APP_URL) ?>/login.php" onclick="document.getElementById('public-nav-panel').classList.remove('open')">Login</a>
        </div>
    <?php endif; ?>
</header>

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
    <main class="app-content">
        <div class="container">
        <?php require __DIR__ . '/flash_toasts.php'; ?>
<?php else: ?>
    <main class="container">
        <?php require __DIR__ . '/flash_toasts.php'; ?>
<?php endif; ?>
