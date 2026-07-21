<?php
// General-purpose helpers shared across the app.

function redirect(string $path): void
{
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

// Escapes output for safe HTML rendering (prevents XSS).
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Renders a user's photo, or a colored initial circle if they haven't uploaded one.
// $sizeClass should include both a size (e.g. "w-8 h-8") and a text size (e.g. "text-xs").
function avatarHtml(?string $photoPath, string $name, string $sizeClass = 'w-8 h-8 text-xs'): string
{
    if ($photoPath) {
        return '<img src="' . e(APP_URL) . '/' . e($photoPath) . '" alt="" class="' . e($sizeClass) . ' rounded-full object-cover border border-gray-200">';
    }
    $initial = $name !== '' ? strtoupper(substr($name, 0, 1)) : '?';
    return '<span class="' . e($sizeClass) . ' rounded-full bg-primary-light text-primary flex items-center justify-center font-bold">' . e($initial) . '</span>';
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

function formatMoney(float $amount): string
{
    return number_format($amount, 2);
}

// Writes an entry to the audit_log table for tracking sensitive actions.
function auditLog(int $userId, string $action, string $targetTable, ?int $targetId = null, ?string $details = null): void
{
    try {
        db()->prepare(
            'INSERT INTO audit_log (user_id, action, target_table, target_id, details) VALUES (?, ?, ?, ?, ?)'
        )->execute([$userId, $action, $targetTable, $targetId, $details]);
    } catch (PDOException $e) {
        // Silently fail — audit logging should never break the main action
    }
}

// Renders a status value as a colored pill, e.g. "active" -> green badge.
function statusBadge(?string $status): string
{
    $status = $status ?? '';

    $success = ['active', 'approved', 'present', 'paid', 'sent', 'completed', 'deposit', 'published'];
    $warning = ['pending', 'scheduled', 'partially_paid'];
    $danger  = ['rejected', 'defaulted', 'absent', 'failed', 'late', 'cancelled', 'suspended', 'withdrawn', 'unpaid', 'withdrawal'];

    if (in_array($status, $success, true)) {
        $class = 'badge-success';
    } elseif (in_array($status, $warning, true)) {
        $class = 'badge-warning';
    } elseif (in_array($status, $danger, true)) {
        $class = 'badge-danger';
    } else {
        $class = 'badge-neutral';
    }

    $label = str_replace('_', ' ', $status);
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}
