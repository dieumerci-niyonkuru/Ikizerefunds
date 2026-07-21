<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $result = attemptLogin($username, $password);
        if ($result === true) {
            redirect('dashboard.php');
        }
        $error = $result;
    }
}

$rows = db()->query('SELECT setting_key, setting_value FROM club_settings')->fetchAll();
$settings = array_column($rows, 'setting_value', 'setting_key');

require __DIR__ . '/includes/header.php';
?>
<div class="max-w-3xl mx-auto mt-10 mb-6 rounded-xl overflow-hidden shadow-lg grid grid-cols-1 md:grid-cols-2 bg-white">
    <div class="hidden md:flex flex-col justify-center bg-gradient-to-br from-primary to-primary-dark text-white p-10">
        <?php if ($siteLogo): ?>
            <img src="<?= e(APP_URL) ?>/<?= e($siteLogo) ?>" alt="" class="h-20 w-20 mb-6 rounded-lg bg-white p-2 object-contain shadow-md">
        <?php endif; ?>
        <h2 class="text-2xl font-bold mb-3"><?= e($siteName) ?></h2>
        <p class="text-white/90 text-sm">Sign in to manage your savings, apply for loans, track meetings,
        and stay up to date &mdash; all in one place.</p>
    </div>
    <div class="p-8 md:p-10">
        <h2 class="mb-1">Welcome Back</h2>
        <p class="text-gray-500 text-sm mb-5">Log in to your member or admin account.</p>
        <?php if ($error): ?>
            <div class="flash flash-error"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" action="" data-loading-text="Signing you in…">
            <?= csrfField() ?>
            <label for="username">Username</label>
            <input type="text" id="username" name="username" required autofocus>

            <label for="password">Password</label>
            <div class="relative">
                <input type="password" id="password" name="password" required class="pr-14">
                <button type="button" id="toggle-password" tabindex="-1" aria-label="Show password"
                        class="btn-plain absolute right-2 top-1/2 -translate-y-1/2 text-xs font-semibold text-gray-400 hover:text-primary cursor-pointer">Show</button>
            </div>

            <button type="submit" class="w-full">Log In</button>
        </form>
        <p class="text-center text-sm text-gray-500 mt-4">
            <a href="<?= e(APP_URL) ?>/forgot_password.php">Forgot password?</a>
        </p>
        <p class="text-center text-sm text-gray-500 mt-1">
            <a href="<?= e(APP_URL) ?>/index.php">&larr; Back to home</a>
        </p>
    </div>
</div>
<script>
(function () {
    var toggle = document.getElementById('toggle-password');
    var pwd = document.getElementById('password');
    if (toggle && pwd) {
        toggle.addEventListener('click', function () {
            var showing = pwd.type === 'text';
            pwd.type = showing ? 'password' : 'text';
            toggle.textContent = showing ? 'Show' : 'Hide';
            toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
        });
    }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
