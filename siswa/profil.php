<?php
/**
 * ============================================================
 *  Siswa Dashboard — Edit Profil
 * ============================================================
 */
$pageTitle = 'Profil Saya';
require_once __DIR__ . '/../includes/siswa-header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];
$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_profil') {
    verifyCsrf();

    $no_wa        = post('no_wa');
    $nama_ortu    = post('nama_ortu');
    $no_hp_ortu   = post('no_hp_ortu');
    $alamat       = post('alamat_domisili');
    $pass_baru    = post('password_baru');
    $pass_konfirm = post('konfirmasi_password');

    // Handle foto upload
    $foto_profil_link = $siswaInfo['foto_profil_link'];
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = uploadProfilePhoto($_FILES['foto']);
        if ($uploadResult === null) {
            $error = 'Foto gagal diupload. Pastikan format JPG/PNG dan ukuran maksimal 2MB.';
        } else {
            // Hapus foto lama jika ada
            if (!empty($siswaInfo['foto_profil_link']) && strpos($siswaInfo['foto_profil_link'], '/assets/img/profiles/') !== false) {
                $oldPath = str_replace(BASE_URL, '', $siswaInfo['foto_profil_link']);
                $oldAbsolute = __DIR__ . '/..' . $oldPath;
                if (file_exists($oldAbsolute)) @unlink($oldAbsolute);
            }
            $foto_profil_link = $uploadResult;
        }
    }

    if (empty($error)) {
        if (!empty($pass_baru)) {
            if ($pass_baru !== $pass_konfirm) {
                $error = 'Konfirmasi kata sandi tidak cocok.';
            } else {
                $db->prepare("UPDATE users SET password = ?, foto_profil_link = ? WHERE id = ?")
                   ->execute([password_hash($pass_baru, PASSWORD_DEFAULT), $foto_profil_link, $uid]);
            }
        } else {
            $db->prepare("UPDATE users SET foto_profil_link = ? WHERE id = ?")
               ->execute([$foto_profil_link, $uid]);
        }

        if (empty($error)) {
            $db->prepare("UPDATE siswa_detail SET no_wa = ?, nama_ortu = ?, no_hp_ortu = ?, alamat_domisili = ? WHERE user_id = ?")
               ->execute([$no_wa, $nama_ortu, $no_hp_ortu, $alamat, $uid]);
            $success = 'Profil berhasil diperbarui!';

            // Reload siswaInfo
            $stmtReload = $db->prepare("SELECT u.*, sd.kelas, sd.jenjang, sd.wali_kelas, sd.no_wa, sd.nama_ortu, sd.no_hp_ortu, sd.alamat_domisili FROM users u JOIN siswa_detail sd ON u.id = sd.user_id WHERE u.id = ?");
            $stmtReload->execute([$uid]);
            $siswaInfo = $stmtReload->fetch();
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'pengajuan_kelas') {
    verifyCsrf();
    $jenis_pengajuan = post('jenis_pengajuan');
    $kelas_lama = post('kelas_lama') ?: null;
    $kelas_baru = post('kelas_baru');
    $alasan = post('alasan');

    if (empty($kelas_baru) || empty($alasan) || empty($jenis_pengajuan)) {
        $error = 'Harap lengkapi semua kolom pengajuan.';
    } else {
        $stmt = $db->prepare("INSERT INTO pengajuan_kelas (siswa_id, jenis_pengajuan, kelas_lama, kelas_baru, alasan) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$uid, $jenis_pengajuan, $kelas_lama, $kelas_baru, $alasan]);
        $success = 'Pengajuan kelas berhasil dikirim. Menunggu persetujuan Admin.';
    }
}

// Cek apakah ada pengajuan kelas yang menunggu
$stmtCek = $db->prepare("SELECT COUNT(*) FROM pengajuan_kelas WHERE siswa_id = ? AND status = 'menunggu'");
$stmtCek->execute([$uid]);
$adaPengajuanMenunggu = $stmtCek->fetchColumn() > 0;

$foto = $siswaInfo['foto_profil_link'] ?: '';
if ($foto && strpos($foto, 'http') === false) {
    $foto = BASE_URL . $foto;
}
if (!$foto) $foto = BASE_URL . '/assets/img/default-avatar.png';
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1" style="color: var(--clr-primary-700);">Profil Saya</h1>
        <p class="text-muted mb-0">Perbarui informasi kontak dan kata sandi kamu.</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success" data-auto-dismiss="3000"><i class="bi bi-check-circle-fill me-2"></i><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?></div>
<?php endif; ?>

<div class="row">
    <!-- Kartu Profil Kiri -->
    <div class="col-md-4 mb-4">
        <div class="panel h-100">
            <div class="panel-body text-center">
                <img src="<?= e($foto) ?>" alt="Foto Profil"
                    style="width: 130px; height: 130px; object-fit: cover; object-position: center; border-radius: 50%; border: 4px solid var(--clr-primary-100); box-shadow: var(--shadow-sm); display: block; margin: 0 auto;"
                    class="mb-3">
                <h3 class="fw-bold mb-1" style="color: var(--clr-primary-700);"><?= e($siswaInfo['nama']) ?></h3>
                <p class="text-muted text-sm mb-3">
                    <?= e($siswaInfo['jenjang']) ?> – Kelas <?= e($siswaInfo['kelas']) ?>
                </p>

                <!-- Ekskul badges -->
                <div class="d-flex flex-wrap justify-content-center gap-1 mb-3">
                    <?php foreach ($ekskulList as $eks): ?>
                    <span class="badge badge-primary"><?= e($eks) ?></span>
                    <?php endforeach; ?>
                </div>

                <hr>
                <div class="text-start text-sm mt-3 d-flex flex-column gap-2">
                    <div><i class="bi bi-envelope-fill me-2 text-muted"></i><?= e($siswaInfo['email']) ?></div>
                    <div><i class="bi bi-person-vcard-fill me-2 text-muted"></i><?= e($siswaInfo['username'] ?? '-') ?></div>
                    <div><i class="bi bi-whatsapp me-2 text-muted"></i><?= e($siswaInfo['no_wa'] ?: '-') ?></div>
                    <div><i class="bi bi-mortarboard-fill me-2 text-muted"></i>Wali Kelas: <?= e($siswaInfo['wali_kelas'] ?: '-') ?></div>
                </div>
                
                <hr>
                <?php if ($adaPengajuanMenunggu): ?>
                    <div class="alert alert-warning text-start mb-0 text-sm p-2">
                        <i class="bi bi-clock-history me-1"></i> Pengajuan tambah/ganti kelas Anda sedang <strong>menunggu persetujuan</strong> Admin.
                    </div>
                <?php else: ?>
                    <button type="button" class="btn btn-outline-primary w-100 mt-2" data-bs-toggle="modal" data-bs-target="#modalPengajuanKelas">
                        <i class="bi bi-plus-circle me-1"></i> Ajukan Tambah/Ganti Kelas
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Form Edit Kanan -->
    <div class="col-md-8 mb-4">
        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-pencil-square me-2"></i> Edit Informasi</h2>
            </div>
            <div class="panel-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profil">
                    <?= csrfField() ?>

                    <!-- Data yang tidak bisa diedit -->
                    <div class="row mb-3 g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted text-xs fw-semibold">Nama Lengkap</label>
                            <input type="text" class="form-control bg-light" value="<?= e($siswaInfo['nama']) ?>" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted text-xs fw-semibold">Email</label>
                            <input type="email" class="form-control bg-light" value="<?= e($siswaInfo['email']) ?>" disabled>
                        </div>
                    </div>
                    <div class="row mb-3 g-3">
                        <div class="col-md-4">
                            <label class="form-label text-muted text-xs fw-semibold">Jenjang</label>
                            <input type="text" class="form-control bg-light" value="<?= e($siswaInfo['jenjang']) ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted text-xs fw-semibold">Kelas</label>
                            <input type="text" class="form-control bg-light" value="<?= e($siswaInfo['kelas']) ?>" disabled>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label text-muted text-xs fw-semibold">Wali Kelas</label>
                            <input type="text" class="form-control bg-light" value="<?= e($siswaInfo['wali_kelas'] ?? '-') ?>" disabled>
                        </div>
                    </div>

                    <hr class="mb-3">
                    <h3 class="text-sm fw-bold mb-3">Yang Bisa Diedit</h3>

                    <div class="row mb-3 g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="no_wa">No. WhatsApp Aktif</label>
                            <input type="text" class="form-control" name="no_wa" id="no_wa" value="<?= e($siswaInfo['no_wa']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="nama_ortu">Nama Orang Tua / Wali</label>
                            <input type="text" class="form-control" name="nama_ortu" id="nama_ortu" value="<?= e($siswaInfo['nama_ortu']) ?>">
                        </div>
                    </div>
                    <div class="row mb-3 g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="no_hp_ortu">No. HP Orang Tua</label>
                            <input type="text" class="form-control" name="no_hp_ortu" id="no_hp_ortu" value="<?= e($siswaInfo['no_hp_ortu']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="alamat">Alamat Domisili</label>
                            <input type="text" class="form-control" name="alamat_domisili" id="alamat" value="<?= e($siswaInfo['alamat_domisili']) ?>">
                        </div>
                    </div>
                    <div class="form-group mb-4">
                        <label class="form-label" for="foto">Ubah Foto Profil</label>
                        <input type="file" class="form-control" name="foto" id="foto" accept="image/jpeg,image/png,image/jpg">
                        <div class="text-xs text-muted mt-1">Format: JPG/PNG. Maks 2MB. Kosongkan jika tidak ingin mengubah.</div>
                    </div>

                    <hr class="mb-3">
                    <h3 class="text-sm fw-bold mb-3">Ubah Kata Sandi</h3>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label" for="password_baru">Kata Sandi Baru</label>
                            <input type="password" class="form-control" name="password_baru" id="password_baru" placeholder="Kosongkan jika tidak diubah">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="konfirmasi_password">Konfirmasi Kata Sandi</label>
                            <input type="password" class="form-control" name="konfirmasi_password" id="konfirmasi_password" placeholder="Ulangi kata sandi baru">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-5">
                            <i class="bi bi-save me-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Pengajuan Kelas -->
<div class="modal" id="modalPengajuanKelas" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="pengajuan_kelas">
                <?= csrfField() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Pengajuan Kelas Ekskul</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Jenis Pengajuan</label>
                        <select name="jenis_pengajuan" class="form-select" id="jenisPengajuan" required>
                            <option value="">-- Pilih --</option>
                            <option value="tambah">Tambah Kelas (Ikut lebih dari satu)</option>
                            <option value="ganti">Ganti Kelas (Pindah kelas)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="wrapperKelasLama" style="display: none;">
                        <label class="form-label">Kelas yang Ingin Dilepas</label>
                        <select name="kelas_lama" class="form-select" id="selectKelasLama">
                            <option value="">-- Pilih Kelas Saat Ini --</option>
                            <?php foreach ($ekskulList as $eks): ?>
                                <option value="<?= e($eks) ?>"><?= e($eks) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kelas Baru yang Diinginkan</label>
                        <select name="kelas_baru" class="form-select" required>
                            <option value="">-- Pilih Kelas --</option>
                            <?php foreach (KELAS_EKSKUL as $eks): ?>
                                <?php if (!in_array($eks, $ekskulList)): ?>
                                    <option value="<?= e($eks) ?>"><?= e($eks) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Alasan</label>
                        <textarea name="alasan" class="form-control" rows="3" required placeholder="Kenapa kamu ingin tambah/pindah kelas?"></textarea>
                    </div>
                </div>
                <div class="modal-footer d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Kirim Pengajuan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ── Modal System ───────────────────────────────────────────
    function openModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.remove('show');
        document.body.style.overflow = '';
    }

    // Tombol trigger dengan data-bs-toggle="modal"
    document.querySelectorAll('[data-bs-toggle="modal"]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const target = this.getAttribute('data-bs-target');
            if (target) openModal(target.replace('#', ''));
        });
    });

    // Tombol close dengan data-bs-dismiss="modal"
    document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) closeModal(modal.id);
        });
    });

    // Klik backdrop untuk tutup modal
    document.querySelectorAll('.modal').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) closeModal(modal.id);
        });
    });

    // Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(function(m) {
                closeModal(m.id);
            });
        }
    });

    // ── Toggle kelas lama/baru berdasarkan jenis pengajuan ─────
    const jenisPengajuan = document.getElementById('jenisPengajuan');
    const wrapperKelasLama = document.getElementById('wrapperKelasLama');
    const selectKelasLama = document.getElementById('selectKelasLama');

    if (jenisPengajuan) {
        jenisPengajuan.addEventListener('change', function() {
            if (this.value === 'ganti') {
                wrapperKelasLama.style.display = 'block';
                selectKelasLama.setAttribute('required', 'required');
            } else {
                wrapperKelasLama.style.display = 'none';
                selectKelasLama.removeAttribute('required');
                selectKelasLama.value = '';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/siswa-footer.php'; ?>
