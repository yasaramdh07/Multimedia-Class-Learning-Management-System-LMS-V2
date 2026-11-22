<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Form Materi
 * ============================================================
 */
$pageTitle = 'Form Materi';
require_once __DIR__ . '/../includes/pembimbing-header.php';

$db = getDB();
$userId = $_SESSION['user_id'];
$error = '';

// Ambil tahun ajaran aktif
$ta = $db->query("SELECT id FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1")->fetch();
$taId = $ta ? $ta['id'] : 0;

if (!$taId) {
    echo '<div class="alert alert-danger mt-4">Tahun ajaran aktif tidak ditemukan. Tidak dapat menambah materi.</div>';
    require_once __DIR__ . '/../includes/pembimbing-footer.php';
    exit;
}

// Ambil kelas yang diampu
$stmtKelas = $db->prepare("SELECT kelas_diampu FROM pembimbing_detail WHERE user_id = ?");
$stmtKelas->execute([$userId]);
$kelasDiampuStr = $stmtKelas->fetchColumn();
$kelasDiampuArr = array_filter(array_map('trim', explode(',', $kelasDiampuStr)));

if (empty($kelasDiampuArr)) {
    echo '<div class="alert alert-warning mt-4">Anda belum ditugaskan mengajar kelas ekskul apapun.</div>';
    require_once __DIR__ . '/../includes/pembimbing-footer.php';
    exit;
}

// Edit mode
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$materi = null;
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM materi WHERE id = ? AND dibuat_oleh = ?");
    $stmt->execute([$id, $userId]);
    $materi = $stmt->fetch();
    if (!$materi) {
        redirect('/pembimbing/materi.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $judul = post('judul');
    $kelas_ekskul = post('kelas_ekskul');
    $tipe = post('tipe');
    $link_file = post('link_file');
    $target = post('target'); // Semua Jenjang, MTs, MA
    
    if (empty($judul) || empty($kelas_ekskul) || empty($tipe) || empty($link_file) || empty($target)) {
        $error = 'Semua kolom wajib diisi.';
    } elseif (!in_array($kelas_ekskul, $kelasDiampuArr)) {
        $error = 'Kelas ekskul tidak valid.';
    } elseif (!in_array($tipe, ['pdf','ppt','video'])) {
        $error = 'Tipe file tidak valid.';
    } elseif (!in_array($target, ['Semua Jenjang','MTs','MA'])) {
        $error = 'Target jenjang tidak valid.';
    } else {
        if ($materi) {
            // Update
            $stmt = $db->prepare("
                UPDATE materi 
                SET kelas_ekskul = ?, judul = ?, link_file = ?, tipe = ?, target = ?
                WHERE id = ? AND dibuat_oleh = ?
            ");
            if ($stmt->execute([$kelas_ekskul, $judul, $link_file, $tipe, $target, $id, $userId])) {
                redirect('/pembimbing/materi.php?msg=saved');
            } else {
                $error = 'Gagal menyimpan perubahan.';
            }
        } else {
            // Insert
            $stmt = $db->prepare("
                INSERT INTO materi (kelas_ekskul, judul, link_file, tipe, target, tahun_ajaran_id, dibuat_oleh)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$kelas_ekskul, $judul, $link_file, $tipe, $target, $taId, $userId])) {
                redirect('/pembimbing/materi.php?msg=saved');
            } else {
                $error = 'Gagal menambah materi.';
            }
        }
    }
}
?>

<div class="mb-4">
    <a href="<?= e(BASE_URL) ?>/pembimbing/materi.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali ke Daftar Materi</a>
</div>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1"><?= $materi ? 'Edit Materi' : 'Tambah Materi Baru' ?></h1>
        <p class="text-secondary mb-0">Bagikan materi berupa tautan Google Drive / YouTube ke siswa.</p>
    </div>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?= e($error) ?></div>
<?php endif; ?>

<div class="panel" style="max-width: 700px;">
    <div class="panel-body">
        <form method="POST" action="">
            <?= csrfField() ?>
            
            <div class="form-group">
                <label for="judul" class="form-label">Judul Materi <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="judul" name="judul" value="<?= e($materi['judul'] ?? '') ?>" required>
            </div>
            
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="kelas_ekskul" class="form-label">Kelas Ekskul <span class="text-danger">*</span></label>
                    <select class="form-control" id="kelas_ekskul" name="kelas_ekskul" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach($kelasDiampuArr as $k): ?>
                            <option value="<?= e($k) ?>" <?= (($materi['kelas_ekskul'] ?? '') === $k) ? 'selected' : '' ?>><?= e($k) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label for="target" class="form-label">Target Jenjang <span class="text-danger">*</span></label>
                    <select class="form-control" id="target" name="target" required>
                        <option value="Semua Jenjang" <?= (($materi['target'] ?? '') === 'Semua Jenjang') ? 'selected' : '' ?>>Semua Jenjang (MTs & MA)</option>
                        <option value="MTs" <?= (($materi['target'] ?? '') === 'MTs') ? 'selected' : '' ?>>Hanya MTs</option>
                        <option value="MA" <?= (($materi['target'] ?? '') === 'MA') ? 'selected' : '' ?>>Hanya MA</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Tipe File <span class="text-danger">*</span></label>
                <div class="d-flex gap-3">
                    <label class="d-flex align-items-center gap-2">
                        <input type="radio" name="tipe" value="pdf" <?= (($materi['tipe'] ?? '') === 'pdf') ? 'checked' : '' ?> required> PDF
                    </label>
                    <label class="d-flex align-items-center gap-2">
                        <input type="radio" name="tipe" value="ppt" <?= (($materi['tipe'] ?? '') === 'ppt') ? 'checked' : '' ?>> PPT / Slide
                    </label>
                    <label class="d-flex align-items-center gap-2">
                        <input type="radio" name="tipe" value="video" <?= (($materi['tipe'] ?? '') === 'video') ? 'checked' : '' ?>> Video
                    </label>
                </div>
            </div>
            
            <div class="form-group mb-4">
                <label for="link_file" class="form-label">Link File / Tautan <span class="text-danger">*</span></label>
                <input type="url" class="form-control" id="link_file" name="link_file" value="<?= e($materi['link_file'] ?? '') ?>" placeholder="https://..." required>
                <div class="text-xs text-muted mt-1">Pastikan link dapat diakses secara publik atau bagi yang memiliki link.</div>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="<?= e(BASE_URL) ?>/pembimbing/materi.php" class="btn btn-outline-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Simpan Materi
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pembimbing-footer.php'; ?>
