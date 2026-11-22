<?php
/**
 * ============================================================
 *  Siswa Dashboard — Rekap Nilai & Progress Akhir Semester
 * ============================================================
 */
$pageTitle = 'Rekap Nilai';
require_once __DIR__ . '/../includes/siswa-header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

// Ambil semua tahun ajaran (aktif & berakhir) yang memiliki nilai untuk siswa ini
$stmtTA = $db->query("SELECT * FROM tahun_ajaran ORDER BY id DESC");
$semuaTA = $stmtTA->fetchAll();

// Aktif TA
$taAktif = $db->query("SELECT * FROM tahun_ajaran WHERE status='aktif' LIMIT 1")->fetch();
$taIdAktif = $taAktif['id'] ?? 0;
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1" style="color: var(--clr-primary-700);">Rekap Nilai Semester</h1>
        <p class="text-muted mb-0">Lihat capaian nilai kehadiran, progres materi, dan nilai akhir setiap semester.</p>
    </div>
</div>

<?php foreach ($semuaTA as $ta): 
    $taId = $ta['id'];
    $isAktif = ($ta['status'] === 'aktif');

    // Ambil nilai dari DB (kalau ada)
    $stmtNilai = $db->prepare("SELECT * FROM nilai WHERE siswa_id = ? AND tahun_ajaran_id = ?");
    $stmtNilai->execute([$uid, $taId]);
    $nilaiRow = $stmtNilai->fetch();

    // Hitung realtime untuk tahun ajaran aktif
    $nilaiKehadiran = (float)($nilaiRow['nilai_kehadiran'] ?? 0);
    $nilaiProgress  = (float)($nilaiRow['nilai_progress']  ?? 0);
    $nilaiTotal     = (float)($nilaiRow['nilai_total']     ?? 0);

    if ($isAktif) {
        // Hitung absensi realtime
        $stmtAbs = $db->prepare("
            SELECT 
                SUM(CASE WHEN a.status='hadir' THEN 1 ELSE 0 END) as hadir,
                COUNT(*) as total
            FROM absensi a
            JOIN sesi_absensi sa ON a.sesi_absensi_id = sa.id
            WHERE a.siswa_id = ?
              AND sa.kelas_ekskul IN (SELECT kelas_ekskul FROM siswa_kelas_ekskul WHERE siswa_id = ?)
        ");
        $stmtAbs->execute([$uid, $uid]);
        $absData = $stmtAbs->fetch();
        $nilaiKehadiran = ($absData['total'] > 0) ? round($absData['hadir'] / $absData['total'] * 100, 1) : 0;
        $nilaiTotal = round(($nilaiKehadiran + $nilaiProgress) / 2, 1);
    }

    if ($nilaiTotal === 0.0 && !$isAktif) continue; // Skip semester kosong (bukan aktif)

    // Hitung total materi & tugas (untuk progress bar semester ini)
    $stmtMatProg = $db->prepare("
        SELECT 
            COUNT(m.id) as total_materi,
            SUM(CASE WHEN pm.status_ceklis = 1 THEN 1 ELSE 0 END) as ceklis
        FROM materi m
        JOIN siswa_kelas_ekskul ske ON m.kelas_ekskul = ske.kelas_ekskul
        LEFT JOIN progress_materi pm ON pm.materi_id = m.id AND pm.siswa_id = ?
        WHERE ske.siswa_id = ? AND m.tahun_ajaran_id = ?
          AND (m.target = 'Semua Jenjang' OR m.target = ?)
    ");
    $stmtMatProg->execute([$uid, $uid, $taId, $siswaInfo['jenjang']]);
    $matProg = $stmtMatProg->fetch();

    $stmtTugProg = $db->prepare("
        SELECT 
            COUNT(t.id) as total_tugas,
            SUM(CASE WHEN st.status = 'diterima' THEN 1 ELSE 0 END) as diterima
        FROM tugas t
        JOIN siswa_kelas_ekskul ske ON t.kelas_ekskul = ske.kelas_ekskul
        LEFT JOIN submission_tugas st ON st.tugas_id = t.id AND st.siswa_id = ?
        WHERE ske.siswa_id = ? AND t.tahun_ajaran_id = ?
          AND (t.target = 'Semua Jenjang' OR t.target = ?)
    ");
    $stmtTugProg->execute([$uid, $uid, $taId, $siswaInfo['jenjang']]);
    $tugProg = $stmtTugProg->fetch();

    $pctMateri = $matProg['total_materi'] > 0 ? round($matProg['ceklis'] / $matProg['total_materi'] * 100) : 0;
    $pctTugas  = $tugProg['total_tugas']  > 0 ? round($tugProg['diterima'] / $tugProg['total_tugas'] * 100) : 0;

    // Grade
    $grade = match(true) {
        $nilaiTotal >= 90 => ['A', 'success'],
        $nilaiTotal >= 80 => ['B+', 'success'],
        $nilaiTotal >= 70 => ['B', 'primary'],
        $nilaiTotal >= 60 => ['C', 'warning'],
        default           => ['D', 'danger'],
    };
?>
<div class="panel mb-4" style="border-top: 4px solid var(--clr-primary-<?= $isAktif ? '500' : '200' ?>);">
    <div class="panel-header">
        <div>
            <h2 class="panel-title">
                <?= e($ta['tahun']) ?> – Semester <?= e($ta['semester']) ?>
                <?php if ($isAktif): ?>
                    <span class="badge badge-success ms-2">Sedang Berjalan</span>
                <?php else: ?>
                    <span class="badge badge-secondary ms-2">Selesai</span>
                <?php endif; ?>
            </h2>
        </div>
        <!-- Grade bubble -->
        <div style="
            width: 56px; height: 56px; border-radius: 50%;
            background: var(--clr-primary-100);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            font-weight: 800; color: var(--clr-primary-700);
            font-size: 1.2rem; line-height: 1;">
            <?= $grade[0] ?>
            <span style="font-size: 0.6rem; font-weight: 400; opacity: 0.7;">Grade</span>
        </div>
    </div>

    <div class="panel-body">
        <div class="row g-4">
            <!-- Nilai Summary -->
            <div class="col-md-4">
                <div class="d-flex flex-column gap-3">
                    <div class="text-center p-4" style="background: var(--clr-surface-2); border-radius: var(--radius-xl);">
                        <div style="font-size: 3rem; font-weight: 800; color: var(--clr-primary-700); line-height: 1;">
                            <?= number_format($nilaiTotal, 1) ?>
                        </div>
                        <div class="text-sm text-muted mt-1">Nilai Akhir (dari 100)</div>
                    </div>

                    <div class="d-flex gap-3">
                        <div class="flex-1 text-center p-3" style="background: #f0fdf4; border-radius: var(--radius-lg); flex: 1;">
                            <div class="fw-bold text-success fs-5"><?= number_format($nilaiKehadiran, 1) ?></div>
                            <div class="text-xs text-muted">Kehadiran</div>
                        </div>
                        <div class="flex-1 text-center p-3" style="background: var(--clr-primary-50); border-radius: var(--radius-lg); flex: 1;">
                            <div class="fw-bold text-primary-700 fs-5"><?= number_format($nilaiProgress, 1) ?></div>
                            <div class="text-xs text-muted">Tugas</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Breakdown -->
            <div class="col-md-8">
                <div class="d-flex flex-column gap-4 pt-1">
                    <!-- Kehadiran progress -->
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-sm fw-semibold"><i class="bi bi-calendar-check-fill me-1 text-success"></i>Kehadiran</span>
                            <span class="text-sm text-success fw-bold"><?= number_format($nilaiKehadiran, 1) ?>%</span>
                        </div>
                        <div style="height: 10px; background: var(--clr-surface-2); border-radius: 99px; overflow: hidden;">
                            <div style="width: <?= min(100, $nilaiKehadiran) ?>%; height: 100%; background: #22c55e; border-radius: 99px;"></div>
                        </div>
                    </div>

                    <!-- Materi progress -->
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-sm fw-semibold"><i class="bi bi-book-fill me-1 text-primary"></i>Materi Dibaca</span>
                            <span class="text-sm fw-bold"><?= $matProg['ceklis'] ?>/<?= $matProg['total_materi'] ?> (<?= $pctMateri ?>%)</span>
                        </div>
                        <div style="height: 10px; background: var(--clr-surface-2); border-radius: 99px; overflow: hidden;">
                            <div style="width: <?= $pctMateri ?>%; height: 100%; background: linear-gradient(90deg, var(--clr-primary-400), var(--clr-primary-600)); border-radius: 99px;"></div>
                        </div>
                    </div>

                    <!-- Tugas progress -->
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-sm fw-semibold"><i class="bi bi-check2-square me-1 text-warning"></i>Tugas Diterima</span>
                            <span class="text-sm fw-bold"><?= $tugProg['diterima'] ?>/<?= $tugProg['total_tugas'] ?> (<?= $pctTugas ?>%)</span>
                        </div>
                        <div style="height: 10px; background: var(--clr-surface-2); border-radius: 99px; overflow: hidden;">
                            <div style="width: <?= $pctTugas ?>%; height: 100%; background: #f59e0b; border-radius: 99px;"></div>
                        </div>
                    </div>

                    <!-- Nilai Total progress -->
                    <div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-sm fw-semibold"><i class="bi bi-award-fill me-1"></i>Nilai Total</span>
                            <span class="text-sm fw-bold"><?= number_format($nilaiTotal, 1) ?>/100</span>
                        </div>
                        <div style="height: 14px; background: var(--clr-surface-2); border-radius: 99px; overflow: hidden;">
                            <div style="width: <?= min(100, $nilaiTotal) ?>%; height: 100%; background: linear-gradient(90deg, var(--clr-primary-500), #7c3aed); border-radius: 99px; position: relative;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($semuaTA)): ?>
<div class="panel"><div class="panel-body text-center py-5 text-muted">
    <i class="bi bi-award fs-1 d-block mb-2"></i>
    Belum ada data tahun ajaran.
</div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/siswa-footer.php'; ?>
