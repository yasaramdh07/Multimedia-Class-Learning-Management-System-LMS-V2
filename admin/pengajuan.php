<?php
/**
 * ============================================================
 *  Admin — Pengajuan (Reset Password & Pengeluaran)
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);
$pageTitle = 'Antrian Pengajuan — ' . APP_NAME;

$db = getDB();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $tipe = $_POST['tipe'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0 && in_array($action, ['approve', 'reject'])) {
        try {
            $db->beginTransaction();

            if ($tipe === 'reset_password') {
                if ($action === 'approve') {
                    // Update password di tabel users, set status pengajuan ke disetujui
                    $stmt = $db->prepare("SELECT user_id, new_password FROM password_reset_requests WHERE id = ? AND status = 'menunggu'");
                    $stmt->execute([$id]);
                    $req = $stmt->fetch();
                    if ($req) {
                        $db->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$req['new_password'], $req['user_id']]);
                        $db->prepare("UPDATE password_reset_requests SET status = 'disetujui' WHERE id = ?")->execute([$id]);
                        $success = "Pengajuan reset password disetujui. Password berhasil diperbarui.";
                    }
                } else {
                    $db->prepare("UPDATE password_reset_requests SET status = 'ditolak' WHERE id = ?")->execute([$id]);
                    $success = "Pengajuan reset password ditolak.";
                }
            } 
            elseif ($tipe === 'pengeluaran') {
                if ($action === 'approve') {
                    // Set siswa status ke rejected/inactive (dalam hal ini dikeluarkan = rejected), dan update status pengajuan
                    $stmt = $db->prepare("SELECT siswa_id FROM pengajuan_pengeluaran WHERE id = ? AND status_approval_admin = 'menunggu'");
                    $stmt->execute([$id]);
                    $req = $stmt->fetch();
                    if ($req) {
                        $db->prepare("UPDATE users SET status = 'rejected' WHERE id = ?")->execute([$req['siswa_id']]);
                        $db->prepare("UPDATE pengajuan_pengeluaran SET status_approval_admin = 'disetujui' WHERE id = ?")->execute([$id]);
                        $success = "Siswa berhasil dikeluarkan dan status akun telah dinonaktifkan.";
                    }
                } else {
                    $db->prepare("UPDATE pengajuan_pengeluaran SET status_approval_admin = 'ditolak' WHERE id = ?")->execute([$id]);
                    $success = "Pengajuan pengeluaran siswa ditolak.";
                }
            }

            $db->commit();
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Terjadi kesalahan sistem: " . $e->getMessage();
        }
    }
}

// 1. Ambil pengajuan reset password (menunggu)
$stmtReset = $db->query("
    SELECT pr.id, pr.dibuat_pada as created_at, u.nama, u.role, u.email, pr.new_password
    FROM password_reset_requests pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.status = 'menunggu'
    ORDER BY pr.dibuat_pada ASC
");
$reqReset = $stmtReset->fetchAll();

// 2. Ambil pengajuan pengeluaran (menunggu)
$stmtKeluar = $db->query("
    SELECT pp.id, pp.created_at AS tanggal, pp.alasan, s.nama AS nama_siswa, s.email AS email_siswa, p.nama AS nama_pembimbing
    FROM pengajuan_pengeluaran pp
    JOIN users s ON pp.siswa_id = s.id
    JOIN users p ON pp.pembimbing_id = p.id
    WHERE pp.status_approval_admin = 'menunggu'
    ORDER BY pp.created_at ASC
");
$reqKeluar = $stmtKeluar->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Antrian Pengajuan</h1>
        <p class="text-secondary mb-0">Kelola permintaan reset password dan pengeluaran siswa.</p>
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

<div class="row" style="display:flex; flex-wrap:wrap; gap:var(--sp-6);">
    <!-- Lupa Password -->
    <div style="flex:1; min-width:300px;">
        <div class="panel">
            <div class="panel-header" style="background:#fffbeb;">
                <h2 class="panel-title text-warning-700" style="color:#d97706;">
                    <i class="bi bi-key-fill"></i> Reset Password (<?= count($reqReset) ?>)
                </h2>
            </div>
            <div class="panel-body p-0 table-responsive">
                <?php if (empty($reqReset)): ?>
                    <div class="p-4 text-center text-muted">Tidak ada pengajuan reset password.</div>
                <?php else: ?>
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Pengguna</th>
                                <th>Password Baru</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reqReset as $r): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-primary-700"><?= e($r['nama']) ?></div>
                                    <div class="text-xs text-muted">
                                        <span class="badge badge-secondary mb-1"><?= ucfirst(e($r['role'])) ?></span><br>
                                        <?= e($r['email']) ?><br>
                                        <i class="bi bi-clock"></i> <?= date('d M Y H:i', strtotime($r['created_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-family:monospace; background:var(--clr-surface-2); padding:2px 6px; border-radius:4px; display:inline-block;">
                                        <?= e($r['new_password']) ?>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline-flex; gap:var(--sp-2);">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="tipe" value="reset_password">
                                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" title="Setujui" onclick="return confirm('Setujui password baru ini?');">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger" title="Tolak" onclick="return confirm('Tolak pengajuan ini?');">
                                            <i class="bi bi-x-lg"></i>
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
    </div>

    <!-- Pengeluaran Siswa -->
    <div style="flex:1; min-width:300px;">
        <div class="panel">
            <div class="panel-header" style="background:#fee2e2;">
                <h2 class="panel-title" style="color:#dc2626;">
                    <i class="bi bi-person-x-fill"></i> Pengeluaran Siswa (<?= count($reqKeluar) ?>)
                </h2>
            </div>
            <div class="panel-body p-0 table-responsive">
                <?php if (empty($reqKeluar)): ?>
                    <div class="p-4 text-center text-muted">Tidak ada pengajuan pengeluaran siswa.</div>
                <?php else: ?>
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Diajukan Oleh & Alasan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reqKeluar as $k): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold text-primary-700"><?= e($k['nama_siswa']) ?></div>
                                    <div class="text-xs text-muted"><?= e($k['email_siswa']) ?></div>
                                </td>
                                <td>
                                    <div class="text-xs text-muted mb-1">
                                        <i class="bi bi-person-badge"></i> <?= e($k['nama_pembimbing']) ?> &bull; <?= date('d M Y', strtotime($k['tanggal'])) ?>
                                    </div>
                                    <div style="font-size:var(--fs-xs); background:var(--clr-surface-2); padding:var(--sp-2); border-radius:var(--radius-sm); border-left:2px solid #dc2626;">
                                        <?= nl2br(e($k['alasan'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <form method="POST" style="display:inline-flex; gap:var(--sp-2);">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="tipe" value="pengeluaran">
                                        <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-danger" title="Keluarkan Siswa" onclick="return confirm('Keluarkan siswa ini secara permanen? Akunnya akan dinonaktifkan.');">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-secondary" title="Tolak Pengajuan" onclick="return confirm('Tolak pengajuan ini dan pertahankan siswa?');">
                                            <i class="bi bi-x-lg"></i>
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
