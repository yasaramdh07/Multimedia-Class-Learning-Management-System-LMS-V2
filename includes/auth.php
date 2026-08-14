<?php
/**
 * ============================================================
 *  Multimedia Class — Auth Helper
 * ============================================================
 *  Semua fungsi terkait sesi, autentikasi, dan otorisasi
 *  dikumpulkan di sini.
 * ============================================================
 */

// Pastikan sesi sudah dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Cek apakah user sudah login.
 * Admin memakai user_id = 0 (virtual), jadi cukup cek isset + role.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

/**
 * Paksa login — redirect ke index.php jika belum login.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

/**
 * Cek role user yang sedang login.
 */
function currentRole(): string
{
    return $_SESSION['role'] ?? '';
}

/**
 * Paksa role tertentu — redirect ke dashboard masing-masing jika salah role.
 *
 * @param string|array $roles Role yang diizinkan ('admin', 'pembimbing', 'siswa', atau array)
 */
function requireRole(string|array $roles): void
{
    requireLogin();

    $allowed = (array) $roles;
    if (!in_array(currentRole(), $allowed, true)) {
        redirectToDashboard();
    }
}

/**
 * Redirect ke dashboard sesuai role.
 */
function redirectToDashboard(): never
{
    $role = currentRole();
    $map  = [
        ROLE_ADMIN      => '/admin/index.php',
        ROLE_PEMBIMBING => '/pembimbing/index.php',
        ROLE_SISWA      => '/siswa/index.php',
    ];

    $target = $map[$role] ?? '/';
    header('Location: ' . BASE_URL . $target);
    exit;
}

/**
 * Buat sesi login untuk user.
 */
function createSession(array $user): void
{
    session_regenerate_id(true); // Cegah session fixation

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['nama']    = $user['nama'];
    $_SESSION['email']   = $user['email'];
}

/**
 * Hancurkan sesi (logout).
 */
function destroySession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Catat atau perbarui activity_log saat login / setiap request.
 * Admin (user_id = 0) tidak dicatat ke tabel activity_log.
 */
function logActivity(int $userId, bool $isNewLogin = false): void
{
    if ($userId <= 0) return; // Skip admin virtual

    $db  = getDB();
    $now = date('Y-m-d H:i:s');

    if ($isNewLogin) {
        $stmt = $db->prepare(
            'INSERT INTO activity_log (user_id, waktu_login, last_seen) VALUES (?, ?, ?)'
        );
        $stmt->execute([$userId, $now, $now]);
    } else {
        $stmt = $db->prepare(
            'UPDATE activity_log SET last_seen = ? WHERE id = (SELECT id FROM activity_log WHERE user_id = ? ORDER BY id DESC LIMIT 1)'
        );
        $stmt->execute([$now, $userId]);
    }
}
