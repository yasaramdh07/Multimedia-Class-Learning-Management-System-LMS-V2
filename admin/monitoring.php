<?php
/**
 * ============================================================
 *  Admin — Monitoring Progres (Read-Only)
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);
$pageTitle = 'Monitoring Progres — ' . APP_NAME;

$db = getDB();

// Ambil Tahun Ajaran Aktif
$taAktif = $db->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1")->fetch();
$taId = $taAktif ? $taAktif['id'] : 0;

// Filter Ekskul
$filterEkskul = $_GET['ekskul'] ?? '';

$filterJenjang = $_GET['jenjang'] ?? '';
$filterKelasSiswa = $_GET['kelas_siswa'] ?? '';

// Query untuk mengambil daftar siswa, beserta kelas, ekskul, dan summary progress/nilai
// Karena ini view admin, kita berikan overview umum per siswa
$sql = "
    SELECT s.id, s.nama, d.kelas, d.jenjang,
           (SELECT GROUP_CONCAT(kelas_ekskul, ', ') FROM siswa_kelas_ekskul WHERE siswa_id = s.id) AS ekskul_list
    FROM users s
    JOIN siswa_detail d ON s.id = d.user_id
    WHERE s.role = 'siswa' AND s.status = 'approved'
";
$params = [];

if ($filterEkskul && in_array($filterEkskul, KELAS_EKSKUL)) {
    $sql .= " AND EXISTS (SELECT 1 FROM siswa_kelas_ekskul WHERE siswa_id = s.id AND kelas_ekskul = ?)";
    $params[] = $filterEkskul;
}

if ($filterJenjang !== '') {
    $sql .= " AND d.jenjang = ?";
    $params[] = $filterJenjang;
}

if ($filterKelasSiswa !== '') {
    $sql .= " AND d.kelas = ?";
    $params[] = $filterKelasSiswa;
}

$sql .= " ORDER BY d.jenjang DESC, d.kelas ASC, s.nama ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$siswaList = $stmt->fetchAll();

// Jika ada TA Aktif, ambil data nilai/progress untuk TA tersebut
$nilaiMap = [];
if ($taId > 0 && !empty($siswaList)) {
    $nStmt = $db->prepare("SELECT siswa_id, nilai_kehadiran, nilai_progress, nilai_total FROM nilai WHERE tahun_ajaran_id = ?");
    $nStmt->execute([$taId]);
    while ($row = $nStmt->fetch()) {
        $nilaiMap[$row['siswa_id']] = $row;
    }
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Monitoring Siswa</h1>
        <p class="text-secondary mb-0">
            Tahun Ajaran Aktif: <strong><?= $taAktif ? e($taAktif['tahun'] . ' - ' . $taAktif['semester']) : 'Tidak Ada' ?></strong>
        </p>
    </div>
</div>

<div class="alert alert-info mb-4">
    <i class="bi bi-info-circle-fill"></i> <strong>Mode Read-Only:</strong> Admin hanya dapat melihat rekap progres dan nilai. Pengisian absensi dan pemberian nilai adalah wewenang Pembimbing di masing-masing kelas.
</div>

<div class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <h2 class="panel-title"><i class="bi bi-bar-chart-line"></i> Rekap Progres Siswa</h2>
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
            <input type="text" class="form-control form-control-sm table-search" placeholder="Cari siswa..." style="max-width: 150px;">
            <select name="ekskul" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                <option value="">-- Semua Ekskul --</option>
                <?php foreach (KELAS_EKSKUL as $ek): ?>
                    <option value="<?= e($ek) ?>" <?= $filterEkskul === $ek ? 'selected' : '' ?>><?= e($ek) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="jenjang" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                <option value="">-- Semua Jenjang --</option>
                <option value="MTs" <?= $filterJenjang === 'MTs' ? 'selected' : '' ?>>MTs</option>
                <option value="MA" <?= $filterJenjang === 'MA' ? 'selected' : '' ?>>MA</option>
            </select>
            <select name="kelas_siswa" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                <option value="">-- Semua Kelas --</option>
                <?php foreach (['7','8','9','10','11','12'] as $ks): ?>
                    <option value="<?= $ks ?>" <?= $filterKelasSiswa == $ks ? 'selected' : '' ?>>Kelas <?= $ks ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="panel-body p-0 table-responsive">
        <?php if (empty($siswaList)): ?>
            <div class="p-4 text-center text-muted">Belum ada data siswa untuk filter terpilih.</div>
        <?php else: ?>
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Kelas / Ekskul</th>
                        <th>Nilai Kehadiran</th>
                        <th>Nilai Progres Tugas</th>
                        <th>Nilai Total Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siswaList as $s): 
                        $n = $nilaiMap[$s['id']] ?? null;
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-primary-700"><?= e($s['nama']) ?></div>
                        </td>
                        <td>
                            <div class="text-xs">
                                <strong><?= e($s['kelas']) ?> (<?= e($s['jenjang']) ?>)</strong><br>
                                <span class="text-muted"><?= e($s['ekskul_list']) ?></span>
                            </div>
                        </td>
                        <?php if ($taId == 0): ?>
                            <td colspan="3" class="text-muted text-xs"><em>Set Tahun Ajaran Aktif terlebih dahulu.</em></td>
                        <?php elseif (!$n): ?>
                            <td class="text-muted text-xs">-</td>
                            <td class="text-muted text-xs">-</td>
                            <td class="text-muted text-xs"><em>Belum dinilai</em></td>
                        <?php else: ?>
                            <td class="fw-semibold"><?= (int)$n['nilai_kehadiran'] ?></td>
                            <td class="fw-semibold"><?= (int)$n['nilai_progress'] ?></td>
                            <td class="fw-bold text-success" style="font-size:var(--fs-md);"><?= (int)$n['nilai_total'] ?></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
