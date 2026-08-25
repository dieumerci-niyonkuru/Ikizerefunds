<?php
// XML sitemap for the public pages. Served at /sitemap.xml via the rewrite in
// .htaccess, and reachable directly at /sitemap.php on servers without rewrites.

require_once __DIR__ . '/config/config.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim(APP_URL, '/');

// [path, change frequency, priority]
$pages = [
    ['',                  'weekly',  '1.0'],
    ['about.php',         'monthly', '0.8'],
    ['membership.php',    'monthly', '0.9'],
    ['leadership.php',    'monthly', '0.7'],
    ['announcements.php', 'weekly',  '0.8'],
    ['feedback.php',      'monthly', '0.5'],
    ['contact.php',       'monthly', '0.7'],
    ['privacy.php',       'yearly',  '0.3'],
    ['terms.php',         'yearly',  '0.3'],
];

$today = date('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($pages as [$path, $freq, $priority]): ?>
    <url>
        <loc><?= htmlspecialchars($base . '/' . $path, ENT_XML1) ?></loc>
        <lastmod><?= $today ?></lastmod>
        <changefreq><?= $freq ?></changefreq>
        <priority><?= $priority ?></priority>
    </url>
<?php endforeach; ?>
</urlset>
