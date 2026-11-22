<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Verifikasi Tugas
 * ============================================================
 */
$pageTitle = 'Verifikasi Tugas';
require_once __DIR__ . '/../includes/pembimbing-header.php';

$db = getDB();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'terima_tugas') {
        $sub_id = (int)$_POST['submission_id'];
        
        // Pastikan submission ini memang untuk tugas yang dibuat oleh pembimbing ini
        $cek = $db->prepare("
            SELECT st.id, st.siswa_id, t.tahun_ajaran_id 
            FROM submission_tugas st 
            JOIN tugas t ON st.tugas_id = t.id 
            WHERE st.id = ? AND t.dibuat_oleh = ? AND st.status = 'menunggu'
        ");
        $cek->execute([$sub_id, $userId]);
        $sub = $cek->fetch();
        
        if ($sub) {
            $db->beginTransaction();
            try {
                // Update status submission
                $db->prepare("UPDATE submission_tugas SET status = 'diterima', diverifikasi_oleh = ? WHERE id = ?")
                   ->execute([$userId, $sub_id]);
                
                // Tambah nilai_progress di tabel nilai
                // Misalnya, setiap tugas yang diterima nambah 10 poin, max 100
                $stmtNilai = $db->prepare("SELECT * FROM nilai WHERE siswa_id = ? AND tahun_ajaran_id = ?");
                $stmtNilai->execute([$sub['siswa_id'], $sub['tahun_ajaran_id']]);
                $nilai = $stmtNilai->fetch();
                
                if ($nilai) {
                    $newProgress = min(100, $nilai['nilai_progress'] + 10);
                    $newTotal = ($nilai['nilai_kehadiran'] + $newProgress) / 2;
                    $db->prepare("UPDATE nilai SET nilai_progress = ?, nilai_total = ?, diberikan_oleh = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?")
                       ->execute([$newProgress, $newTotal, $userId, $nilai['id']]);
                } else {
                    $newProgress = 10;
                    $newTotal = 5; // (0+10)/2
                    $db->prepare("INSERT INTO nilai (siswa_id, tahun_ajaran_id, nilai_kehadiran, nilai_progress, nilai_total, diberikan_oleh) VALUES (?, ?, 0, ?, ?, ?)")
                       ->execute([$sub['siswa_id'], $sub['tahun_ajaran_id'], $newProgress, $newTotal, $userId]);
                }
                
                $db->commit();
                $success = 'Tugas berhasil diverifikasi dan nilai siswa telah ditambahkan.';
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Gagal memverifikasi tugas.';
            }
        }
    }
}

// Ambil daftar pengumpulan tugas yang menunggu verifikasi
$sql = "
    SELECT st.*, t.judul as judul_tugas, t.kelas_ekskul, s.nama as nama_siswa, sd.kelas
    FROM submission_tugas st
    JOIN tugas t ON st.tugas_id = t.id
    JOIN users s ON st.siswa_id = s.id
    JOIN siswa_detail sd ON s.id = sd.user_id
    WHERE t.dibuat_oleh = ? AND st.status = 'menunggu'
    ORDER BY st.waktu_submit ASC
";
$stmtMenunggu = $db->prepare($sql);
$stmtMenunggu->execute([$userId]);
$listMenunggu = $stmtMenunggu->fetchAll();
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Verifikasi Tugas</h1>
        <p class="text-secondary mb-0">Periksa dan berikan nilai untuk tugas yang dikumpulkan siswa.</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success" data-auto-dismiss="3000"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger" data-auto-dismiss="3000"><?= e($error) ?></div>
<?php endif; ?>

<div class="panel mb-4">
    <div class="panel-header">
        <h2 class="panel-title"><i class="bi bi-inbox-fill me-2"></i> Menunggu Verifikasi (<?= count($listMenunggu) ?>)</h2>
    </div>
    <div class="panel-body p-0 table-responsive">
        <?php if (empty($listMenunggu)): ?>
            <div class="p-4 text-center text-muted">Hore! Tidak ada tugas yang perlu diverifikasi saat ini.</div>
        <?php else: ?>
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Siswa</th>
                        <th>Tugas & Kelas</th>
                        <th>File / Link Bukti</th>
                        <th>Waktu Kumpul</th>
                        <th width="150" class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($listMenunggu as $sub): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-primary-700"><?= e($sub['nama_siswa']) ?></div>
                            <div class="text-xs text-muted">Kelas <?= e($sub['kelas']) ?></div>
                        </td>
                        <td>
                            <div class="fw-medium"><?= e($sub['judul_tugas']) ?></div>
                            <span class="badge badge-secondary"><?= e($sub['kelas_ekskul']) ?></span>
                        </td>
                        <td>
                            <?php if ($sub['link_bukti'] === 'Eksternal'): ?>
                                <span class="badge badge-secondary"><i class="bi bi-box-arrow-up-right me-1"></i> Via Link Pengumpulan</span>
                            <?php else: ?>
                                <?php 
                                    $icon = 'bi-link';
                                    if ($sub['jenis_kumpul'] === 'zip') $icon = 'bi-file-zip';
                                    if ($sub['jenis_kumpul'] === 'foto') $icon = 'bi-image';
                                    if ($sub['jenis_kumpul'] === 'video') $icon = 'bi-play-btn';
                                ?>
                                <a href="<?= e($sub['link_bukti']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi <?= $icon ?>"></i> Buka Bukti
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm text-muted">
                            <?= date('d M Y, H:i', strtotime($sub['waktu_submit'])) ?>
                        </td>
                        <td class="text-center">
                            <form method="POST" action="" onsubmit="return confirm('Terima tugas ini dan berikan nilai progres ke siswa?');">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="terima_tugas">
                                <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="bi bi-check-lg"></i> Terima
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pembimbing-footer.php'; ?>
