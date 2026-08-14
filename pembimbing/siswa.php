<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Siswa Bimbingan
 * ============================================================
 */
$pageTitle = 'Siswa Bimbingan';
require_once __DIR__ . '/../includes/pembimbing-header.php';

$db = getDB();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

// Proses pengajuan pengeluaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'ajukan_pengeluaran') {
        $siswa_id = (int)$_POST['siswa_id'];
        $alasan = post('alasan', '');
        
        if (empty($alasan)) {
            $error = 'Alasan wajib diisi.';
        } else {
            // Cek apakah sudah ada pengajuan menunggu untuk siswa ini
            $cek = $db->prepare("SELECT id FROM pengajuan_pengeluaran WHERE siswa_id = ? AND status_approval_admin = 'menunggu'");
            $cek->execute([$siswa_id]);
            if ($cek->fetch()) {
                $error = 'Sudah ada pengajuan pengeluaran yang menunggu persetujuan admin untuk siswa ini.';
            } else {
                $stmt = $db->prepare("INSERT INTO pengajuan_pengeluaran (siswa_id, pembimbing_id, alasan, status_approval_admin) VALUES (?, ?, ?, 'menunggu')");
                if ($stmt->execute([$siswa_id, $userId, $alasan])) {
                    $success = 'Pengajuan pengeluaran berhasil dikirim ke admin.';
                } else {
                    $error = 'Terjadi kesalahan sistem.';
                }
            }
        }
    }
}

// Ambil kelas yang diampu oleh pembimbing ini
$stmtKelas = $db->prepare("SELECT kelas_diampu FROM pembimbing_detail WHERE user_id = ?");
$stmtKelas->execute([$userId]);
$kelasDiampuStr = $stmtKelas->fetchColumn();
$kelasDiampuArr = array_filter(array_map('trim', explode(',', $kelasDiampuStr)));

