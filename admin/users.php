<?php
/**
 * ============================================================
 *  Admin — Manajemen Pengguna
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);
$pageTitle = 'Data Pengguna — ' . APP_NAME;

$db = getDB();
$success = '';
$error = '';

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    verifyCsrf();
    $id = (int)$_POST['user_id'];
    if ($id > 0) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$id])) {
            $success = "Pengguna berhasil dihapus.";
        } else {
            $error = "Gagal menghapus pengguna.";
        }
    }
}

// Ambil semua pengguna
$stmt = $db->query("
    SELECT id, role, nama, email, no_hp, password, status, foto_profil_link, created_at 
    FROM users 
    ORDER BY role ASC, nama ASC
");
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Data Pengguna</h1>
        <p class="text-secondary mb-0">Kelola semua akun pembimbing dan siswa.</p>
    </div>
    <div>
        <!-- Untuk mempermudah, tambah manual diarahkan ke halaman register role masing-masing,
             tapi di-bypass (bisa jadi todo selanjutnya untuk form add khusus admin). -->
        <a href="<?= e(BASE_URL) ?>/admin/user-form.php" class="btn btn-primary">
            <i class="bi bi-person-plus-fill"></i> Tambah Pengguna
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

<div class="alert alert-warning mb-4">
    <i class="bi bi-exclamation-triangle-fill"></i> <strong>Perhatian:</strong> Password ditampilkan dalam bentuk teks (plain-text) sesuai kebijakan sistem. Harap jaga kerahasiaan layar Anda.
</div>

<div class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <h2 class="panel-title mb-0"><i class="bi bi-people-fill me-2"></i> Daftar Pengguna</h2>
        <input type="text" class="form-control form-control-sm table-search" placeholder="Cari pengguna..." style="max-width: 200px;">
    </div>
    <div class="panel-body p-0 table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th>Nama & Kontak</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Password</th>
                    <th style="width: 150px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td>
                        <div class="fw-semibold text-primary-700"><?= e($u['nama']) ?></div>
                        <div class="text-xs text-muted">
                            <i class="bi bi-envelope"></i> <?= e($u['email']) ?><br>
                            <i class="bi bi-phone"></i> <?= e($u['no_hp']) ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($u['role'] === 'siswa'): ?>
                            <span class="badge badge-primary">Siswa</span>
                        <?php else: ?>
                            <span class="badge badge-success">Pembimbing</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['status'] === 'approved'): ?>
                            <span class="badge badge-success">Aktif</span>
                        <?php elseif ($u['status'] === 'pending'): ?>
                            <span class="badge badge-warning">Pending</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Rejected</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="input-icon-wrap" style="width: 150px;">
                            <input type="password" class="form-control form-control-sm" value="<?= e($u['password']) ?>" readonly style="background:var(--clr-surface-2); padding-right:30px;">
                            <button type="button" class="toggle-pw" aria-label="Tampilkan password" style="position:absolute; right:5px; top:50%; transform:translateY(-50%); border:none; background:none; color:var(--clr-text-muted);">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <div class="d-flex" style="gap:var(--sp-2);">
                            <a href="<?= e(BASE_URL) ?>/admin/user-form.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                <i class="bi bi-pencil-fill"></i>
                            </a>
                            <form method="POST" style="margin:0;">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Hapus pengguna ini secara permanen?');">
                                    <i class="bi bi-trash-fill"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($users)): ?>
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">Belum ada pengguna.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.querySelectorAll('.toggle-pw').forEach(btn => {
    btn.addEventListener('click', () => {
        const input = btn.previousElementSibling;
        const isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        btn.querySelector('i').className = isText ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
