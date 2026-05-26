<?php
// ============================================================
//  Geo-Ecomers | Funciones Globales
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// ---- Auth helpers ----

function isLoggedIn(): bool {
    return !empty($_SESSION['user_id']);
}

function isAdmin(): bool {
    return !empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireLogin(string $redirect = '/auth/login.php'): void {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . $redirect);
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    static $user = null;
    if ($user === null) {
        $db   = getDB();
        $stmt = $db->prepare('SELECT id, name, email, role, avatar, phone FROM users WHERE id = ? AND status = "active"');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

// ---- Flash messages ----

function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

function showFlash(): string {
    $f = getFlash();
    if (!$f) return '';
    $cls = match($f['type']) {
        'success' => 'alert-success',
        'danger'  => 'alert-danger',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    return '<div class="alert ' . $cls . ' alert-dismissible fade show" role="alert">'
         . htmlspecialchars($f['msg'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// ---- Sanitize ----

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function sanitize(string $s): string {
    return trim(strip_tags($s));
}

// ---- Money ----

function money(float $amount): string {
    return '$' . number_format($amount, 2);
}

// ---- Cart helpers ----

function cartCount(): int {
    if (!isLoggedIn()) return 0;
    $db   = getDB();
    $stmt = $db->prepare(
        'SELECT COALESCE(SUM(ci.quantity),0) FROM carts c
         JOIN cart_items ci ON ci.cart_id = c.id
         WHERE c.user_id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    return (int) $stmt->fetchColumn();
}

function getOrCreateCart(): int {
    $db   = getDB();
    $uid  = $_SESSION['user_id'];
    $stmt = $db->prepare('SELECT id FROM carts WHERE user_id = ? LIMIT 1');
    $stmt->execute([$uid]);
    $row  = $stmt->fetch();
    if ($row) return $row['id'];
    $db->prepare('INSERT INTO carts (user_id) VALUES (?)')->execute([$uid]);
    return (int) $db->lastInsertId();
}

// ---- Pagination ----

function paginate(int $total, int $perPage, int $current): array {
    $pages = (int) ceil($total / $perPage);
    return [
        'total'   => $total,
        'pages'   => $pages,
        'current' => $current,
        'offset'  => ($current - 1) * $perPage,
        'perPage' => $perPage,
    ];
}

// ---- Slug ----

function slugify(string $text): string {
    $text = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}
