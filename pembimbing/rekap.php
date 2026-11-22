<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Rekap & Nilai (termasuk export PDF)
 * ============================================================
 */
// Jangan output apapun sebelum ini jika ingin export PDF
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_PEMBIMBING);

$db = getDB();
$userId = $_SESSION['user_id'];

// Ambil tahun ajaran aktif
$ta = $db->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1")->fetch();
$taId = $ta ? $ta['id'] : 0;
$taLabel = $ta ? ($ta['tahun'] . ' Semester ' . $ta['semester']) : '-';

// Ambil kelas yang diampu
$stmtKelas = $db->prepare("SELECT kelas_diampu FROM pembimbing_detail WHERE user_id = ?");
$stmtKelas->execute([$userId]);
$kelasDiampuStr = $stmtKelas->fetchColumn();
$kelasDiampuArr = array_filter(array_map('trim', explode(',', $kelasDiampuStr)));

// Filter kelas ekskul untuk rekap
$filterKelas = $_GET['kelas'] ?? ($kelasDiampuArr[0] ?? '');
if (!in_array($filterKelas, $kelasDiampuArr)) {
    $filterKelas = $kelasDiampuArr[0] ?? '';
}

// Filter jenjang & kelas siswa
$filterJenjang = $_GET['jenjang'] ?? '';
$filterKelasSiswa = $_GET['kelas_siswa'] ?? '';

