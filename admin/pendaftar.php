<?php
/**
 * ============================================================
 *  Admin — Pendaftar Baru (Approval)
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);
$pageTitle = 'Pendaftar Baru — ' . APP_NAME;

$db = getDB();
$success = '';
$error = '';

// Handle aksi Approve/Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($userId > 0 && in_array($action, ['approve', 'reject'])) {
        $newStatus = ($action === 'approve') ? STATUS_APPROVED : STATUS_REJECTED;
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND status = 'pending'");
        if ($stmt->execute([$newStatus, $userId])) {
            $success = "User berhasil di-" . ($action === 'approve' ? 'setujui' : 'tolak') . ".";
        } else {
            $error = "Gagal memproses aksi. Silakan coba lagi.";
        }
    }
}

// Ambil daftar pending Pembimbing
$stmtP = $db->query("
    SELECT u.id, u.nama, u.email, u.no_hp, u.foto_profil_link, u.created_at,
           d.umur, d.jenis_kelamin, d.kelas_diampu, d.sosial_media
    FROM users u
    JOIN pembimbing_detail d ON u.id = d.user_id
    WHERE u.status = 'pending' AND u.role = 'pembimbing'
    ORDER BY u.created_at ASC
");
$pendingPembimbing = $stmtP->fetchAll();

// Ambil daftar pending Siswa
$stmtS = $db->query("
    SELECT u.id, u.nama, u.email, u.no_hp, u.foto_profil_link, u.created_at,
           d.kelas, d.jenjang, d.wali_kelas, d.no_wa, d.nama_ortu, d.no_hp_ortu, d.alamat_domisili
    FROM users u
    JOIN siswa_detail d ON u.id = d.user_id
    WHERE u.status = 'pending' AND u.role = 'siswa'
    ORDER BY u.created_at ASC
");
$pendingSiswa = $stmtS->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Pendaftar Baru</h1>
        <p class="text-secondary mb-0">Tinjau dan setujui pendaftaran pembimbing dan siswa.</p>
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

<!-- Pembimbing Pending -->
<div class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <h2 class="panel-title mb-0"><i class="bi bi-person-badge-fill"></i> Pembimbing (<?= count($pendingPembimbing) ?>)</h2>
        <input type="text" class="form-control form-control-sm table-search" placeholder="Cari pembimbing..." style="max-width: 200px;">
    </div>
    <div class="panel-body p-0 table-responsive">
        <?php if (empty($pendingPembimbing)): ?>
            <div class="p-4 text-center text-muted">Tidak ada pendaftar pembimbing yang menunggu.</div>
        <?php else: ?>
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Profil</th>
                        <th>Info</th>
                        <th>Kelas Diampu</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingPembimbing as $p): ?>
                    <tr>
                        <td style="width:60px;">
                            <div class="user-avatar" style="width:48px; height:48px;">
                                <?php if ($p['foto_profil_link']): ?>
                                    <img src="<?= e($p['foto_profil_link']) ?>" alt="Foto">
                                <?php else: ?>
                                    <i class="bi bi-person"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-primary-700"><?= e($p['nama']) ?></div>
                            <div class="text-xs text-muted">
                                <i class="bi bi-envelope"></i> <?= e($p['email']) ?><br>
                                <i class="bi bi-phone"></i> <?= e($p['no_hp']) ?>
                            </div>
                        </td>
                        <td>
                            <div style="max-width:200px; font-size:var(--fs-xs);">
                                <?= e($p['kelas_diampu']) ?>
                            </div>
                        </td>
                        <td>
                            <form method="POST" style="display:inline-flex; gap:var(--sp-2);">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $p['id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Setujui pembimbing ini?');">
                                    <i class="bi bi-check-lg"></i> Terima
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tolak pembimbing ini?');">
                                    <i class="bi bi-x-lg"></i> Tolak
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

<!-- Siswa Pending -->
<div class="panel">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <h2 class="panel-title mb-0"><i class="bi bi-people-fill"></i> Siswa (<?= count($pendingSiswa) ?>)</h2>
        <input type="text" class="form-control form-control-sm table-search" placeholder="Cari siswa..." style="max-width: 200px;">
    </div>
    <div class="panel-body p-0 table-responsive">
        <?php if (empty($pendingSiswa)): ?>
            <div class="p-4 text-center text-muted">Tidak ada pendaftar siswa yang menunggu.</div>
        <?php else: ?>
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Profil</th>
                        <th>Info Siswa</th>
                        <th>Detail Kelas & Ortu</th>
                        <th>Ekskul</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingSiswa as $s): 
                        // Ambil kelas ekskul siswa ini
                        $stmtEk = $db->prepare("SELECT kelas_ekskul FROM siswa_kelas_ekskul WHERE siswa_id = ?");
                        $stmtEk->execute([$s['id']]);
                        $ekskuls = $stmtEk->fetchAll(PDO::FETCH_COLUMN);
                    ?>
                    <tr>
                        <td style="width:60px;">
                            <div class="user-avatar" style="width:48px; height:48px;">
                                <?php if ($s['foto_profil_link']): ?>
                                    <img src="<?= e($s['foto_profil_link']) ?>" alt="Foto">
                                <?php else: ?>
                                    <i class="bi bi-person"></i>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="fw-bold text-primary-700"><?= e($s['nama']) ?></div>
                            <div class="text-xs text-muted">
                                <i class="bi bi-envelope"></i> <?= e($s['email']) ?><br>
                                <i class="bi bi-whatsapp"></i> <?= e($s['no_wa']) ?>
                            </div>
                        </td>
                        <td style="font-size:var(--fs-xs);">
                            <div><strong>Kelas:</strong> <?= e($s['kelas']) ?> (<?= e($s['jenjang']) ?>)</div>
                            <div><strong>Wali Kelas:</strong> <?= e($s['wali_kelas']) ?></div>
                            <div class="mt-1 border-top pt-1 text-muted">
                                <strong>Ortu:</strong> <?= e($s['nama_ortu']) ?> (<?= e($s['no_hp_ortu']) ?>)
                            </div>
                        </td>
                        <td style="max-width:150px; font-size:var(--fs-xs);">
                            <?php foreach ($ekskuls as $ek): ?>
                                <span class="badge badge-primary mb-1"><?= e($ek) ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <form method="POST" style="display:inline-flex; gap:var(--sp-2);">
                                <?= csrfField() ?>
                                <input type="hidden" name="user_id" value="<?= $s['id'] ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" onclick="return confirm('Setujui siswa ini?');">
                                    <i class="bi bi-check-lg"></i> Terima
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tolak siswa ini?');">
                                    <i class="bi bi-x-lg"></i> Tolak
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

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
