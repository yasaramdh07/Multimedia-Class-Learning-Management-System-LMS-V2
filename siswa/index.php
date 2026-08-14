<?php
/**
 * ============================================================
 *  Siswa Dashboard — Beranda / Home
 * ============================================================
 */
$pageTitle = 'Dashboard Saya';
require_once __DIR__ . '/../includes/siswa-header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

// ── Tahun Ajaran Aktif ────────────────────────────────────────
$ta = $db->query("SELECT * FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1")->fetch();
$taId    = $ta['id']    ?? 0;
$taLabel = $ta ? $ta['tahun'] . ' – Semester ' . $ta['semester'] : 'Belum ada tahun ajaran aktif';

// ── Statistik Progress per ekskul ────────────────────────────
$progressEkskul = [];
foreach ($ekskulList as $eks) {
    // Total materi (disesuaikan target jenjang siswa)
    $stm = $db->prepare("
        SELECT COUNT(*) FROM materi
        WHERE kelas_ekskul = ? AND tahun_ajaran_id = ?
          AND (target = 'Semua Jenjang' OR target = ?)
    ");
    $stm->execute([$eks, $taId, $siswaInfo['jenjang']]);
    $totalMateri = (int)$stm->fetchColumn();

    // Materi sudah diceklis
    $stm2 = $db->prepare("
        SELECT COUNT(*) FROM progress_materi pm
        JOIN materi m ON pm.materi_id = m.id
        WHERE pm.siswa_id = ? AND m.kelas_ekskul = ? AND m.tahun_ajaran_id = ? AND pm.status_ceklis = 1
          AND (m.target = 'Semua Jenjang' OR m.target = ?)
    ");
    $stm2->execute([$uid, $eks, $taId, $siswaInfo['jenjang']]);
    $ceklisMateri = (int)$stm2->fetchColumn();

    // Total tugas (disesuaikan target jenjang)
    $stm3 = $db->prepare("
        SELECT COUNT(*) FROM tugas
        WHERE kelas_ekskul = ? AND tahun_ajaran_id = ?
          AND (target = 'Semua Jenjang' OR target = ?)
    ");
    $stm3->execute([$eks, $taId, $siswaInfo['jenjang']]);
    $totalTugas = (int)$stm3->fetchColumn();

    // Tugas yang sudah diterima (diverifikasi pembimbing)
    $stm4 = $db->prepare("
        SELECT COUNT(*) FROM submission_tugas st
        JOIN tugas t ON st.tugas_id = t.id
        WHERE st.siswa_id = ? AND t.kelas_ekskul = ? AND t.tahun_ajaran_id = ? AND st.status = 'diterima'
          AND (t.target = 'Semua Jenjang' OR t.target = ?)
    ");
    $stm4->execute([$uid, $eks, $taId, $siswaInfo['jenjang']]);
    $tugasDiterima = (int)$stm4->fetchColumn();

    $pctMateri = $totalMateri > 0 ? round($ceklisMateri / $totalMateri * 100) : 0;
    $pctTugas  = $totalTugas  > 0 ? round($tugasDiterima / $totalTugas  * 100) : 0;
    $pctTotal  = round(($pctMateri + $pctTugas) / 2);

    $progressEkskul[] = [
        'nama'           => $eks,
        'materi_ceklis'  => $ceklisMateri,
        'materi_total'   => $totalMateri,
        'tugas_diterima' => $tugasDiterima,
        'tugas_total'    => $totalTugas,
        'pct_materi'     => $pctMateri,
        'pct_tugas'      => $pctTugas,
        'pct_total'      => $pctTotal,
    ];
}

// ── Peringatan terbaru ────────────────────────────────────────
$stmtWarn = $db->prepare("
    SELECT p.alasan, p.tanggal, u.nama AS pembimbing
    FROM peringatan p JOIN users u ON p.pembimbing_id = u.id
    WHERE p.siswa_id = ?
    ORDER BY p.tanggal DESC, p.id DESC
    LIMIT 3
");
$stmtWarn->execute([$uid]);
$peringatanTerbaru = $stmtWarn->fetchAll();

// ── Tugas mendekati deadline (7 hari ke depan, belum dikumpul) ─
$stmtDeadline = $db->prepare("
    SELECT t.* FROM tugas t
    WHERE t.kelas_ekskul IN (
        SELECT kelas_ekskul FROM siswa_kelas_ekskul WHERE siswa_id = ?
    )
    AND t.tahun_ajaran_id = ?
    AND (t.target = 'Semua Jenjang' OR t.target = ?)
    AND t.deadline BETWEEN datetime('now') AND datetime('now', '+7 days')
    AND NOT EXISTS (
        SELECT 1 FROM submission_tugas st WHERE st.tugas_id = t.id AND st.siswa_id = ?
    )
    ORDER BY t.deadline ASC
    LIMIT 5
");
$stmtDeadline->execute([$uid, $taId, $siswaInfo['jenjang'], $uid]);
$tugasDeadline = $stmtDeadline->fetchAll();

// ── Rekap Absensi Total ────────────────────────────────────────
$stmtAbs = $db->prepare("
    SELECT 
        SUM(CASE WHEN a.status = 'hadir' THEN 1 ELSE 0 END) as hadir,
        SUM(CASE WHEN a.status = 'izin'  THEN 1 ELSE 0 END) as izin,
        SUM(CASE WHEN a.status = 'sakit' THEN 1 ELSE 0 END) as sakit,
        COUNT(*) as total
    FROM absensi a
    JOIN sesi_absensi sa ON a.sesi_absensi_id = sa.id
    WHERE a.siswa_id = ?
");
$stmtAbs->execute([$uid]);
$absTotal = $stmtAbs->fetch();
$pctHadir = ($absTotal['total'] > 0) ? round($absTotal['hadir'] / $absTotal['total'] * 100) : 0;

$overallPct = !empty($progressEkskul)
    ? round(array_sum(array_column($progressEkskul, 'pct_total')) / count($progressEkskul))
    : 0;
?>

<!-- ── Greeting ──────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-start mb-5 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1" style="color: var(--clr-primary-700);">
            Halo, <?= e(explode(' ', $siswaInfo['nama'])[0]) ?>! 👋
        </h1>
        <p class="text-muted mb-0">
            <?= e($siswaInfo['jenjang']) ?> – Kelas <?= e($siswaInfo['kelas']) ?> &nbsp;|&nbsp;
            Tahun Ajaran: <strong><?= e($taLabel) ?></strong>
        </p>
    </div>
    <!-- Semester Badge -->
    <?php if ($ta): ?>
    <span class="badge badge-primary px-3 py-2 fs-xs">
        <i class="bi bi-calendar3 me-1"></i> Semester <?= e($ta['semester']) ?> – <?= e($ta['tahun']) ?>
    </span>
    <?php endif; ?>
</div>

<!-- ── Stat Cards ─────────────────────────────────────────── -->
<div class="row mb-4">
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon primary"><i class="bi bi-graph-up-arrow"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= $overallPct ?>%</div>
                <div class="stat-label">Progress Keseluruhan</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= (int)$absTotal['hadir'] ?></div>
                <div class="stat-label">Total Kehadiran</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon warning"><i class="bi bi-clock-fill"></i></div>
            <div class="stat-info">
                <div class="stat-value"><?= count($tugasDeadline) ?></div>
                <div class="stat-label">Tugas Mendesak</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3 mb-3">
        <div class="stat-card">
            <div class="stat-icon <?= $jumlahPeringatan > 0 ? 'danger' : 'success' ?>">
                <i class="bi bi-<?= $jumlahPeringatan > 0 ? 'exclamation-triangle-fill' : 'shield-check' ?>"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= $jumlahPeringatan ?></div>
                <div class="stat-label">Peringatan (30 Hari)</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Progress Bar per Ekskul ────────────────────────────── -->
<div class="panel mb-4">
    <div class="panel-header">
        <h2 class="panel-title"><i class="bi bi-graph-up me-2"></i> Progress Belajar per Kelas Ekskul</h2>
        <a href="<?= e(BASE_URL) ?>/siswa/materi.php" class="btn btn-sm btn-outline-primary">Lihat Materi</a>
    </div>
    <div class="panel-body">
        <?php if (empty($progressEkskul)): ?>
            <p class="text-muted text-center py-3">Kamu belum terdaftar di kelas ekskul apapun.</p>
        <?php else: ?>
            <?php foreach ($progressEkskul as $p): ?>
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-semibold text-primary-700"><?= e($p['nama']) ?></span>
                    <span class="badge badge-<?= $p['pct_total'] >= 75 ? 'success' : ($p['pct_total'] >= 40 ? 'warning' : 'danger') ?> px-3">
                        <?= $p['pct_total'] ?>%
                    </span>
                </div>
                <!-- Progress bar total -->
                <div class="progress mb-2" style="height: 10px; background: var(--clr-surface-2); border-radius: 99px; overflow: hidden;">
                    <div class="progress-bar" style="width: <?= $p['pct_total'] ?>%; background: linear-gradient(90deg, var(--clr-primary-400), var(--clr-primary-600)); border-radius: 99px; transition: width 0.8s ease;"></div>
                </div>
                <div class="d-flex gap-4 text-xs text-muted">
                    <span><i class="bi bi-book-half me-1"></i>Materi: <?= $p['materi_ceklis'] ?>/<?= $p['materi_total'] ?> (<?= $p['pct_materi'] ?>%)</span>
                    <span><i class="bi bi-check2-square me-1"></i>Tugas: <?= $p['tugas_diterima'] ?>/<?= $p['tugas_total'] ?> (<?= $p['pct_tugas'] ?>%)</span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── 2-Column Row ───────────────────────────────────────── -->
<div class="row">
    <!-- Tugas Mendesak -->
    <div class="col-md-7 mb-4">
        <div class="panel h-100">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-alarm-fill me-2 text-warning"></i> Tugas Mendekati Deadline</h2>
                <a href="<?= e(BASE_URL) ?>/siswa/tugas.php" class="btn btn-sm btn-outline-primary">Semua Tugas</a>
            </div>
            <div class="panel-body p-0">
                <?php if (empty($tugasDeadline)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                        Semua tugas sudah dikumpulkan!
                    </div>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($tugasDeadline as $t):
                            $hariSisa = ceil((strtotime($t['deadline']) - time()) / 86400);
                        ?>
                        <li class="d-flex align-items-center gap-3 px-4 py-3 border-bottom">
                            <div class="flex-shrink-0 text-center" style="width: 44px;">
                                <div class="fw-bold fs-4 text-<?= $hariSisa <= 2 ? 'danger' : 'warning' ?> lh-1"><?= $hariSisa ?></div>
                                <div class="text-xs text-muted">hari</div>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-semibold text-primary-700"><?= e($t['judul']) ?></div>
                                <div class="text-xs text-muted"><span class="badge badge-secondary"><?= e($t['kelas_ekskul']) ?></span> · <?= date('d M Y, H:i', strtotime($t['deadline'])) ?></div>
                            </div>
                            <a href="<?= e(BASE_URL) ?>/siswa/tugas.php?submit=<?= $t['id'] ?>" class="btn btn-sm btn-primary flex-shrink-0">
                                <i class="bi bi-upload me-1"></i> Kumpul
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Peringatan Terbaru -->
    <div class="col-md-5 mb-4">
        <div class="panel h-100">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-exclamation-triangle-fill me-2 text-danger"></i> Peringatan</h2>
                <?php if ($jumlahPeringatan > 0): ?>
                <a href="<?= e(BASE_URL) ?>/siswa/peringatan.php" class="btn btn-sm btn-outline-danger">Lihat Semua</a>
                <?php endif; ?>
            </div>
            <div class="panel-body p-0">
                <?php if (empty($peringatanTerbaru)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-shield-check text-success fs-3 d-block mb-2"></i>
                        Tidak ada peringatan. Pertahankan!
                    </div>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($peringatanTerbaru as $w): ?>
                        <li class="px-4 py-3 border-bottom">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="badge badge-danger">Peringatan</span>
                                <span class="text-xs text-muted"><?= date('d M Y', strtotime($w['tanggal'])) ?></span>
                            </div>
                            <p class="mb-1 text-sm"><?= e(truncate($w['alasan'], 80)) ?></p>
                            <div class="text-xs text-muted">oleh: <?= e($w['pembimbing']) ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Absensi Ring Chart (simple visual) ─────────────────── -->
<div class="panel mb-4">
    <div class="panel-header">
        <h2 class="panel-title"><i class="bi bi-pie-chart-fill me-2"></i> Ringkasan Kehadiran</h2>
        <a href="<?= e(BASE_URL) ?>/siswa/absensi.php" class="btn btn-sm btn-outline-primary">Detail Rekap</a>
    </div>
    <div class="panel-body">
        <div class="row align-items-center">
            <div class="col-md-4 text-center mb-3 mb-md-0">
                <!-- Simple circular gauge using CSS conic-gradient -->
                <div style="
                    width: 120px; height: 120px; border-radius: 50%;
                    background: conic-gradient(
                        #22c55e 0% <?= $pctHadir ?>%,
                        #f59e0b <?= $pctHadir ?>% <?= $pctHadir + ($absTotal['total'] > 0 ? round($absTotal['izin'] / $absTotal['total'] * 100) : 0) ?>%,
                        #ef4444 <?= $pctHadir + ($absTotal['total'] > 0 ? round($absTotal['izin'] / $absTotal['total'] * 100) : 0) ?>% 100%
                    );
                    margin: 0 auto; display: flex; align-items: center; justify-content: center; position: relative;">
                    <div style="width: 80px; height: 80px; background: white; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                        <span style="font-size: 1.4rem; font-weight: 700; color: var(--clr-primary-700);"><?= $pctHadir ?>%</span>
                        <span style="font-size: 0.65rem; color: #888;">Hadir</span>
                    </div>
                </div>
                <div class="text-sm text-muted mt-2">Total <?= $absTotal['total'] ?> sesi tercatat</div>
            </div>
            <div class="col-md-8">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <span style="width: 14px; height: 14px; background: #22c55e; border-radius: 4px; flex-shrink: 0;"></span>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-sm fw-semibold">Hadir</span>
                                <span class="text-sm fw-bold text-success"><?= (int)$absTotal['hadir'] ?> kali</span>
                            </div>
                            <div style="height: 8px; background: #f0f0f0; border-radius: 99px; overflow: hidden;">
                                <div style="width: <?= $pctHadir ?>%; height: 100%; background: #22c55e; border-radius: 99px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span style="width: 14px; height: 14px; background: #f59e0b; border-radius: 4px; flex-shrink: 0;"></span>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-sm fw-semibold">Izin</span>
                                <span class="text-sm fw-bold text-warning"><?= (int)$absTotal['izin'] ?> kali</span>
                            </div>
                            <div style="height: 8px; background: #f0f0f0; border-radius: 99px; overflow: hidden;">
                                <div style="width: <?= $absTotal['total'] > 0 ? round($absTotal['izin']/$absTotal['total']*100) : 0 ?>%; height: 100%; background: #f59e0b; border-radius: 99px;"></div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <span style="width: 14px; height: 14px; background: #ef4444; border-radius: 4px; flex-shrink: 0;"></span>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-sm fw-semibold">Sakit</span>
                                <span class="text-sm fw-bold text-danger"><?= (int)$absTotal['sakit'] ?> kali</span>
                            </div>
                            <div style="height: 8px; background: #f0f0f0; border-radius: 99px; overflow: hidden;">
                                <div style="width: <?= $absTotal['total'] > 0 ? round($absTotal['sakit']/$absTotal['total']*100) : 0 ?>%; height: 100%; background: #ef4444; border-radius: 99px;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/siswa-footer.php'; ?>