$rekapSiswa = [];
if ($taId && $filterKelas) {
    // 1. Dapatkan daftar siswa di kelas tersebut
    $sqlSiswa = "
        SELECT u.id, u.nama, sd.kelas, sd.jenjang,
               (SELECT COUNT(*) FROM absensi a JOIN sesi_absensi sa ON a.sesi_absensi_id = sa.id 
                WHERE a.siswa_id = u.id AND sa.kelas_ekskul = ? AND a.status = 'hadir') as total_hadir,
               (SELECT COUNT(*) FROM sesi_absensi sa2 WHERE sa2.kelas_ekskul = ?) as total_sesi,
               (SELECT nilai_progress FROM nilai n WHERE n.siswa_id = u.id AND n.tahun_ajaran_id = ?) as db_progress
        FROM users u
        JOIN siswa_detail sd ON u.id = sd.user_id
        JOIN siswa_kelas_ekskul ske ON u.id = ske.siswa_id
        WHERE u.status = 'approved' AND ske.kelas_ekskul = ?
    ";
    
    $params = [$filterKelas, $filterKelas, $taId, $filterKelas];
    
    if ($filterJenjang !== '') {
        $sqlSiswa .= " AND sd.jenjang = ? ";
        $params[] = $filterJenjang;
    }
    
    if ($filterKelasSiswa !== '') {
        $sqlSiswa .= " AND sd.kelas = ? ";
        $params[] = $filterKelasSiswa;
    }
    
    $sqlSiswa .= " ORDER BY sd.jenjang DESC, sd.kelas ASC, u.nama ASC";

    $stmtSiswa = $db->prepare($sqlSiswa);
    $stmtSiswa->execute($params);
    $siswaList = $stmtSiswa->fetchAll();
    
    // 2. Kalkulasi nilai
    foreach ($siswaList as $s) {
        $nilaiHadir = $s['total_sesi'] > 0 ? round(($s['total_hadir'] / $s['total_sesi']) * 100) : 0;
        $nilaiProgress = (float)($s['db_progress'] ?? 0);
        $nilaiTotal = round(($nilaiHadir + $nilaiProgress) / 2);
        
        // Simpan / update di DB
        $cek = $db->prepare("SELECT id FROM nilai WHERE siswa_id = ? AND tahun_ajaran_id = ?");
        $cek->execute([$s['id'], $taId]);
        $rowNilai = $cek->fetch();
        if ($rowNilai) {
            $db->prepare("UPDATE nilai SET nilai_kehadiran = ?, nilai_total = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
               ->execute([$nilaiHadir, $nilaiTotal, $rowNilai['id']]);
        } else {
            $db->prepare("INSERT INTO nilai (siswa_id, tahun_ajaran_id, nilai_kehadiran, nilai_progress, nilai_total, diberikan_oleh) VALUES (?, ?, ?, ?, ?, ?)")
               ->execute([$s['id'], $taId, $nilaiHadir, $nilaiProgress, $nilaiTotal, $userId]);
        }
        
        $rekapSiswa[] = [
            'nama' => $s['nama'],
            'kelas_lengkap' => $s['jenjang'] . ' - ' . $s['kelas'],
            'kehadiran_persen' => $nilaiHadir,
            'hadir_per_sesi' => $s['total_hadir'] . '/' . $s['total_sesi'],
            'nilai_progress' => $nilaiProgress,
            'nilai_akhir' => $nilaiTotal
        ];
    }
}

// LOGIKA EXPORT PDF
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once __DIR__ . '/../includes/fpdf/fpdf.php';
    
    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial', 'B', 15);
            $this->Cell(0, 10, 'Rekapitulasi Nilai & Kehadiran Ekskul', 0, 1, 'C');
            $this->SetFont('Arial', '', 11);
            $this->Cell(0, 6, 'MTs & MA YABIKA', 0, 1, 'C');
            $this->Ln(5);
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Halaman ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Info Meta
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(40, 7, 'Pembimbing', 0, 0); $pdf->Cell(100, 7, ': ' . $_SESSION['nama'], 0, 1);
    $pdf->Cell(40, 7, 'Kelas Ekskul', 0, 0); $pdf->Cell(100, 7, ': ' . $filterKelas, 0, 1);
    $pdf->Cell(40, 7, 'Tahun Ajaran', 0, 0); $pdf->Cell(100, 7, ': ' . $taLabel, 0, 1);
    $pdf->Cell(40, 7, 'Jenjang', 0, 0); $pdf->Cell(100, 7, ': ' . ($filterJenjang ?: 'Semua'), 0, 1);
    $pdf->Cell(40, 7, 'Kelas Siswa', 0, 0); $pdf->Cell(100, 7, ': ' . ($filterKelasSiswa ?: 'Semua'), 0, 1);
    $pdf->Ln(5);
    
    // Table Header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(230, 240, 255);
    $pdf->Cell(10, 8, 'No', 1, 0, 'C', true);
    $pdf->Cell(65, 8, 'Nama Siswa', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Jenjang/Kelas', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Kehadiran (%)', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Nilai Tugas', 1, 0, 'C', true);
    $pdf->Cell(25, 8, 'Nilai Akhir', 1, 1, 'C', true);
    
    // Table Body
    $pdf->SetFont('Arial', '', 10);
    $no = 1;
    foreach ($rekapSiswa as $s) {
        $pdf->Cell(10, 8, $no++, 1, 0, 'C');
        $pdf->Cell(65, 8, substr($s['nama'], 0, 30), 1, 0, 'L');
        $pdf->Cell(35, 8, $s['kelas_lengkap'], 1, 0, 'C');
        $pdf->Cell(30, 8, $s['kehadiran_persen'] . '% (' . $s['hadir_per_sesi'] . ')', 1, 0, 'C');
        $pdf->Cell(25, 8, $s['nilai_progress'], 1, 0, 'C');
        $pdf->Cell(25, 8, $s['nilai_akhir'], 1, 1, 'C');
    }
    
    $fileName = 'Rekap_Nilai_' . str_replace(' ', '_', $filterKelas);
    if ($filterJenjang) $fileName .= '_' . $filterJenjang;
    if ($filterKelasSiswa) $fileName .= '_Kls_' . $filterKelasSiswa;
    $fileName .= '_' . date('Ymd') . '.pdf';
    $pdf->Output('D', $fileName);
    exit;
}

// BILA BUKAN EXPORT, RENDER HTML
$pageTitle = 'Rekap & Nilai';
require_once __DIR__ . '/../includes/pembimbing-header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Rekap Absensi & Nilai</h1>
        <p class="text-secondary mb-0">Tahun Ajaran: <?= e($taLabel) ?></p>
    </div>
    <div>
        <?php if ($taId && !empty($rekapSiswa)): ?>
        <a href="<?= e(BASE_URL) ?>/pembimbing/rekap.php?kelas=<?= urlencode($filterKelas) ?>&jenjang=<?= urlencode($filterJenjang) ?>&kelas_siswa=<?= urlencode($filterKelasSiswa) ?>&export=pdf" class="btn btn-danger">
            <i class="bi bi-file-earmark-pdf-fill me-2"></i> Export ke PDF
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$taId): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle-fill"></i> Tidak bisa melihat rekap karena tidak ada tahun ajaran yang aktif.
</div>
<?php elseif (empty($kelasDiampuArr)): ?>
<div class="alert alert-warning">Anda belum ditugaskan mengajar kelas ekskul apapun.</div>
<?php else: ?>

