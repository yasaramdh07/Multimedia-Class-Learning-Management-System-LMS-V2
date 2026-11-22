<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Input Absensi Manual
 * ============================================================
 */
$pageTitle = 'Input Absensi';
require_once __DIR__ . '/../includes/pembimbing-header.php';

$db = getDB();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

$sesiId = isset($_GET['sesi_id']) ? (int)$_GET['sesi_id'] : 0;
if (!$sesiId) {
    redirect('/pembimbing/absensi.php');
}

// Ambil info sesi
$stmtSesi = $db->prepare("SELECT * FROM sesi_absensi WHERE id = ? AND pembimbing_id = ?");
$stmtSesi->execute([$sesiId, $userId]);
$sesi = $stmtSesi->fetch();

if (!$sesi) {
    redirect('/pembimbing/absensi.php');
}

$stmtSesi->closeCursor();

// Pastikan DB sudah mendukung status 'alpa' (auto-migrasi sekali jalan)
$alpaErr = null;
$alpaSupported = ensureAbsensiAlpa($db, $alpaErr);

$ta = $db->query("SELECT id FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1")->fetch();
$taId = $ta ? $ta['id'] : 0;

// Proses submit absensi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    // Cek apakah ada data siswa yang disubmit
    if (isset($_POST['absensi']) && is_array($_POST['absensi'])) {
        $validStatus = ['hadir', 'izin', 'sakit', 'alpa'];
        $data = [];
        foreach ($_POST['absensi'] as $siswa_id => $status) {
            if (in_array($status, $validStatus, true) && (int)$siswa_id > 0) {
                $data[(int)$siswa_id] = $status;
            }
        }

        if (!$alpaSupported && in_array('alpa', $data, true)) {
            $error = 'Database belum mendukung status Alpa (migrasi gagal): ' . $alpaErr;
        } elseif ($data) {
            // BEGIN IMMEDIATE: ambil kunci tulis di awal supaya tidak bentrok dengan
            // request polling (update last_seen) yang menulis ke DB secara bersamaan.
            // Retry beberapa kali kalau DB sedang sibuk.
            $saved = false;
            $lastErr = '';
            for ($attempt = 1; $attempt <= 3 && !$saved; $attempt++) {
                $inTx = false;
                try {
                    $db->exec('BEGIN IMMEDIATE');
                    $inTx = true;

                    $cek = $db->prepare("SELECT id FROM absensi WHERE sesi_absensi_id = ? AND siswa_id = ?");
                    $upd = $db->prepare("UPDATE absensi SET status = ?, waktu_dicatat = datetime('now','localtime'), dicatat_oleh = ? WHERE id = ?");
                    $ins = $db->prepare("INSERT INTO absensi (sesi_absensi_id, siswa_id, status, dicatat_oleh) VALUES (?, ?, ?, ?)");

                    foreach ($data as $siswa_id => $status) {
                        $cek->execute([$sesiId, $siswa_id]);
                        $ada = $cek->fetchColumn();
                        $cek->closeCursor();

                        if ($ada) {
                            $upd->execute([$status, $userId, $ada]);
                        } else {
                            $ins->execute([$sesiId, $siswa_id, $status, $userId]);
                        }
                    }

                    $db->exec('COMMIT');
                    $saved = true;
                } catch (Throwable $e) {
                    if ($inTx) {
                        try { $db->exec('ROLLBACK'); } catch (Throwable $ignored) {}
                    }
                    $lastErr = $e->getMessage();
                    error_log('[Absensi] Simpan gagal (percobaan ' . $attempt . '): ' . $lastErr);

                    // Hanya retry kalau penyebabnya DB sibuk/terkunci
                    if (stripos($lastErr, 'locked') === false && stripos($lastErr, 'busy') === false) {
                        break;
                    }
                    usleep(300000 * $attempt);
                }
            }

            if ($saved) {
                $success = 'Data absensi berhasil disimpan.';
            } elseif (stripos($lastErr, 'locked') !== false || stripos($lastErr, 'busy') !== false) {
                $error = 'Server sedang sibuk, absensi belum tersimpan. Silakan klik Simpan sekali lagi.';
            } else {
                $error = 'Terjadi kesalahan saat menyimpan absensi. (' . $lastErr . ')';
            }
        }
    }
}

// Ambil daftar siswa di kelas ekskul sesi ini
$sqlSiswa = "
    SELECT u.id, u.nama, sd.kelas, sd.jenjang, u.foto_profil_link,
           (SELECT status FROM absensi a WHERE a.sesi_absensi_id = ? AND a.siswa_id = u.id) as status_absen
    FROM users u
    JOIN siswa_detail sd ON u.id = sd.user_id
    JOIN siswa_kelas_ekskul ske ON u.id = ske.siswa_id
    WHERE u.status = 'approved' AND ske.kelas_ekskul = ?
    ORDER BY u.nama ASC
";
$stmtSiswa = $db->prepare($sqlSiswa);
$stmtSiswa->execute([$sesiId, $sesi['kelas_ekskul']]);
$siswaList = $stmtSiswa->fetchAll();

