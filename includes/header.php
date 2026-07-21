<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
$user = function_exists('currentUser') ? currentUser() : null;

$siteName = APP_NAME;
$siteLogo = null;
if (function_exists('db')) {
    $rows = db()->query('SELECT setting_key, setting_value FROM club_settings')->fetchAll();
    $settings = array_column($rows, 'setting_value', 'setting_key');
    $siteName = $settings['club_name'] ?? APP_NAME;
    $siteLogo = $settings['logo_path'] ?? null;
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

            /* Topbar */
            .topbar { @apply sticky top-0 z-20 flex h-[60px] items-center justify-between gap-4 bg-primary px-3 sm:px-5 text-white shadow; }
            .brand { @apply flex items-center gap-2 text-base sm:text-lg font-bold text-white no-underline; }
            .brand-logo { @apply h-9 w-9 sm:h-11 sm:w-11 rounded-md bg-white p-1 object-contain shadow-sm; }
            .topbar-nav { @apply flex items-center gap-2 sm:gap-4; }
            .topbar-nav a { @apply text-white no-underline; }
            .user-chip { @apply text-sm hidden md:inline; }
            .user-chip small { @apply opacity-80; }
            .sidebar-toggle { @apply inline-block md:hidden bg-transparent border-0 text-2xl text-white cursor-pointer; }

            /* Public tab navigation */
            .public-nav-links { @apply hidden md:flex items-center gap-1; }
            .public-nav-link { @apply px-2 sm:px-3 py-2 rounded-md text-xs sm:text-sm text-white/90 no-underline hover:bg-white/10; }
            .public-nav-link.active { @apply bg-white/15 text-white font-semibold; }
            .public-nav-toggle { @apply inline-block md:hidden bg-transparent border-0 text-2xl text-white cursor-pointer; }
            .public-nav-panel { @apply md:hidden absolute top-[60px] left-0 right-0 bg-primary-dark shadow-lg z-30 flex flex-col p-2; }

            /* App shell */
            .app-shell { @apply flex min-h-[calc(100vh-60px)]; }
            .sidebar {
                @apply w-[230px] shrink-0 bg-white border-r border-gray-200 p-3
                       fixed md:static top-[60px] md:top-0 bottom-0 left-0 z-10
                       transition-transform duration-200
                       overflow-y-auto shadow-lg md:shadow-none;
            }
            .sidebar-link { @apply flex items-center gap-2 rounded-lg px-3 py-2 mb-1 text-sm text-gray-800 no-underline hover:bg-primary-light; }
            .sidebar-link.active { @apply bg-primary text-white font-semibold hover:bg-primary; }
            .sidebar-icon { @apply inline-block w-5 text-center; }
            .app-content { @apply flex-1 min-w-0; }

            /* Layout */
            .container { @apply max-w-5xl mx-auto my-4 sm:my-6 px-3 sm:px-5; }
            .card { @apply bg-white border border-gray-200 rounded-xl p-4 sm:p-6 mb-4 sm:mb-6 shadow-sm; }

            /* Buttons */
            .btn { @apply inline-block bg-primary text-white border-0 rounded-md px-4 sm:px-5 py-2 text-sm cursor-pointer no-underline hover:bg-primary-dark; }
            button:not(.sidebar-toggle):not(.btn-ghost):not(.btn-plain) { @apply inline-block bg-primary text-white border-0 rounded-md px-4 sm:px-5 py-2 text-sm cursor-pointer hover:bg-primary-dark; }
            .btn-ghost { @apply bg-transparent border border-white/60 hover:bg-white/15; }
            .btn-plain { @apply bg-transparent border-0 rounded-none p-0; }

            /* Flash messages */
            .flash { @apply rounded-xl px-4 py-3 mb-4 text-sm; }
            .flash-error { @apply bg-red-50 text-red-800; }
            .flash-success { @apply bg-green-50 text-green-800; }

            /* Toasts */
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

            /* Badges */
            .badge { @apply inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize whitespace-nowrap; }
            .badge-success { @apply bg-green-50 text-green-800; }
            .badge-warning { @apply bg-amber-50 text-amber-800; }
            .badge-danger { @apply bg-red-50 text-red-800; }
            .badge-neutral { @apply bg-gray-100 text-gray-600; }

            /* Forms */
            form label { @apply block mb-1 font-semibold text-sm; }
            form input[type="text"], form input[type="password"], form input[type="email"],
            form input[type="number"], form input[type="date"], form input[type="datetime-local"],
            form input[type="month"], form input[type="file"], form select, form textarea {
                @apply w-full px-3 py-2 mb-4 border border-gray-300 rounded-md text-sm bg-white focus:outline-primary;
            }
            form small { @apply block -mt-3 mb-4 text-gray-500; }

            /* Search / filter bars (GET forms above a table) */
            .filter-bar { @apply flex flex-col sm:flex-row flex-wrap items-stretch sm:items-end gap-3 mb-4; }
            .filter-bar > div { @apply flex flex-col flex-1 min-w-[140px]; }
            .filter-bar label { @apply text-xs font-semibold text-gray-500 mb-1; }
            .filter-bar input[type="text"], .filter-bar input[type="date"], .filter-bar select {
                @apply w-full sm:w-auto sm:min-w-[150px] px-3 py-2 mb-0 border border-gray-300 rounded-md text-sm bg-white;
            }
            .filter-bar button, .filter-bar a.btn { @apply mb-0; }

            /* Tables — horizontally scrollable on mobile */
            .table-wrap { @apply overflow-x-auto -mx-4 sm:mx-0 px-4 sm:px-0; }
            table { @apply w-full border-collapse min-w-[500px]; }
            th, td { @apply text-left px-2 py-2 border-b border-gray-200 text-sm; }
            th { @apply text-gray-500 font-semibold uppercase text-xs tracking-wide whitespace-nowrap; }
            tbody tr:hover { @apply bg-primary-light; }

            /* Grids */
            .dashboard-grid { @apply grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-3; }
            .dashboard-grid .card { @apply mb-0; }
            .stat-grid { @apply grid gap-3 sm:gap-4 mb-6 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4; }
            .stat-grid .stat, .report-summary .stat { @apply bg-white border border-gray-200 rounded-xl p-3 sm:p-4; }
            .stat-grid .stat .label, .report-summary .stat .label { @apply text-xs uppercase tracking-wide text-gray-500; }
            .stat-grid .stat .value, .report-summary .stat .value { @apply text-xl sm:text-2xl font-bold text-primary-dark; }
            .report-summary { @apply grid gap-3 sm:gap-4 mb-4 grid-cols-2 sm:grid-cols-3 lg:grid-cols-4; }

            /* Auth */
            .auth-card { @apply max-w-md mx-auto mt-6 sm:mt-12 px-3 sm:px-0; }

            /* Hero */
            .hero { @apply bg-gradient-to-br from-primary to-primary-dark text-white rounded-xl p-6 sm:p-10 mb-6 text-center shadow; }
            .hero h1 { @apply text-2xl sm:text-3xl md:text-4xl font-bold mb-3; }
            .hero p { @apply text-white/90 max-w-xl mx-auto mb-5 text-sm sm:text-base; }

            /* Page headings */
            h1 { @apply text-xl sm:text-2xl font-bold mb-4; }
            h2 { @apply text-lg sm:text-xl font-bold mb-3; }

            /* Action buttons in table rows */
            td .btn, td button { @apply text-xs px-2 py-1; }

            @media print {
                .topbar, .site-footer, .no-print { display: none !important; }
                .app-shell, .app-content { display: block !important; }
                .container { @apply m-0 max-w-full p-0; }
                .card { @apply border-0 p-0 shadow-none; }
            }
        }
    </style>
</head>
<body>
<?php require __DIR__ . '/page_loader.php'; ?>
<header class="topbar relative">
    <a class="brand" href="<?= e(APP_URL) ?>/index.php">
        <?php if ($siteLogo): ?><img src="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>" alt="" class="brand-logo"><?php endif; ?>
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
                <a class="public-nav-link<?= $currentFile === $item['href'] ? ' active' : '' ?>" href="<?= e(APP_URL) ?>/<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="flex items-center gap-2">
            <a href="<?= e(APP_URL) ?>/login.php" class="btn hidden sm:inline-block">Login</a>
            <button class="public-nav-toggle no-print" type="button" aria-label="Toggle menu" onclick="document.getElementById('public-nav-panel').classList.toggle('hidden')">&#9776;</button>
        </div>
        <div class="public-nav-panel hidden" id="public-nav-panel">
            <?php foreach ($publicNavItems as $item): ?>
                <a class="public-nav-link<?= $currentFile === $item['href'] ? ' active' : '' ?>" href="<?= e(APP_URL) ?>/<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
            <?php endforeach; ?>
            <a class="public-nav-link" href="<?= e(APP_URL) ?>/login.php">Login</a>
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
