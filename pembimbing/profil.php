<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Edit Profil
 * ============================================================
 */
$pageTitle = 'Profil Saya';
require_once __DIR__ . '/../includes/pembimbing-header.php';

$db = getDB();
$userId = $_SESSION['user_id'];
$success = '';
$error = '';

// Ambil data user
$stmt = $db->prepare("
    SELECT u.nama, u.email, u.username, u.no_hp, u.foto_profil_link, pd.sosial_media 
    FROM users u
    JOIN pembimbing_detail pd ON u.id = pd.user_id
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $no_hp = post('no_hp');
    $sosial_media = post('sosial_media');
    $password_baru = post('password_baru');
    $konfirmasi_password = post('konfirmasi_password');
    
    $foto_profil_link = $user['foto_profil_link'];
    
    // Upload foto profil jika ada
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = uploadProfilePhoto($_FILES['foto']);
        if (strpos($uploadResult, 'Error') === 0) {
            $error = $uploadResult;
        } else {
            $foto_profil_link = $uploadResult; // Path relatif misal: /assets/img/profiles/...
            
            // Hapus foto lama jika bukan default/kosong
            if (!empty($user['foto_profil_link']) && strpos($user['foto_profil_link'], '/assets/img/profiles/') === 0) {
                $oldFile = __DIR__ . '/..' . $user['foto_profil_link'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }
        }
    }
    
    if (empty($error)) {
        // Cek password jika diisi
        if (!empty($password_baru)) {
            if ($password_baru !== $konfirmasi_password) {
                $error = 'Konfirmasi password tidak cocok.';
            } else {
                // Update dengan password
                $db->prepare("UPDATE users SET no_hp = ?, password = ?, foto_profil_link = ? WHERE id = ?")
                   ->execute([$no_hp, $password_baru, $foto_profil_link, $userId]);
            }
        } else {
            // Update tanpa password
            $db->prepare("UPDATE users SET no_hp = ?, foto_profil_link = ? WHERE id = ?")
               ->execute([$no_hp, $foto_profil_link, $userId]);
        }
        
        if (empty($error)) {
            $db->prepare("UPDATE pembimbing_detail SET sosial_media = ? WHERE user_id = ?")
               ->execute([$sosial_media, $userId]);
               
            $success = 'Profil berhasil diperbarui.';
            // Reload user data
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Profil Saya</h1>
        <p class="text-secondary mb-0">Perbarui informasi kontak dan kata sandi Anda.</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success" data-auto-dismiss="3000"><i class="bi bi-check-circle-fill"></i> <?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4 mb-4">
        <!-- Kartu Profil -->
        <div class="panel text-center">
            <div class="panel-body">
                <?php 
                    $foto = $user['foto_profil_link'] ?: (BASE_URL . '/assets/img/default-avatar.png');
                    // Jika path relatif (upload lokal)
                    if (strpos($foto, '/') === 0 && strpos($foto, BASE_URL) === false) {
                        $foto = BASE_URL . $foto;
                    }
                ?>
                <img src="<?= e($foto) ?>" alt="Foto Profil" class="mb-3 shadow-sm" style="width: 150px; height: 150px; object-fit: cover; object-position: center; border-radius: 50%; border: 4px solid var(--primary-100); display: block; margin: 0 auto;">
                <h3 class="h5 fw-bold text-primary-700 mb-1"><?= e($user['nama']) ?></h3>
                <p class="text-muted text-sm mb-3">@<?= e($user['username']) ?></p>
                <div class="badge badge-primary px-3 py-2 mb-2">Pembimbing</div>
            </div>
        </div>
    </div>
    
    <div class="col-md-8 mb-4">
        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-pencil-square me-2"></i> Edit Data Diri</h2>
            </div>
            <div class="panel-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control bg-light" value="<?= e($user['nama']) ?>" disabled>
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control bg-light" value="<?= e($user['email']) ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6 form-group">
                            <label class="form-label" for="no_hp">No. HP / WA</label>
                            <input type="text" class="form-control" name="no_hp" id="no_hp" value="<?= e($user['no_hp']) ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label class="form-label" for="sosial_media">Sosial Media (IG/Lainnya)</label>
                            <input type="text" class="form-control" name="sosial_media" id="sosial_media" value="<?= e($user['sosial_media']) ?>" placeholder="@username_ig">
                        </div>
                    </div>
                    
                    <div class="form-group mb-4">
                        <label class="form-label" for="foto">Ubah Foto Profil</label>
                        <input type="file" class="form-control" name="foto" id="foto" accept="image/*">
                        <div class="text-xs text-muted mt-1">Kosongkan jika tidak ingin mengubah foto. Format: JPG/PNG.</div>
                    </div>
                    
                    <hr class="mb-4">
                    <h3 class="h6 fw-bold mb-3">Ubah Kata Sandi</h3>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 form-group mb-md-0">
                            <label class="form-label" for="password_baru">Kata Sandi Baru</label>
                            <input type="password" class="form-control" name="password_baru" id="password_baru" placeholder="Ketik kata sandi baru">
                        </div>
                        <div class="col-md-6 form-group mb-0">
                            <label class="form-label" for="konfirmasi_password">Konfirmasi Kata Sandi Baru</label>
                            <input type="password" class="form-control" name="konfirmasi_password" id="konfirmasi_password" placeholder="Ulangi kata sandi">
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-2"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pembimbing-footer.php'; ?>