<div class="panel mb-4">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <h2 class="panel-title"><i class="bi bi-table me-2"></i> Data Rekapitulasi</h2>
        <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
            <input type="text" class="form-control form-control-sm table-search" placeholder="Cari siswa..." style="max-width: 150px;">
            <?php if (count($kelasDiampuArr) > 1): ?>
            <select name="kelas" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                <?php foreach ($kelasDiampuArr as $k): ?>
                    <option value="<?= e($k) ?>" <?= $filterKelas === $k ? 'selected' : '' ?>><?= e($k) ?></option>
                <?php endforeach; ?>
            </select>
            <?php else: ?>
            <input type="hidden" name="kelas" value="<?= e($filterKelas) ?>">
            <?php endif; ?>
            
            <select name="jenjang" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                <option value="">Semua Jenjang</option>
                <option value="MTs" <?= $filterJenjang === 'MTs' ? 'selected' : '' ?>>MTs</option>
                <option value="MA" <?= $filterJenjang === 'MA' ? 'selected' : '' ?>>MA</option>
            </select>

            <select name="kelas_siswa" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                <option value="">Semua Kelas</option>
                <?php foreach (['7','8','9','10','11','12'] as $ks): ?>
                    <option value="<?= $ks ?>" <?= $filterKelasSiswa == $ks ? 'selected' : '' ?>>Kelas <?= $ks ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="panel-body p-0 table-responsive">
        <?php if (empty($rekapSiswa)): ?>
            <div class="p-4 text-center text-muted">Belum ada siswa di kelas ini.</div>
        <?php else: ?>
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Siswa</th>
                        <th>Jenjang/Kelas</th>
                        <th class="text-center">Kehadiran</th>
                        <th class="text-center">Nilai Tugas</th>
                        <th class="text-center">Nilai Akhir (Rata-rata)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $no = 1; foreach ($rekapSiswa as $s): ?>
                    <tr>
                        <td><?= $no++ ?></td>
                        <td>
                            <div class="fw-semibold text-primary-700"><?= e($s['nama']) ?></div>
                        </td>
                        <td><?= e($s['kelas_lengkap']) ?></td>
                        <td class="text-center">
                            <span class="fs-5 <?= $s['kehadiran_persen'] < 50 ? 'text-danger' : 'text-success' ?>"><?= $s['kehadiran_persen'] ?>%</span><br>
                            <span class="text-xs text-muted"><?= $s['hadir_per_sesi'] ?> sesi</span>
                        </td>
                        <td class="text-center">
                            <span class="fs-5 text-primary"><?= $s['nilai_progress'] ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $s['nilai_akhir'] >= 75 ? 'badge-success' : 'badge-warning' ?> fs-6 py-2 px-3">
                                <?= $s['nilai_akhir'] ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
<?php require_once __DIR__ . '/../includes/pembimbing-footer.php'; ?>
