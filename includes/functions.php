<?php
function url(string $path = ''): string {
    return BASE_URL . $path;
}

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function formatDate(string $date, string $format = 'd M Y'): string {
    return date($format, strtotime($date));
}

function formatCurrency(?float $amount): string {
    if ($amount === null) return '—';
    return '$' . number_format($amount, 2);
}

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9-]/', '-', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function flash(string $key, string $message = null): ?string {
    sessionStart();
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function csrfToken(): string {
    sessionStart();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    sessionStart();
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid security token. Please go back and try again.');
    }
}

function priorityBadge(string $priority): string {
    $map = [
        'high'   => 'bg-red-100 text-red-700',
        'medium' => 'bg-amber-100 text-amber-700',
        'low'    => 'bg-stone-100 text-stone-600',
    ];
    $class = $map[$priority] ?? $map['medium'];
    return '<span class="text-xs px-2 py-0.5 rounded-full font-medium ' . $class . '">' . ucfirst($priority) . '</span>';
}
