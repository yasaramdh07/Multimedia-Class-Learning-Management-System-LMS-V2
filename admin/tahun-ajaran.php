<?php
/**
 * ============================================================
 *  Admin — Manajemen Tahun Ajaran & Semester
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);
$pageTitle = 'Tahun Ajaran — ' . APP_NAME;

$db = getDB();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    try {
        $db->beginTransaction();

        if ($action === 'add') {
            $tahun = post('tahun', '');
            $semester = post('semester', '');
            
            if (empty($tahun) || empty($semester)) {
                $error = "Tahun dan Semester wajib diisi.";
            } else {
                $stmt = $db->prepare("INSERT INTO tahun_ajaran (tahun, semester, status) VALUES (?, ?, 'berakhir')");
                $stmt->execute([$tahun, $semester]);
                $success = "Tahun Ajaran berhasil ditambahkan.";
            }
        } 
        elseif ($action === 'set_aktif') {
            $id = (int)$_POST['id'];
            // Nonaktifkan semua dulu
            $db->exec("UPDATE tahun_ajaran SET status = 'berakhir'");
            // Aktifkan yang dipilih
            $stmt = $db->prepare("UPDATE tahun_ajaran SET status = 'aktif' WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Tahun Ajaran berhasil diset aktif.";
        }
        elseif ($action === 'akhiri') {
            $id = (int)$_POST['id'];
            $stmt = $db->prepare("UPDATE tahun_ajaran SET status = 'berakhir' WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Semester berhasil diakhiri.";
        }
        elseif ($action === 'delete') {
            $id = (int)$_POST['id'];
            // Cek apakah aktif
            $cek = $db->prepare("SELECT status FROM tahun_ajaran WHERE id = ?");
            $cek->execute([$id]);
            if ($cek->fetchColumn() === 'aktif') {
                $error = "Tidak bisa menghapus tahun ajaran yang sedang aktif. Akhiri semester terlebih dahulu.";
            } else {
                $stmt = $db->prepare("DELETE FROM tahun_ajaran WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Tahun Ajaran berhasil dihapus.";
            }
        }

        $db->commit();
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Terjadi kesalahan sistem: " . $e->getMessage();
    }
}

// Ambil semua tahun ajaran
$stmt = $db->query("SELECT * FROM tahun_ajaran ORDER BY id DESC");
$tahunAjaran = $stmt->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Tahun Ajaran & Semester</h1>
        <p class="text-secondary mb-0">Kelola periode aktif sistem. Hanya satu periode yang bisa aktif.</p>
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
    <!-- Col Kiri: Tambah Baru -->
    <div style="flex:1; min-width:300px; max-width:400px;">
        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-plus-circle"></i> Tambah Periode</h2>
            </div>
            <div class="panel-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label class="form-label" for="tahun">Tahun Ajaran</label>
                        <input type="text" name="tahun" id="tahun" class="form-control" placeholder="Contoh: 2026/2027" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="semester">Semester</label>
                        <select name="semester" id="semester" class="form-control" required>
                            <option value="Ganjil">Ganjil</option>
                            <option value="Genap">Genap</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Simpan Periode</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Col Kanan: List Periode -->
    <div style="flex:2; min-width:300px;">
        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-calendar3"></i> Daftar Tahun Ajaran</h2>
            </div>
            <div class="panel-body p-0 table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Tahun Ajaran</th>
                            <th>Semester</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tahunAjaran as $ta): ?>
                        <tr <?= $ta['status'] === 'aktif' ? 'style="background-color: var(--clr-primary-50);"' : '' ?>>
                            <td class="fw-semibold <?= $ta['status'] === 'aktif' ? 'text-primary-700' : '' ?>">
                                <?= e($ta['tahun']) ?>
                            </td>
                            <td><?= e($ta['semester']) ?></td>
                            <td>
                                <?php if ($ta['status'] === 'aktif'): ?>
                                    <span class="badge badge-success"><i class="bi bi-record-circle"></i> Aktif Saat Ini</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Berakhir</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex" style="gap:var(--sp-2);">
                                    <?php if ($ta['status'] === 'aktif'): ?>
                                        <form method="POST" style="margin:0;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="akhiri">
                                            <input type="hidden" name="id" value="<?= $ta['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Akhiri semester ini? Seluruh data absensi & nilai pada semester ini akan terkunci (berakhir).');">
                                                <i class="bi bi-lock-fill"></i> Akhiri Semester
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST" style="margin:0;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="set_aktif">
                                            <input type="hidden" name="id" value="<?= $ta['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Set semester ini menjadi AKTIF? Semester yang sebelumnya aktif otomatis akan diakhiri.');">
                                                <i class="bi bi-check-circle"></i> Set Aktif
                                            </button>
                                        </form>
                                        <form method="POST" style="margin:0;">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $ta['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus periode ini?');">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($tahunAjaran)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">Belum ada data tahun ajaran.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
