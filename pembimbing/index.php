<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Home
 * ============================================================
 */
$pageTitle = 'Dashboard Pembimbing';
require_once __DIR__ . '/../includes/pembimbing-header.php';

$db = getDB();
$userId = $_SESSION['user_id'];

// Hitung total siswa aktif yang diajar
$stmtSiswa = $db->prepare("
    SELECT COUNT(DISTINCT s.siswa_id) 
    FROM siswa_kelas_ekskul s
    JOIN users u ON s.siswa_id = u.id
    WHERE u.status = 'approved' AND s.kelas_ekskul IN (
        SELECT value FROM (
            WITH RECURSIVE split(value, str) AS (
                SELECT '', kelas_diampu || ',' FROM pembimbing_detail WHERE user_id = ?
                UNION ALL
                SELECT substr(str, 0, instr(str, ',')), substr(str, instr(str, ',')+1)
                FROM split WHERE str != ''
            )
            SELECT trim(value) as value FROM split WHERE value != ''
        )
    )
");
$stmtSiswa->execute([$userId]);
$totalSiswa = $stmtSiswa->fetchColumn();

// Hitung materi dibuat
$stmtMateri = $db->prepare("SELECT COUNT(id) FROM materi WHERE dibuat_oleh = ?");
$stmtMateri->execute([$userId]);
$totalMateri = $stmtMateri->fetchColumn();

// Hitung tugas pending
$stmtPending = $db->prepare("
    SELECT COUNT(st.id) FROM submission_tugas st
    JOIN tugas t ON st.tugas_id = t.id
    WHERE t.dibuat_oleh = ? AND st.status = 'menunggu'
");
$stmtPending->execute([$userId]);
$pendingVerif = $stmtPending->fetchColumn();

// Ambil jadwal / sesi absensi terbaru (3 sesi terakhir)
$stmtSesi = $db->prepare("
    SELECT s.*, 
           (SELECT COUNT(*) FROM absensi a WHERE a.sesi_absensi_id = s.id AND a.status = 'hadir') as hadir,
           (SELECT COUNT(*) FROM absensi a WHERE a.sesi_absensi_id = s.id) as total_absen
    FROM sesi_absensi s
    WHERE s.pembimbing_id = ?
    ORDER BY s.tanggal DESC, s.id DESC
    LIMIT 3
");
$stmtSesi->execute([$userId]);
$recentSesi = $stmtSesi->fetchAll();

?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Halo, <?= e($_SESSION['nama']) ?>!</h1>
        <p class="text-secondary mb-0">Selamat datang di dashboard Pembimbing.</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-primary-100 text-primary-700">
                <i class="bi bi-people-fill"></i>
            </div>
            <div>
                <div class="stat-value"><?= (int)$totalSiswa ?></div>
                <div class="stat-label">Siswa Aktif</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-warning-100 text-warning-700">
                <i class="bi bi-check2-square"></i>
            </div>
            <div>
                <div class="stat-value"><?= (int)$pendingVerif ?></div>
                <div class="stat-label">Menunggu Verifikasi</div>
            </div>
        </div>
    </div>

    <div class="col-md-4 mb-3">
        <div class="stat-card">
            <div class="stat-icon bg-success-100 text-success-700">
                <i class="bi bi-journal-bookmark-fill"></i>
            </div>
            <div>
                <div class="stat-value"><?= (int)$totalMateri ?></div>
                <div class="stat-label">Materi Dibuat</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Kolom Kiri -->
    <div class="col-md-8 mb-4">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <h2 class="panel-title"><i class="bi bi-calendar-check me-2"></i> Sesi Absensi Terbaru</h2>
                <a href="<?= e(BASE_URL) ?>/pembimbing/absensi.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
            </div>
            <div class="panel-body p-0">
                <?php if (empty($recentSesi)): ?>
                    <div class="p-4 text-center text-muted">Belum ada sesi absensi yang dibuat.</div>
                <?php else: ?>
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Kelas Ekskul</th>
                                <th>Kehadiran</th>
                                <th>Status Sesi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentSesi as $s): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($s['tanggal'])) ?></td>
                                <td><span class="badge badge-secondary"><?= e($s['kelas_ekskul']) ?></span></td>
                                <td>
                                    <?= (int)$s['hadir'] ?> / <?= (int)$s['total_absen'] ?> Hadir
                                </td>
                                <td>
                                    <?php if ($s['status'] === 'buka'): ?>
                                        <span class="badge badge-success">Terbuka</span>
                                    <?php else: ?>
                                        <span class="badge badge-secondary">Ditutup</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Kolom Kanan -->
    <div class="col-md-4 mb-4">
        <div class="panel" style="height: 100%;">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-card-checklist me-2"></i> Jalan Pintas</h2>
            </div>
            <div class="panel-body d-flex flex-column gap-3">
                <a href="<?= e(BASE_URL) ?>/pembimbing/materi-form.php" class="btn btn-outline-primary text-start">
                    <i class="bi bi-plus-circle me-2"></i> Tambah Materi Baru
                </a>
                <a href="<?= e(BASE_URL) ?>/pembimbing/tugas-form.php" class="btn btn-outline-primary text-start">
                    <i class="bi bi-plus-circle me-2"></i> Tambah Tugas Baru
                </a>
                <a href="<?= e(BASE_URL) ?>/pembimbing/absensi.php" class="btn btn-outline-success text-start">
                    <i class="bi bi-journal-check me-2"></i> Buka Sesi Absensi
                </a>
                <a href="<?= e(BASE_URL) ?>/pembimbing/verifikasi.php" class="btn btn-outline-warning text-start">
                    <i class="bi bi-check2-square me-2"></i> Verifikasi Tugas
                    <?php if ($pendingVerif > 0): ?>
                        <span class="badge bg-warning text-dark float-end"><?= $pendingVerif ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pembimbing-footer.php'; ?>
