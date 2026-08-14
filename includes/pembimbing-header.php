<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Header Template
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireRole(ROLE_PEMBIMBING);

$pageTitle = $pageTitle ?? 'Pembimbing Dashboard';

// Konstanta Link Sosmed / Website
define('LINK_WEB_MULTIMEDIA', 'https://multimediayabika.com');
define('LINK_IG_MULTIMEDIA', 'https://instagram.com/multimediayabika');

// Ambil info pembimbing
$db = getDB();
$stmt = $db->prepare("SELECT p.*, u.nama, u.foto_profil_link FROM pembimbing_detail p JOIN users u ON p.user_id = u.id WHERE p.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$pembimbing_info = $stmt->fetch();

$foto_profil = $pembimbing_info['foto_profil_link'] ?: (BASE_URL . '/assets/img/default-avatar.png');
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
            <div class="nav-label">Menu Pembimbing</div>
            <a href="<?= e(BASE_URL) ?>/pembimbing/index.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/pembimbing/index.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
            <a href="<?= e(BASE_URL) ?>/pembimbing/siswa.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/pembimbing/siswa.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Siswa Bimbingan
            </a>
            
            <div class="nav-label mt-4">Akademik</div>
            <a href="<?= e(BASE_URL) ?>/pembimbing/materi.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/pembimbing/materi.php') !== false || strpos($_SERVER['SCRIPT_NAME'], '/pembimbing/materi-form.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-journal-bookmark-fill"></i> Kelola Materi
            </a>
            <a href="<?= e(BASE_URL) ?>/pembimbing/tugas.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/pembimbing/tugas.php') !== false || strpos($_SERVER['SCRIPT_NAME'], '/pembimbing/tugas-form.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-card-checklist"></i> Kelola Tugas
            </a>
            <a href="<?= e(BASE_URL) ?>/pembimbing/verifikasi.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/pembimbing/verifikasi.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-check2-square"></i> Verifikasi Tugas
                <?php
                    // Hitung tugas menunggu
                    $pendingTugas = $db->prepare("
                        SELECT COUNT(st.id) FROM submission_tugas st
                        JOIN tugas t ON st.tugas_id = t.id
                        WHERE t.dibuat_oleh = ? AND st.status = 'menunggu'
                    ");
                    $pendingTugas->execute([$_SESSION['user_id']]);
                    $ptCount = $pendingTugas->fetchColumn();
                    if ($ptCount > 0) {
                        echo '<span class="badge badge-warning ms-auto">'.$ptCount.'</span>';
                    }
                ?>
            </a>

            <div class="nav-label mt-4">Administrasi</div>
            <a href="<?= e(BASE_URL) ?>/pembimbing/absensi.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/pembimbing/absensi') !== false ? 'active' : '' ?>">
                <i class="bi bi-calendar-check-fill"></i> Absensi Kelas
            </a>
            <a href="<?= e(BASE_URL) ?>/pembimbing/rekap.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/pembimbing/rekap.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-bar-graph-fill"></i> Rekap & Nilai
            </a>
            <a href="<?= e(BASE_URL) ?>/pembimbing/peringatan.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/pembimbing/peringatan.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-exclamation-triangle-fill"></i> Peringatan
            </a>

            <div class="nav-label mt-4">Akun</div>
            <a href="<?= e(BASE_URL) ?>/pembimbing/profil.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/pembimbing/profil.php') !== false ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i> Profil Saya
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar" style="overflow:hidden; border-radius:50%;">
                    <img src="<?= e($foto_profil) ?>" style="width:100%;height:100%;object-fit:cover;" alt="Avatar">
                </div>
                <div class="user-name" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                    <?= e($_SESSION['nama']) ?>
                </div>
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
            <div class="topbar-brand">Pembimbing Dashboard</div>
        </header>

        <main class="page-content">
