<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Form Tugas
 * ============================================================
 */
$pageTitle = 'Form Tugas';
require_once __DIR__ . '/../includes/pembimbing-header.php';

$db = getDB();
$userId = $_SESSION['user_id'];
$error = '';

// Ambil tahun ajaran aktif
$ta = $db->query("SELECT id FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1")->fetch();
$taId = $ta ? $ta['id'] : 0;

if (!$taId) {
    echo '<div class="alert alert-danger mt-4">Tahun ajaran aktif tidak ditemukan. Tidak dapat menambah tugas.</div>';
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
$tugas = null;
if ($id > 0) {
    $stmt = $db->prepare("SELECT * FROM tugas WHERE id = ? AND dibuat_oleh = ?");
    $stmt->execute([$id, $userId]);
    $tugas = $stmt->fetch();
    if (!$tugas) {
        redirect('/pembimbing/tugas.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    
    $judul = post('judul');
    $deskripsi = post('deskripsi');
    $kelas_ekskul = post('kelas_ekskul');
    $target = post('target'); // Semua Jenjang, MTs, MA
    $deadline = post('deadline'); // Format: YYYY-MM-DDTHH:MM
    $link_pengumpulan = post('link_pengumpulan');
    $materi_id = !empty($_POST['materi_id']) ? (int)$_POST['materi_id'] : null;
    
    if (empty($judul) || empty($kelas_ekskul) || empty($target) || empty($deadline) || empty($link_pengumpulan)) {
        $error = 'Semua kolom ber-bintang (*) wajib diisi.';
    } elseif (!in_array($kelas_ekskul, $kelasDiampuArr)) {
        $error = 'Kelas ekskul tidak valid.';
    } elseif (!in_array($target, ['Semua Jenjang','MTs','MA'])) {
        $error = 'Target jenjang tidak valid.';
    } else {
        // Konversi deadline ke format YYYY-MM-DD HH:MM:SS
        $deadlineSql = date('Y-m-d H:i:s', strtotime($deadline));
        
        if ($tugas) {
            // Update
            $stmt = $db->prepare("
                UPDATE tugas 
                SET materi_id = ?, kelas_ekskul = ?, judul = ?, deskripsi = ?, deadline = ?, target = ?, link_pengumpulan = ?
                WHERE id = ? AND dibuat_oleh = ?
            ");
            if ($stmt->execute([$materi_id, $kelas_ekskul, $judul, $deskripsi, $deadlineSql, $target, $link_pengumpulan, $id, $userId])) {
                redirect('/pembimbing/tugas.php?msg=saved');
            } else {
                $error = 'Gagal menyimpan perubahan.';
            }
        } else {
            // Insert
            $stmt = $db->prepare("
                INSERT INTO tugas (materi_id, kelas_ekskul, judul, deskripsi, deadline, target, tahun_ajaran_id, dibuat_oleh, link_pengumpulan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            if ($stmt->execute([$materi_id, $kelas_ekskul, $judul, $deskripsi, $deadlineSql, $target, $taId, $userId, $link_pengumpulan])) {
                redirect('/pembimbing/tugas.php?msg=saved');
            } else {
                $error = 'Gagal menambah tugas.';
            }
        }
    }
}

// Ambil daftar materi untuk kelas yang diampu pembimbing ini (untuk dropdown tautan materi)
$placeholders = implode(',', array_fill(0, count($kelasDiampuArr), '?'));
$sqlMateri = "SELECT id, judul, kelas_ekskul FROM materi WHERE dibuat_oleh = ? AND tahun_ajaran_id = ? AND kelas_ekskul IN ($placeholders) ORDER BY created_at DESC";
$paramsMateri = array_merge([$userId, $taId], $kelasDiampuArr);
$stmtMateri = $db->prepare($sqlMateri);
$stmtMateri->execute($paramsMateri);
$materiList = $stmtMateri->fetchAll();
?>

<div class="mb-4">
    <a href="<?= e(BASE_URL) ?>/pembimbing/tugas.php" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Kembali ke Daftar Tugas</a>
</div>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1"><?= $tugas ? 'Edit Tugas' : 'Tambah Tugas Baru' ?></h1>
        <p class="text-secondary mb-0">Buat tugas mandiri atau tautkan dengan materi yang sudah ada.</p>
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
                <label for="judul" class="form-label">Judul Tugas <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="judul" name="judul" value="<?= e($tugas['judul'] ?? '') ?>" required>
            </div>
            
            <div class="form-group">
                <label for="deskripsi" class="form-label">Deskripsi / Format Tugas</label>
                <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3" placeholder="Misal: Buat video maksimal 3 menit..."><?= e($tugas['deskripsi'] ?? '') ?></textarea>
                <div class="text-xs text-muted mt-1">Opsional. Jelaskan apa yang harus dibuat dan format file-nya.</div>
            </div>
            
            <div class="form-group">
                <label for="materi_id" class="form-label">Tautkan ke Materi (Opsional)</label>
                <select class="form-control" id="materi_id" name="materi_id">
                    <option value="">-- Tidak Ditautkan (Tugas Mandiri) --</option>
                    <?php foreach($materiList as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= (($tugas['materi_id'] ?? '') == $m['id']) ? 'selected' : '' ?>>
                            [<?= e($m['kelas_ekskul']) ?>] <?= e($m['judul']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="text-xs text-muted mt-1">Jika ditautkan, siswa dapat melihat materi langsung dari tugas ini.</div>
            </div>
            
            <div class="form-group">
                <label for="link_pengumpulan" class="form-label">Link Pengumpulan Tugas <span class="text-danger">*</span></label>
                <input type="url" class="form-control" id="link_pengumpulan" name="link_pengumpulan" value="<?= e($tugas['link_pengumpulan'] ?? '') ?>" placeholder="https://forms.gle/... atau https://drive.google.com/..." required>
                <div class="text-xs text-muted mt-1">Masukkan link Google Form, Google Drive, atau form eksternal lainnya tempat siswa harus mengupload file mereka.</div>
            </div>
            
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="kelas_ekskul" class="form-label">Kelas Ekskul <span class="text-danger">*</span></label>
                    <select class="form-control" id="kelas_ekskul" name="kelas_ekskul" required>
                        <option value="">-- Pilih Kelas --</option>
                        <?php foreach($kelasDiampuArr as $k): ?>
                            <option value="<?= e($k) ?>" <?= (($tugas['kelas_ekskul'] ?? '') === $k) ? 'selected' : '' ?>><?= e($k) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label for="target" class="form-label">Target Jenjang <span class="text-danger">*</span></label>
                    <select class="form-control" id="target" name="target" required>
                        <option value="Semua Jenjang" <?= (($tugas['target'] ?? '') === 'Semua Jenjang') ? 'selected' : '' ?>>Semua Jenjang (MTs & MA)</option>
                        <option value="MTs" <?= (($tugas['target'] ?? '') === 'MTs') ? 'selected' : '' ?>>Hanya MTs</option>
                        <option value="MA" <?= (($tugas['target'] ?? '') === 'MA') ? 'selected' : '' ?>>Hanya MA</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group mb-4">
                <label for="deadline" class="form-label">Batas Waktu (Deadline) <span class="text-danger">*</span></label>
                <?php 
                    $defDeadline = '';
                    if ($tugas) {
                        $defDeadline = date('Y-m-d\TH:i', strtotime($tugas['deadline']));
                    }
                ?>
                <input type="datetime-local" class="form-control" id="deadline" name="deadline" value="<?= $defDeadline ?>" required>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
                <a href="<?= e(BASE_URL) ?>/pembimbing/tugas.php" class="btn btn-outline-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i> Simpan Tugas
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pembimbing-footer.php'; ?>
