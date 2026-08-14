<?php
/**
 * ============================================================
 *  Admin Dashboard — Header Template
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireRole(ROLE_ADMIN);

$pageTitle = $pageTitle ?? 'Admin Dashboard';

// Konstanta Link Sosmed / Website
define('LINK_WEB_MULTIMEDIA', 'https://multimediayabika.com'); // Ganti dengan link asli
define('LINK_IG_MULTIMEDIA', 'https://instagram.com/multimediayabika'); // Ganti dengan link asli
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= e(BASE_URL) ?>">
    <meta name="user-id" content="<?= e($_SESSION['user_id'] ?? '') ?>">
    <title><?= e($pageTitle) ?></title>

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- App CSS -->
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/main.css">
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/dashboard.css">
    <link rel="icon" type="image/png" href="<?= e(BASE_URL . LOGO_PATH) ?>">
</head>
<body class="dashboard-body">

<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <img src="<?= e(BASE_URL . LOGO_PATH) ?>" alt="Logo">
            <div>
                <div class="brand-name">Multimedia</div>
                <div class="brand-sub">MTs & MA YABIKA</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Menu Admin</div>
            <a href="<?= e(BASE_URL) ?>/admin/index.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/admin/index.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            
            <div class="nav-label mt-4">Manajemen Pengguna</div>
            <a href="<?= e(BASE_URL) ?>/admin/pendaftar.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/admin/pendaftar.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-person-lines-fill"></i> Pendaftar Baru
                <?php
                    // Hitung pendaftar pending
                    $db = getDB();
                    $pendingCount = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
                    if ($pendingCount > 0) {
                        echo '<span class="badge badge-warning ms-auto">'.$pendingCount.'</span>';
                    }
                ?>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/pengajuan.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/admin/pengajuan.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-inbox-fill"></i> Pengajuan
                <?php
                    // Hitung pengajuan pending (lupa password + pengeluaran)
                    $reqCount = $db->query("SELECT COUNT(*) FROM password_reset_requests WHERE status = 'menunggu'")->fetchColumn();
                    $reqCount += $db->query("SELECT COUNT(*) FROM pengajuan_pengeluaran WHERE status_approval_admin = 'menunggu'")->fetchColumn();
                    if ($reqCount > 0) {
                        echo '<span class="badge badge-danger ms-auto">'.$reqCount.'</span>';
                    }
                ?>
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/users.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/admin/users.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Data Pengguna
            </a>

            <div class="nav-label mt-4">Akademik</div>
            <a href="<?= e(BASE_URL) ?>/admin/tahun-ajaran.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/admin/tahun-ajaran.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i> Tahun Ajaran
            </a>
            <a href="<?= e(BASE_URL) ?>/admin/monitoring.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/admin/monitoring.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-fill"></i> Monitoring Progres
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="external-links">
                <a href="<?= LINK_WEB_MULTIMEDIA ?>" target="_blank" title="Website"><i class="bi bi-globe"></i></a>
                <a href="<?= LINK_IG_MULTIMEDIA ?>" target="_blank" title="Instagram"><i class="bi bi-instagram"></i></a>
            </div>
            <div class="user-info">
                <div class="user-avatar"><i class="bi bi-shield-lock-fill"></i></div>
                <div class="user-name">Administrator</div>
            </div>
            <a href="<?= e(BASE_URL) ?>/auth/logout.php" class="btn-logout">
                <i class="bi bi-box-arrow-right"></i> Keluar
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-content">
        <!-- Topbar (Mobile trigger) -->
        <header class="topbar">
            <button id="sidebar-toggle" class="btn-icon"><i class="bi bi-list"></i></button>
            <div class="topbar-brand">Admin Multimedia</div>
        </header>

        <main class="page-content">
