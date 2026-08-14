<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Kelola Materi
 * ============================================================
 */
$pageTitle = 'Kelola Materi';
require_once __DIR__ . '/../includes/pembimbing-header.php';

$db = getDB();
$userId = $_SESSION['user_id'];

// Hapus Materi
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    // Pastikan milik pembimbing ini
    $cek = $db->prepare("SELECT id FROM materi WHERE id = ? AND dibuat_oleh = ?");
    $cek->execute([$delId, $userId]);
    if ($cek->fetch()) {
        $db->prepare("DELETE FROM materi WHERE id = ?")->execute([$delId]);
        redirect('/pembimbing/materi.php?msg=deleted');
    }
}

// Ambil tahun ajaran aktif
$ta = $db->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1")->fetch();
$taId = $ta ? $ta['id'] : 0;
$taLabel = $ta ? ($ta['tahun'] . ' (' . $ta['semester'] . ')') : 'Tidak ada Tahun Ajaran aktif';

// Filter kelas ekskul (jika pembimbing punya banyak kelas)
$stmtKelas = $db->prepare("SELECT kelas_diampu FROM pembimbing_detail WHERE user_id = ?");
$stmtKelas->execute([$userId]);
$kelasDiampuStr = $stmtKelas->fetchColumn();
$kelasDiampuArr = array_filter(array_map('trim', explode(',', $kelasDiampuStr)));

$filterKelas = $_GET['kelas'] ?? '';
if (!in_array($filterKelas, $kelasDiampuArr) && !empty($filterKelas)) {
    $filterKelas = '';
}

// Query Materi
$sql = "SELECT * FROM materi WHERE dibuat_oleh = ? AND tahun_ajaran_id = ?";
$params = [$userId, $taId];
if ($filterKelas) {
    $sql .= " AND kelas_ekskul = ?";
    $params[] = $filterKelas;
}
$sql .= " ORDER BY created_at DESC";
$stmtMateri = $db->prepare($sql);
$stmtMateri->execute($params);
$materiList = $stmtMateri->fetchAll();
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Kelola Materi</h1>
        <p class="text-secondary mb-0">Tahun Ajaran: <?= e($taLabel) ?></p>
    </div>
    <div>
        <?php if ($taId): ?>
        <a href="<?= e(BASE_URL) ?>/pembimbing/materi-form.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i> Tambah Materi
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
<div class="alert alert-success" data-auto-dismiss="3000">Materi berhasil dihapus.</div>
<?php endif; ?>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
<div class="alert alert-success" data-auto-dismiss="3000">Materi berhasil disimpan.</div>
<?php endif; ?>

<?php if (!$taId): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle-fill"></i> Tidak bisa mengelola materi karena tidak ada tahun ajaran yang aktif. Silakan hubungi Admin.
</div>
<?php else: ?>

<div class="panel mb-4">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <h2 class="panel-title">Daftar Materi</h2>
        <?php if (count($kelasDiampuArr) > 1): ?>
        <form method="GET" class="d-flex gap-2">
            <select name="kelas" class="form-control form-control-sm" onchange="this.form.submit()">
                <option value="">Semua Kelas</option>
                <?php foreach ($kelasDiampuArr as $k): ?>
                    <option value="<?= e($k) ?>" <?= $filterKelas === $k ? 'selected' : '' ?>><?= e($k) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>
    <div class="panel-body p-0 table-responsive">
        <?php if (empty($materiList)): ?>
            <div class="p-4 text-center text-muted">Belum ada materi.</div>
        <?php else: ?>
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Judul Materi</th>
                        <th>Kelas / Target</th>
                        <th>Tipe / Link</th>
                        <th>Dibuat</th>
                        <th width="150" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materiList as $m): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-primary-700"><?= e($m['judul']) ?></div>
                        </td>
                        <td>
                            <span class="badge badge-primary mb-1"><?= e($m['kelas_ekskul']) ?></span><br>
                            <span class="text-xs text-muted">Target: <?= e($m['target']) ?></span>
                        </td>
                        <td>
                            <?php 
                                $icon = 'bi-file-earmark';
                                if ($m['tipe'] === 'pdf') $icon = 'bi-filetype-pdf text-danger';
                                if ($m['tipe'] === 'ppt') $icon = 'bi-filetype-ppt text-warning';
                                if ($m['tipe'] === 'video') $icon = 'bi-play-btn text-success';
                            ?>
                            <div><i class="bi <?= $icon ?> me-1"></i> <?= strtoupper($m['tipe']) ?></div>
                            <a href="<?= e($m['link_file']) ?>" target="_blank" class="text-xs text-primary text-decoration-none"><i class="bi bi-box-arrow-up-right"></i> Buka File</a>
                        </td>
                        <td class="text-sm text-muted">
                            <?= date('d M Y', strtotime($m['created_at'])) ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="<?= e(BASE_URL) ?>/pembimbing/materi-form.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="<?= e(BASE_URL) ?>/pembimbing/materi.php?delete=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Hapus materi ini beserta data progres siswa terkait?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
<?php require_once __DIR__ . '/../includes/pembimbing-footer.php'; ?>
