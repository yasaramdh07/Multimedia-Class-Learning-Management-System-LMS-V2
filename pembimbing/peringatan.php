<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Peringatan
 * ============================================================
 */
$pageTitle = 'Peringatan Siswa';
require_once __DIR__ . '/../includes/pembimbing-header.php';

$db = getDB();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

// Ambil kelas yang diampu
$stmtKelas = $db->prepare("SELECT kelas_diampu FROM pembimbing_detail WHERE user_id = ?");
$stmtKelas->execute([$userId]);
$kelasDiampuStr = $stmtKelas->fetchColumn();
$kelasDiampuArr = array_filter(array_map('trim', explode(',', $kelasDiampuStr)));

// Jika submit form peringatan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'kirim_peringatan') {
        $siswa_id = (int)$_POST['siswa_id'];
        $alasan = post('alasan');
        
        if (empty($siswa_id) || empty($alasan)) {
            $error = 'Semua kolom wajib diisi.';
        } else {
            $stmt = $db->prepare("INSERT INTO peringatan (siswa_id, pembimbing_id, alasan) VALUES (?, ?, ?)");
            if ($stmt->execute([$siswa_id, $userId, $alasan])) {
                $success = 'Peringatan berhasil dikirim ke siswa bersangkutan.';
            } else {
                $error = 'Terjadi kesalahan sistem.';
            }
        }
    }
}

// Ambil daftar siswa bimbingan (untuk opsi dropdown)
$siswaOptions = [];
if (!empty($kelasDiampuArr)) {
    $placeholders = implode(',', array_fill(0, count($kelasDiampuArr), '?'));
    $sqlSiswa = "
        SELECT DISTINCT u.id, u.nama, sd.kelas
        FROM users u
        JOIN siswa_detail sd ON u.id = sd.user_id
        JOIN siswa_kelas_ekskul ske ON u.id = ske.siswa_id
        WHERE u.status = 'approved' AND ske.kelas_ekskul IN ($placeholders)
        ORDER BY u.nama ASC
    ";
    $stmtSiswa = $db->prepare($sqlSiswa);
    $stmtSiswa->execute($kelasDiampuArr);
    $siswaOptions = $stmtSiswa->fetchAll();
}

// Ambil riwayat peringatan yang pernah dikirim oleh pembimbing ini
$stmtRiwayat = $db->prepare("
    SELECT p.*, s.nama as nama_siswa, sd.kelas 
    FROM peringatan p
    JOIN users s ON p.siswa_id = s.id
    JOIN siswa_detail sd ON s.id = sd.user_id
    WHERE p.pembimbing_id = ?
    ORDER BY p.tanggal DESC, p.id DESC
");
$stmtRiwayat->execute([$userId]);
$riwayat = $stmtRiwayat->fetchAll();
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Peringatan Siswa</h1>
        <p class="text-secondary mb-0">Kelola dan kirim peringatan kepada siswa yang bermasalah.</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success" data-auto-dismiss="3000"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger" data-auto-dismiss="5000"><?= e($error) ?></div>
<?php endif; ?>

<div class="row">
    <!-- Form Kirim Peringatan -->
    <div class="col-md-5 mb-4">
        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-send-exclamation me-2"></i> Kirim Peringatan</h2>
            </div>
            <div class="panel-body">
                <?php if (empty($siswaOptions)): ?>
                    <div class="alert alert-warning">Anda belum memiliki siswa bimbingan yang aktif.</div>
                <?php else: ?>
                <form method="POST" action="" onsubmit="return confirm('Kirim peringatan ini? Siswa akan dapat melihatnya di dashboard mereka.');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="kirim_peringatan">
                    
                    <div class="form-group">
                        <label class="form-label" for="siswa_id">Pilih Siswa <span class="text-danger">*</span></label>
                        <select class="form-control" name="siswa_id" id="siswa_id" required>
                            <option value="">-- Pilih Siswa --</option>
                            <?php foreach ($siswaOptions as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['nama']) ?> (Kelas <?= e($s['kelas']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="form-label" for="alasan">Alasan Peringatan <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="alasan" id="alasan" rows="4" placeholder="Misal: Sudah 3 kali tidak hadir tanpa keterangan..." required></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send me-1"></i> Kirim Peringatan
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Riwayat Peringatan -->
    <div class="col-md-7 mb-4">
        <div class="panel h-100">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-clock-history me-2"></i> Riwayat Peringatan</h2>
            </div>
            <div class="panel-body p-0 table-responsive">
                <?php if (empty($riwayat)): ?>
                    <div class="p-4 text-center text-muted">Belum ada peringatan yang dikirim.</div>
                <?php else: ?>
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Siswa</th>
                                <th>Alasan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($riwayat as $r): ?>
                            <tr>
                                <td class="text-sm" style="white-space: nowrap;">
                                    <?= date('d M Y', strtotime($r['tanggal'])) ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <div class="fw-semibold text-primary-700"><?= e($r['nama_siswa']) ?></div>
                                    <div class="text-xs text-muted">Kelas <?= e($r['kelas']) ?></div>
                                </td>
                                <td>
                                    <p class="mb-0 text-sm" style="max-width: 300px; white-space: normal;">
                                        <?= nl2br(e($r['alasan'])) ?>
                                    </p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pembimbing-footer.php'; ?>
