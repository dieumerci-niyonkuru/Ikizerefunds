<?php
// Renders flash messages as auto-dismissing toasts (top-right) instead of an
// inline banner. Expects $flashes to already be set by the caller (header.php
// reads getFlashes() once, since it empties the session on read).
$flashes = $flashes ?? [];
if ($flashes):
?>
<div class="toast-stack no-print" id="toast-stack">
    <?php foreach ($flashes as $flash): ?>
        <div class="toast toast-<?= e($flash['type']) ?>" role="status">
            <span class="toast-icon"><?= $flash['type'] === 'success' ? '&#10003;' : '&#9888;' ?></span>
            <span class="toast-message"><?= e($flash['message']) ?></span>
            <button type="button" class="btn-plain toast-close" aria-label="Dismiss" onclick="this.closest('.toast').remove()">&times;</button>
        </div>
    <?php endforeach; ?>
</div>
<script>
(function () {
    Array.prototype.forEach.call(document.querySelectorAll('#toast-stack .toast'), function (toast, i) {
        setTimeout(function () {
            toast.classList.add('toast-hide');
            setTimeout(function () { toast.remove(); }, 300);
        }, 5000 + i * 300);
    });
})();
</script>
<?php endif; ?>
