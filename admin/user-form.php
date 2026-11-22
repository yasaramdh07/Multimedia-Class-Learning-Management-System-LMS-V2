<?php
/**
 * ============================================================
 *  Admin — Edit Pengguna
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);
$pageTitle = 'Edit Pengguna — ' . APP_NAME;

$db = getDB();
$success = '';
$error = '';
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    // Redirect if no ID (Add feature deferred to registration form for now to ensure all relations are populated)
    header("Location: " . BASE_URL . "/admin/users.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $nama = post('nama', '');
    $email = post('email', '');
    $no_hp = post('no_hp', '');
    $password = post('password', '');
    $status = post('status', '');

    if (empty($nama) || empty($email) || empty($password)) {
        $error = "Nama, Email, dan Password tidak boleh kosong.";
    } else {
        $stmt = $db->prepare("UPDATE users SET nama=?, email=?, no_hp=?, password=?, status=? WHERE id=?");
        if ($stmt->execute([$nama, $email, $no_hp, $password, $status, $id])) {
            $success = "Data pengguna berhasil diperbarui.";
        } else {
            $error = "Gagal memperbarui data.";
        }
    }
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    die("User tidak ditemukan.");
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Edit Pengguna</h1>
        <p class="text-secondary mb-0">Ubah data dasar akun pengguna.</p>
    </div>
    <div>
        <a href="<?= e(BASE_URL) ?>/admin/users.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
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

<div class="panel" style="max-width:600px;">
    <div class="panel-body">
        <form method="POST">
            <?= csrfField() ?>
            <div class="form-group mb-3">
                <label class="form-label">Role</label>
                <input type="text" class="form-control" value="<?= ucfirst(e($user['role'])) ?>" disabled>
            </div>
            
            <div class="form-group mb-3">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama" class="form-control" value="<?= e($user['nama']) ?>" required>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">No HP / WA</label>
                <input type="text" name="no_hp" class="form-control" value="<?= e($user['no_hp']) ?>">
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Status Akun</label>
                <select name="status" class="form-control">
                    <option value="pending" <?= $user['status']==='pending' ? 'selected':'' ?>>Pending</option>
                    <option value="approved" <?= $user['status']==='approved' ? 'selected':'' ?>>Approved (Aktif)</option>
                    <option value="rejected" <?= $user['status']==='rejected' ? 'selected':'' ?>>Rejected (Nonaktif)</option>
                </select>
            </div>

            <div class="form-group mb-4">
                <label class="form-label">Password</label>
                <input type="text" name="password" class="form-control" value="<?= e($user['password']) ?>" required>
                <small class="text-muted">Password disimpan dalam plain-text. Admin dapat menggantinya langsung di sini.</small>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Perubahan</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
