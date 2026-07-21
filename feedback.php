<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_feedback') {
    verifyCsrf();
    $name = trim($_POST['name'] ?? '') ?: null;
    $email = trim($_POST['email'] ?? '') ?: null;
    $message = trim($_POST['message'] ?? '');

    if ($message === '') {
        setFlash('error', 'Please share your idea before submitting.');
    } else {
        $stmt = db()->prepare('INSERT INTO feedback (name, email, message) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, $message]);
        setFlash('success', 'Thank you! Your idea has been shared with our leadership.');
    }
    redirect('feedback.php');
}

require __DIR__ . '/includes/header.php';
?>
<div class="card">
    <h1>Share an Idea</h1>
    <p>Have a suggestion for <?= e($siteName) ?>? We'd love to hear it &mdash; whether
    it's about savings plans, meetings, or anything else that could make the club better.</p>
</div>

<div class="card max-w-lg">
    <form method="post" action="">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="submit_feedback">

        <label for="name">Your Name (optional)</label>
        <input type="text" id="name" name="name">

        <label for="email">Your Email (optional)</label>
        <input type="email" id="email" name="email">

        <label for="message">Your Idea</label>
        <textarea id="message" name="message" rows="5" required></textarea>

        <button type="submit">Submit Idea</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
