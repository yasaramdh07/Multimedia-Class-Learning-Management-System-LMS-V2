<?php
/**
 * ============================================================
 *  Siswa Dashboard — Header Template
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

requireRole(ROLE_SISWA);

$pageTitle = $pageTitle ?? 'Dashboard Siswa';

$db = getDB();

// Ambil data siswa lengkap
$stmtUser = $db->prepare("
    SELECT u.*, sd.kelas, sd.jenjang, sd.wali_kelas, sd.no_wa, sd.nama_ortu, sd.no_hp_ortu, sd.alamat_domisili
    FROM users u
    JOIN siswa_detail sd ON u.id = sd.user_id
    WHERE u.id = ?
");
$stmtUser->execute([$_SESSION['user_id']]);
$siswaInfo = $stmtUser->fetch();

// Ambil daftar kelas ekskul yang diikuti
$stmtEkskul = $db->prepare("SELECT kelas_ekskul FROM siswa_kelas_ekskul WHERE siswa_id = ?");
$stmtEkskul->execute([$_SESSION['user_id']]);
$ekskulList = array_column($stmtEkskul->fetchAll(), 'kelas_ekskul');

// Hitung peringatan yang belum dibaca (semua peringatan dalam 30 hari terakhir)
$stmtPeringatan = $db->prepare("
    SELECT COUNT(*) FROM peringatan 
    WHERE siswa_id = ? AND tanggal >= date('now', '-30 days')
");
$stmtPeringatan->execute([$_SESSION['user_id']]);
$jumlahPeringatan = (int)$stmtPeringatan->fetchColumn();

$foto_profil = $siswaInfo['foto_profil_link'] ?: (BASE_URL . '/assets/img/default-avatar.png');
if (strpos($foto_profil, BASE_URL) === false && strpos($foto_profil, 'http') === false) {
    $foto_profil = BASE_URL . $foto_profil;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= e(BASE_URL) ?>">
    <meta name="user-id" content="<?= e($_SESSION['user_id'] ?? '') ?>">
    <title><?= e($pageTitle) ?> — Multimedia Class</title>

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
            <div class="nav-label">Menu Siswa</div>
            <a href="<?= e(BASE_URL) ?>/siswa/index.php" class="nav-link <?= basename($_SERVER['SCRIPT_NAME']) === 'index.php' && strpos($_SERVER['SCRIPT_NAME'], '/siswa/') !== false ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>

            <div class="nav-label mt-4">Belajar</div>
            <a href="<?= e(BASE_URL) ?>/siswa/materi.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/siswa/materi') !== false ? 'active' : '' ?>">
                <i class="bi bi-journal-bookmark-fill"></i> Materi
            </a>
            <a href="<?= e(BASE_URL) ?>/siswa/tugas.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/siswa/tugas') !== false ? 'active' : '' ?>">
                <i class="bi bi-card-checklist"></i> Tugas & Submit
            </a>

            <div class="nav-label mt-4">Kehadiran</div>
            <a href="<?= e(BASE_URL) ?>/siswa/absensi.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/siswa/absensi') !== false ? 'active' : '' ?>">
                <i class="bi bi-calendar-check-fill"></i> Rekap Absensi
            </a>

            <div class="nav-label mt-4">Lainnya</div>
            <a href="<?= e(BASE_URL) ?>/siswa/nilai.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/siswa/nilai') !== false ? 'active' : '' ?>">
                <i class="bi bi-award-fill"></i> Rekap Nilai
            </a>
            <a href="<?= e(BASE_URL) ?>/siswa/peringatan.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/siswa/peringatan') !== false ? 'active' : '' ?>">
                <i class="bi bi-exclamation-triangle-fill"></i> Peringatan
                <?php if ($jumlahPeringatan > 0): ?>
                    <span class="badge badge-danger ms-auto"><?= $jumlahPeringatan ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= e(BASE_URL) ?>/siswa/profil.php" class="nav-link <?= strpos($_SERVER['SCRIPT_NAME'], '/siswa/profil') !== false ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i> Profil Saya
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="external-links">
                <a href="https://multimediayabika.com" target="_blank" title="Website Multimedia YABIKA"><i class="bi bi-globe2"></i></a>
                <a href="https://instagram.com/multimediayabika" target="_blank" title="Instagram Multimedia YABIKA"><i class="bi bi-instagram"></i></a>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <img src="<?= e($foto_profil) ?>" alt="Foto Profil">
                </div>
                <div class="user-name"><?= e($siswaInfo['nama']) ?></div>
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
            <div class="topbar-brand">Multimedia Class</div>
        </header>

        <main class="page-content">