?>

<div class="mb-4">
    <a href="<?= e(BASE_URL) ?>/pembimbing/absensi.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali ke Riwayat Sesi</a>
</div>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Input Absensi Manual</h1>
        <p class="text-secondary mb-0">Kelas: <strong class="text-primary-700"><?= e($sesi['kelas_ekskul']) ?></strong> | Tanggal: <strong><?= date('d M Y', strtotime($sesi['tanggal'])) ?></strong></p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success" data-auto-dismiss="3000"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger" data-auto-dismiss="5000"><?= e($error) ?></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <h2 class="panel-title"><i class="bi bi-list-check me-2"></i> Daftar Siswa (<?= count($siswaList) ?>)</h2>
        <div class="d-flex align-items-center gap-2 flex-wrap">
        <?php if (!empty($siswaList)): ?>
            <button type="button" id="btnAlpaSisa" class="btn btn-sm btn-alpa-sisa" title="Tandai semua siswa yang belum dipilih sebagai Alpa">
                <i class="bi bi-x-circle me-1"></i> Alpakan yang Belum Diisi
            </button>
        <?php endif; ?>
        <?php if ($sesi['status'] === 'tutup'): ?>
            <span class="badge badge-secondary">Sesi Ditutup</span>
        <?php else: ?>
            <span class="badge badge-success">Sesi Terbuka</span>
        <?php endif; ?>
        </div>
    </div>
    
    <div class="panel-body">
        <?php if (empty($siswaList)): ?>
            <div class="alert alert-warning text-center">Belum ada siswa yang mendaftar dan disetujui untuk kelas <?= e($sesi['kelas_ekskul']) ?>.</div>
        <?php else: ?>
            <form method="POST" action="">
                <?= csrfField() ?>
                
                <div class="table-responsive mb-4">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th class="text-center">Hadir</th>
                                <th class="text-center">Izin</th>
                                <th class="text-center">Sakit</th>
                                <th class="text-center">Alpa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($siswaList as $s): ?>
                                <?php
                                    $foto = $s['foto_profil_link'] ?: (BASE_URL . '/assets/img/default-avatar.png');
                                    $status = $s['status_absen']; // null jika belum absen
                                ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <img src="<?= e($foto) ?>" alt="Foto" style="width:40px;height:40px;border-radius:50%;object-fit:cover;">
                                        <div>
                                            <div class="fw-semibold text-primary-700"><?= e($s['nama']) ?></div>
                                            <div class="text-xs text-muted"><?= e($s['jenjang']) ?> - Kelas <?= e($s['kelas']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <input type="radio" name="absensi[<?= $s['id'] ?>]" value="hadir" class="form-check-input" style="width:20px;height:20px;" <?= $status === 'hadir' ? 'checked' : '' ?> required>
                                </td>
                                <td class="text-center">
                                    <input type="radio" name="absensi[<?= $s['id'] ?>]" value="izin" class="form-check-input" style="width:20px;height:20px;" <?= $status === 'izin' ? 'checked' : '' ?>>
                                </td>
                                <td class="text-center">
                                    <input type="radio" name="absensi[<?= $s['id'] ?>]" value="sakit" class="form-check-input" style="width:20px;height:20px;" <?= $status === 'sakit' ? 'checked' : '' ?>>
                                </td>
                                <td class="text-center">
                                    <input type="radio" name="absensi[<?= $s['id'] ?>]" value="alpa" class="form-check-input" style="width:20px;height:20px;" title="Alpa (tanpa keterangan)" <?= $status === 'alpa' ? 'checked' : '' ?>>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bi bi-save me-2"></i> Simpan Data Kehadiran
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
/* Memperbesar ukuran radio button untuk mempermudah klik/tap */
input[type="radio"] {
    accent-color: var(--primary-500);
    cursor: pointer;
}
input[type="radio"][value="izin"] {
    accent-color: var(--warning-500);
}
input[type="radio"][value="sakit"] {
    accent-color: var(--danger-500);
}
input[type="radio"][value="alpa"] {
    accent-color: #334155;
}
.btn-alpa-sisa {
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-weight: 600;
    transition: all .2s ease;
}
.btn-alpa-sisa:hover {
    background: #334155;
    color: #fff;
    border-color: #334155;
    transform: translateY(-1px);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btnAlpaSisa');
    if (!btn) return;
    btn.addEventListener('click', function () {
        const groups = new Set();
        document.querySelectorAll('input[type="radio"][name^="absensi["]').forEach(r => groups.add(r.name));
        let count = 0;
        groups.forEach(name => {
            const checked = document.querySelector('input[name="' + CSS.escape(name) + '"]:checked');
            if (!checked) {
                const alpa = document.querySelector('input[name="' + CSS.escape(name) + '"][value="alpa"]');
                if (alpa) { alpa.checked = true; count++; }
            }
        });
        alert(count > 0
            ? count + ' siswa ditandai Alpa. Jangan lupa klik "Simpan Data Kehadiran".'
            : 'Semua siswa sudah memiliki status.');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/pembimbing-footer.php'; ?>
