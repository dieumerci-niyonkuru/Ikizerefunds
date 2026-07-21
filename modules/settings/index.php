<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

requirePermission('settings.manage');
$user = currentUser();

const TEXT_SETTINGS = ['club_name', 'club_email', 'club_phone', 'club_address', 'default_interest_rate', 'currency'];
const MAX_LOGO_BYTES = 2 * 1024 * 1024; // 2 MB
const ALLOWED_LOGO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_settings') {
    verifyCsrf();

    $upsert = db()->prepare(
        'INSERT INTO club_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
    );

    foreach (TEXT_SETTINGS as $key) {
        $value = trim($_POST[$key] ?? '');
        $upsert->execute([$key, $value, $user['id']]);
    }

    if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo'];
        $mime = mime_content_type($file['tmp_name']);

        if ($file['size'] > MAX_LOGO_BYTES) {
            setFlash('error', 'Logo file is too large (max 2 MB). Other settings were saved.');
        } elseif (!isset(ALLOWED_LOGO_TYPES[$mime])) {
            setFlash('error', 'Logo must be a PNG, JPEG, or WEBP image. Other settings were saved.');
        } else {
            $ext = ALLOWED_LOGO_TYPES[$mime];
            $filename = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
            $destination = __DIR__ . '/../../assets/uploads/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $upsert->execute(['logo_path', 'assets/uploads/' . $filename, $user['id']]);
            } else {
                setFlash('error', 'Could not save the uploaded logo. Other settings were saved.');
            }
        }
    }

    setFlash('success', 'Club settings updated.');
    redirect('modules/settings/index.php');
}

$rows = db()->query('SELECT setting_key, setting_value FROM club_settings')->fetchAll();
$settings = array_column($rows, 'setting_value', 'setting_key');

require __DIR__ . '/../../includes/header.php';
?>
<div class="card max-w-xl">
    <h1>Club Settings</h1>
    <form method="post" action="" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="save_settings">

        <label for="club_name">Club Name</label>
        <input type="text" id="club_name" name="club_name" value="<?= e($settings['club_name'] ?? APP_NAME) ?>">

        <label for="club_email">Club Email</label>
        <input type="email" id="club_email" name="club_email" value="<?= e($settings['club_email'] ?? '') ?>">

        <label for="club_phone">Club Phone</label>
        <input type="text" id="club_phone" name="club_phone" value="<?= e($settings['club_phone'] ?? '') ?>">

        <label for="club_address">Club Address</label>
        <input type="text" id="club_address" name="club_address" value="<?= e($settings['club_address'] ?? '') ?>">

        <label for="default_interest_rate">Default Interest Rate (%)</label>
        <input type="text" id="default_interest_rate" name="default_interest_rate" value="<?= e($settings['default_interest_rate'] ?? '5') ?>">
        <small>Reference value only — each loan type has its own rate under Loans.</small>

        <label for="currency">Currency</label>
        <input type="text" id="currency" name="currency" value="<?= e($settings['currency'] ?? 'RWF') ?>">

        <label for="logo">Club Logo</label>
        <?php if (!empty($settings['logo_path'])): ?>
            <p><img src="<?= e(APP_URL) ?>/<?= e($settings['logo_path']) ?>" alt="Club logo" class="max-h-[60px]"></p>
        <?php endif; ?>
        <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">

        <button type="submit" class="mt-4">Save Settings</button>
    </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
