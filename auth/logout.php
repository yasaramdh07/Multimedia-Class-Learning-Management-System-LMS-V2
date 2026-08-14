<?php
/**
 * Auth — Logout
 * Hancurkan sesi dan redirect ke halaman login.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) {
    // Update last_seen sebelum logout (hanya untuk non-admin)
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid > 0) {
        logActivity($uid, false);
    }
}

destroySession();

header('Location: ' . BASE_URL . '/?logout=1');
exit;
