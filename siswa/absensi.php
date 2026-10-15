<?php
/**
 * ============================================================
 *  Siswa Dashboard — Rekap Absensi (View Only)
 * ============================================================
 */
$pageTitle = 'Rekap Kehadiran';
require_once __DIR__ . '/../includes/siswa-header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

// Ambil semua absensi siswa ini, dikelompokkan per kelas ekskul
$stmtAbs = $db->prepare("
    SELECT a.status, sa.tanggal, sa.kelas_ekskul, u.nama AS dicatat_oleh_nama
    FROM absensi a
    JOIN sesi_absensi sa ON a.sesi_absensi_id = sa.id
    JOIN users u ON a.dicatat_oleh = u.id
    WHERE a.siswa_id = ?
    ORDER BY sa.tanggal DESC, sa.id DESC
");
$stmtAbs->execute([$uid]);
$allAbsensi = $stmtAbs->fetchAll();

// Kelompokkan per kelas ekskul
$grouped = [];
foreach ($allAbsensi as $a) {
    $grouped[$a['kelas_ekskul']][] = $a;
}

// Statistik total
$totalHadir = count(array_filter($allAbsensi, fn($a) => $a['status'] === 'hadir'));
$totalIzin  = count(array_filter($allAbsensi, fn($a) => $a['status'] === 'izin'));
$totalSakit = count(array_filter($allAbsensi, fn($a) => $a['status'] === 'sakit'));
$totalAlpa  = count(array_filter($allAbsensi, fn($a) => $a['status'] === 'alpa'));
$totalAll   = count($allAbsensi);
$pctHadir   = $totalAll > 0 ? round($totalHadir / $totalAll * 100) : 0;

// Filter bulan
$filterBulan = $_GET['bulan'] ?? '';
$bulanList   = array_unique(array_map(fn($a) => date('Y-m', strtotime($a['tanggal'])), $allAbsensi));
rsort($bulanList);
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1" style="color: var(--clr-primary-700);">Rekap Kehadiran</h1>
        <p class="text-muted mb-0">Data kehadiran dicatat manual oleh pembimbing setiap sesi ekskul.</p>
    </div>
    <?php if (!empty($bulanList)): ?>
    <form method="GET" class="d-flex gap-2">
        <select name="bulan" class="form-control form-control-sm" onchange="this.form.submit()">
            <option value="">Semua Bulan</option>
            <?php foreach ($bulanList as $b): 
                $label = date('F Y', strtotime($b . '-01'));
            ?>
            <option value="<?= $b ?>" <?= $filterBulan === $b ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php endif; ?>
</div>

<!-- Statistik Summary -->
<div class="stat-grid-5 mb-4">
    <div class="stat-card">
        <div class="stat-icon success"><i class="bi bi-check-circle-fill"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalHadir ?></div>
            <div class="stat-label">Hadir</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="bi bi-info-circle-fill"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalIzin ?></div>
            <div class="stat-label">Izin</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon danger"><i class="bi bi-heartbreak-fill"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalSakit ?></div>
            <div class="stat-label">Sakit</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon dark"><i class="bi bi-x-circle-fill"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $totalAlpa ?></div>
            <div class="stat-label">Alpa</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon primary"><i class="bi bi-percent"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $pctHadir ?>%</div>
            <div class="stat-label">Tingkat Kehadiran</div>
        </div>
    </div>
</div>

<!-- Tabel per kelas ekskul -->
<?php if (empty($allAbsensi)): ?>
<div class="panel"><div class="panel-body text-center py-5 text-muted">
    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
    Belum ada data absensi yang dicatat oleh pembimbing.
</div></div>
<?php else: ?>

<?php foreach ($grouped as $kelas => $rows):
    // Filter per bulan jika ada
    if ($filterBulan) {
        $rows = array_filter($rows, fn($r) => str_starts_with($r['tanggal'], $filterBulan));
    }
    if (empty($rows)) continue;

    $kHadir = count(array_filter($rows, fn($r) => $r['status'] === 'hadir'));
    $kIzin  = count(array_filter($rows, fn($r) => $r['status'] === 'izin'));
    $kSakit = count(array_filter($rows, fn($r) => $r['status'] === 'sakit'));
    $kTotal = count($rows);
    $kPct   = $kTotal > 0 ? round($kHadir / $kTotal * 100) : 0;
?>
<div class="panel mb-4">
    <div class="panel-header">
        <h2 class="panel-title"><i class="bi bi-calendar-check me-2"></i> <?= e($kelas) ?></h2>
        <div class="d-flex gap-2 align-items-center">
            <span class="text-sm text-muted"><?= $kTotal ?> sesi</span>
            <span class="badge badge-<?= $kPct >= 75 ? 'success' : ($kPct >= 50 ? 'warning' : 'danger') ?> px-3">
                <?= $kPct ?>% Hadir
            </span>
        </div>
    </div>
    <div class="panel-body p-0 table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Tanggal Sesi</th>
                    <th class="text-center">Status</th>
                    <th>Dicatat oleh</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= date('l, d M Y', strtotime($r['tanggal'])) ?></td>
                    <td class="text-center">
                        <?php
                        $badgeMap = [
                            'hadir' => ['badge-success', 'check-circle-fill', 'Hadir'],
                            'izin'  => ['badge-warning', 'info-circle-fill',  'Izin'],
                            'sakit' => ['badge-danger',  'heartbreak-fill',   'Sakit'],
                            'alpa'  => ['badge-alpa',    'x-circle-fill',     'Alpa'],
                        ];
                        [$cls, $icon, $lbl] = $badgeMap[$r['status']] ?? ['badge-secondary', 'dash', $r['status']];
                        ?>
                        <span class="badge <?= $cls ?> px-3">
                            <i class="bi bi-<?= $icon ?> me-1"></i> <?= $lbl ?>
                        </span>
                    </td>
                    <td class="text-muted text-sm"><?= e($r['dicatat_oleh_nama']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/siswa-footer.php'; ?>