// Query daftar siswa
$siswaList = [];
if (!empty($kelasDiampuArr)) {
    // Bangun placeholder (?,?,?)
    $placeholders = implode(',', array_fill(0, count($kelasDiampuArr), '?'));
    
    // Kita ambil juga data kehadiran (hadir) & total sesi untuk menghitung %
    // Dan status progress materi
    
    $sql = "
        SELECT 
            u.id, u.nama, u.status, u.email, u.foto_profil_link,
            sd.kelas, sd.jenjang,
            GROUP_CONCAT(DISTINCT ske.kelas_ekskul) as kelas_ekskul_diikuti,
            -- Hitung kehadiran
            (SELECT COUNT(*) FROM absensi a 
             JOIN sesi_absensi sa ON a.sesi_absensi_id = sa.id 
             WHERE a.siswa_id = u.id AND sa.kelas_ekskul IN ($placeholders) AND a.status = 'hadir') as total_hadir,
            (SELECT COUNT(*) FROM sesi_absensi sa2 WHERE sa2.kelas_ekskul IN ($placeholders)) as total_sesi,
            -- Hitung progress materi
            (SELECT COUNT(*) FROM progress_materi pm 
             JOIN materi m ON pm.materi_id = m.id 
             WHERE pm.siswa_id = u.id AND m.kelas_ekskul IN ($placeholders) AND pm.status_ceklis = 1) as materi_selesai,
            (SELECT COUNT(*) FROM materi m2 WHERE m2.kelas_ekskul IN ($placeholders)) as total_materi
        FROM users u
        JOIN siswa_detail sd ON u.id = sd.user_id
        JOIN siswa_kelas_ekskul ske ON u.id = ske.siswa_id
        WHERE u.status IN ('approved', 'rejected') 
          AND ske.kelas_ekskul IN ($placeholders)
        GROUP BY u.id
        ORDER BY u.nama ASC
    ";
    
    // Bind parameter (kelas_ekskul 5 kali karena 5 subquery IN / WHERE IN)
    $params = array_merge($kelasDiampuArr, $kelasDiampuArr, $kelasDiampuArr, $kelasDiampuArr, $kelasDiampuArr);
    
    $stmtSiswa = $db->prepare($sql);
    $stmtSiswa->execute($params);
    $siswaList = $stmtSiswa->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Daftar Siswa Bimbingan</h1>
        <p class="text-secondary mb-0">Kelola dan pantau siswa di kelas yang Anda ampu.</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success" data-auto-dismiss="5000">
    <i class="bi bi-check-circle-fill"></i> <?= e($success) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger" data-auto-dismiss="5000">
    <i class="bi bi-exclamation-triangle-fill"></i> <?= e($error) ?>
</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <h2 class="panel-title mb-0"><i class="bi bi-people me-2"></i> Semua Siswa (<?= count($siswaList) ?>)</h2>
        <input type="text" class="form-control form-control-sm table-search" placeholder="Cari siswa..." style="max-width: 200px;">
    </div>
    <div class="panel-body p-0 table-responsive">
        <?php if (empty($siswaList)): ?>
            <div class="p-4 text-center text-muted">Belum ada siswa yang mendaftar dan disetujui di kelas Anda.</div>
        <?php else: ?>
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Profil Siswa</th>
                        <th>Jenjang & Kelas</th>
                        <th>Ekskul (Diampu)</th>
                        <th>Performa</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siswaList as $s): ?>
                        <?php
                            $foto = $s['foto_profil_link'] ?: (BASE_URL . '/assets/img/default-avatar.png');
                            
                            $pctHadir = $s['total_sesi'] > 0 ? round(($s['total_hadir'] / $s['total_sesi']) * 100) : 0;
                            $pctMateri = $s['total_materi'] > 0 ? round(($s['materi_selesai'] / $s['total_materi']) * 100) : 0;
                            
                            // Filter kelas ekskul yg diampu pembimbing saja (mungkin siswa ikut kelas lain juga)
                            $eks = explode(',', $s['kelas_ekskul_diikuti']);
                            $eksDiampu = array_intersect($eks, $kelasDiampuArr);
                        ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <img src="<?= e($foto) ?>" alt="Foto" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                <div>
                                    <div class="fw-semibold text-primary-700"><?= e($s['nama']) ?></div>
                                    <div class="text-xs text-muted"><?= e($s['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div><?= e($s['jenjang']) ?></div>
                            <div class="text-muted text-sm">Kelas <?= e($s['kelas']) ?></div>
                        </td>
                        <td>
                            <?php foreach($eksDiampu as $ek): ?>
                                <span class="badge badge-primary mb-1"><?= e($ek) ?></span><br>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <div class="text-xs mb-1">
                                Kehadiran: <strong class="<?= $pctHadir < 50 ? 'text-danger' : 'text-success' ?>"><?= $pctHadir ?>%</strong> 
                                <span class="text-muted">(<?= $s['total_hadir'] ?>/<?= $s['total_sesi'] ?>)</span>
                            </div>
                            <div class="text-xs">
                                Materi: <strong class="<?= $pctMateri < 50 ? 'text-warning' : 'text-primary' ?>"><?= $pctMateri ?>%</strong> 
                                <span class="text-muted">(<?= $s['materi_selesai'] ?>/<?= $s['total_materi'] ?>)</span>
                            </div>
                        </td>
                        <td>
                            <?php if ($s['status'] === 'approved'): ?>
                                <span class="badge badge-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Tidak Aktif / Dikeluarkan</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($s['status'] === 'approved'): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="bukaModalKeluarkan(<?= $s['id'] ?>, '<?= e($s['nama']) ?>')">
                                    <i class="bi bi-person-dash"></i> Ajukan Pengeluaran
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajukan Pengeluaran -->
<div class="modal-overlay" id="modal-keluarkan" style="display:none;">
    <div class="modal-content" style="max-width:500px;">
        <div class="modal-header">
            <h3 class="modal-title text-danger">Ajukan Pengeluaran Siswa</h3>
            <button type="button" class="btn-icon" onclick="tutupModal()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="ajukan_pengeluaran">
            <input type="hidden" name="siswa_id" id="modal-siswa-id">
            
            <div class="modal-body">
                <p>Anda akan mengajukan pengeluaran untuk siswa <strong id="modal-siswa-nama"></strong> ke Admin. Akun siswa belum akan dinonaktifkan sampai Admin menyetujuinya.</p>
                <div class="form-group mb-0">
                    <label class="form-label" for="alasan">Alasan Pengeluaran <span class="text-danger">*</span></label>
                    <textarea name="alasan" id="alasan" class="form-control" rows="4" placeholder="Jelaskan alasan (misal: sering bolos, tugas kosong terus, dll)" required></textarea>
                </div>
            </div>
            
            <div class="modal-footer" style="display:flex; justify-content:flex-end; gap:var(--sp-2);">
                <button type="button" class="btn btn-outline-secondary" onclick="tutupModal()">Batal</button>
                <button type="submit" class="btn btn-danger">Kirim Pengajuan</button>
            </div>
        </form>
    </div>
</div>

<script>
function bukaModalKeluarkan(id, nama) {
    document.getElementById('modal-siswa-id').value = id;
    document.getElementById('modal-siswa-nama').innerText = nama;
    document.getElementById('alasan').value = '';
    document.getElementById('modal-keluarkan').style.display = 'flex';
}

function tutupModal() {
    document.getElementById('modal-keluarkan').style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/../includes/pembimbing-footer.php'; ?>
