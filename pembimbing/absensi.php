<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Kelola Absensi
 * ============================================================
 */
$pageTitle = 'Absensi Kelas';
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

// Buka Sesi Baru & Tutup Sesi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'buka_sesi') {
        $kelas_ekskul = post('kelas_ekskul');
        $tanggal = post('tanggal'); // YYYY-MM-DD
        
        if (empty($kelas_ekskul) || empty($tanggal)) {
            $error = 'Kelas ekskul dan tanggal wajib diisi.';
        } elseif (!in_array($kelas_ekskul, $kelasDiampuArr)) {
            $error = 'Kelas ekskul tidak valid.';
        } else {
            // Cek apakah sudah ada sesi di tanggal yg sama untuk kelas ini
            $cek = $db->prepare("SELECT id FROM sesi_absensi WHERE pembimbing_id = ? AND kelas_ekskul = ? AND tanggal = ?");
            $cek->execute([$userId, $kelas_ekskul, $tanggal]);
            if ($cek->fetch()) {
                $error = 'Sesi absensi untuk kelas dan tanggal tersebut sudah ada.';
            } else {
                $stmt = $db->prepare("INSERT INTO sesi_absensi (pembimbing_id, kelas_ekskul, tanggal, status) VALUES (?, ?, ?, 'buka')");
                if ($stmt->execute([$userId, $kelas_ekskul, $tanggal])) {
                    $success = 'Sesi absensi berhasil dibuka.';
                } else {
                    $error = 'Gagal membuka sesi absensi.';
                }
            }
        }
    } elseif ($action === 'tutup_sesi') {
        $sesi_id = (int)$_POST['sesi_id'];
        $stmt = $db->prepare("UPDATE sesi_absensi SET status = 'tutup', ditutup_pada = CURRENT_TIMESTAMP WHERE id = ? AND pembimbing_id = ?");
        $stmt->execute([$sesi_id, $userId]);
        $success = 'Sesi absensi berhasil ditutup.';
    }
}

// Ambil riwayat sesi
$sql = "
    SELECT s.*, 
           (SELECT COUNT(*) FROM absensi a WHERE a.sesi_absensi_id = s.id AND a.status = 'hadir') as hadir,
           (SELECT COUNT(*) FROM absensi a WHERE a.sesi_absensi_id = s.id AND a.status = 'izin') as izin,
           (SELECT COUNT(*) FROM absensi a WHERE a.sesi_absensi_id = s.id AND a.status = 'sakit') as sakit,
           (SELECT COUNT(*) FROM absensi a WHERE a.sesi_absensi_id = s.id) as total_absen
    FROM sesi_absensi s
    WHERE s.pembimbing_id = ?
    ORDER BY s.tanggal DESC, s.id DESC
";
$stmtSesi = $db->prepare($sql);
$stmtSesi->execute([$userId]);
$sesiList = $stmtSesi->fetchAll();
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Absensi Kelas</h1>
        <p class="text-secondary mb-0">Buka sesi absensi dan input kehadiran siswa secara manual.</p>
    </div>
    <div>
        <button type="button" class="btn btn-primary" onclick="document.getElementById('modal-buka-sesi').style.display='flex'">
            <i class="bi bi-plus-lg me-2"></i> Buka Sesi Baru
        </button>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success" data-auto-dismiss="3000"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger" data-auto-dismiss="5000"><?= e($error) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header">
        <h2 class="panel-title"><i class="bi bi-calendar3 me-2"></i> Riwayat Sesi Absensi</h2>
    </div>
    <div class="panel-body p-0 table-responsive">
        <?php if (empty($sesiList)): ?>
            <div class="p-4 text-center text-muted">Belum ada sesi absensi yang dibuat.</div>
        <?php else: ?>
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Tanggal Sesi</th>
                        <th>Kelas Ekskul</th>
                        <th>Statistik Kehadiran</th>
                        <th>Status Sesi</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sesiList as $s): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-primary-700"><?= date('d M Y', strtotime($s['tanggal'])) ?></div>
                            <div class="text-xs text-muted">Dibuka: <?= date('H:i', strtotime($s['dibuka_pada'])) ?></div>
                        </td>
                        <td>
                            <span class="badge badge-primary"><?= e($s['kelas_ekskul']) ?></span>
                        </td>
                        <td>
                            <div class="d-flex gap-2 text-sm">
                                <span class="text-success" title="Hadir"><i class="bi bi-check-circle-fill"></i> <?= $s['hadir'] ?></span>
                                <span class="text-warning" title="Izin"><i class="bi bi-info-circle-fill"></i> <?= $s['izin'] ?></span>
                                <span class="text-danger" title="Sakit"><i class="bi bi-heartbreak-fill"></i> <?= $s['sakit'] ?></span>
                            </div>
                            <div class="text-xs text-muted mt-1">Total: <?= $s['total_absen'] ?> data tercatat</div>
                        </td>
                        <td>
                            <?php if ($s['status'] === 'buka'): ?>
                                <span class="badge badge-success">Terbuka</span>
                            <?php else: ?>
                                <span class="badge badge-secondary">Ditutup</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="<?= e(BASE_URL) ?>/pembimbing/absensi-input.php?sesi_id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary" title="Input/Lihat Absensi">
                                    <i class="bi bi-pencil-square"></i> Input Manual
                                </a>
                                <?php if ($s['status'] === 'buka'): ?>
                                <form method="POST" action="" onsubmit="return confirm('Tutup sesi ini? Anda tetap bisa mengedit data absensi nanti.');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="tutup_sesi">
                                    <input type="hidden" name="sesi_id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-warning" title="Tutup Sesi">
                                        <i class="bi bi-door-closed"></i> Tutup
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Buka Sesi -->
<div class="modal-overlay" id="modal-buka-sesi" style="display:none;">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title">Buka Sesi Absensi Baru</h3>
            <button type="button" class="btn-icon" onclick="document.getElementById('modal-buka-sesi').style.display='none'">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="buka_sesi">
            
            <div class="modal-body">
                <?php if (empty($kelasDiampuArr)): ?>
                    <div class="alert alert-warning">Anda belum ditugaskan mengajar kelas ekskul apapun.</div>
                <?php else: ?>
                <div class="form-group">
                    <label class="form-label" for="kelas_ekskul">Pilih Kelas Ekskul</label>
                    <select class="form-control" name="kelas_ekskul" id="kelas_ekskul" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach($kelasDiampuArr as $k): ?>
                            <option value="<?= e($k) ?>"><?= e($k) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group mb-0">
                    <label class="form-label" for="tanggal">Tanggal Pertemuan</label>
                    <input type="date" class="form-control" name="tanggal" id="tanggal" value="<?= date('Y-m-d') ?>" required>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:var(--sp-2);">
                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('modal-buka-sesi').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary" <?= empty($kelasDiampuArr) ? 'disabled' : '' ?>>Simpan Sesi</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pembimbing-footer.php'; ?>
