<?php
/**
 * ============================================================
 *  Pembimbing Dashboard — Kelola Tugas
 * ============================================================
 */
$pageTitle = 'Kelola Tugas';
require_once __DIR__ . '/../includes/pembimbing-header.php';

$db = getDB();
$userId = $_SESSION['user_id'];

// Hapus Tugas
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $cek = $db->prepare("SELECT id FROM tugas WHERE id = ? AND dibuat_oleh = ?");
    $cek->execute([$delId, $userId]);
    if ($cek->fetch()) {
        $db->prepare("DELETE FROM tugas WHERE id = ?")->execute([$delId]);
        redirect('/pembimbing/tugas.php?msg=deleted');
    }
}

// Ambil tahun ajaran aktif
$ta = $db->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1")->fetch();
$taId = $ta ? $ta['id'] : 0;
$taLabel = $ta ? ($ta['tahun'] . ' (' . $ta['semester'] . ')') : 'Tidak ada Tahun Ajaran aktif';

// Filter kelas
$stmtKelas = $db->prepare("SELECT kelas_diampu FROM pembimbing_detail WHERE user_id = ?");
$stmtKelas->execute([$userId]);
$kelasDiampuStr = $stmtKelas->fetchColumn();
$kelasDiampuArr = array_filter(array_map('trim', explode(',', $kelasDiampuStr)));

$filterKelas = $_GET['kelas'] ?? '';
if (!in_array($filterKelas, $kelasDiampuArr) && !empty($filterKelas)) {
    $filterKelas = '';
}

// Query Tugas
$sql = "
    SELECT t.*, m.judul AS materi_judul,
           (SELECT COUNT(*) FROM submission_tugas st WHERE st.tugas_id = t.id) as terkumpul
    FROM tugas t
    LEFT JOIN materi m ON t.materi_id = m.id
    WHERE t.dibuat_oleh = ? AND t.tahun_ajaran_id = ?
";
$params = [$userId, $taId];
if ($filterKelas) {
    $sql .= " AND t.kelas_ekskul = ?";
    $params[] = $filterKelas;
}
$sql .= " ORDER BY t.deadline ASC";
$stmtTugas = $db->prepare($sql);
$stmtTugas->execute($params);
$tugasList = $stmtTugas->fetchAll();
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Kelola Tugas</h1>
        <p class="text-secondary mb-0">Tahun Ajaran: <?= e($taLabel) ?></p>
    </div>
    <div>
        <?php if ($taId): ?>
        <a href="<?= e(BASE_URL) ?>/pembimbing/tugas-form.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-2"></i> Tambah Tugas
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
<div class="alert alert-success" data-auto-dismiss="3000">Tugas berhasil dihapus.</div>
<?php endif; ?>
<?php if (isset($_GET['msg']) && $_GET['msg'] === 'saved'): ?>
<div class="alert alert-success" data-auto-dismiss="3000">Tugas berhasil disimpan.</div>
<?php endif; ?>

<?php if (!$taId): ?>
<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle-fill"></i> Tidak bisa mengelola tugas karena tidak ada tahun ajaran yang aktif.
</div>
<?php else: ?>

<div class="panel mb-4">
    <div class="panel-header d-flex justify-content-between align-items-center">
        <h2 class="panel-title">Daftar Tugas</h2>
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
        <?php if (empty($tugasList)): ?>
            <div class="p-4 text-center text-muted">Belum ada tugas.</div>
        <?php else: ?>
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Judul Tugas</th>
                        <th>Kelas / Target</th>
                        <th>Terkumpul</th>
                        <th>Deadline</th>
                        <th width="150" class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tugasList as $t): ?>
                    <?php 
                        $isPast = strtotime($t['deadline']) < time();
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold text-primary-700"><?= e($t['judul']) ?></div>
                            <?php if ($t['materi_judul']): ?>
                                <div class="text-xs text-muted"><i class="bi bi-link-45deg"></i> Materi: <?= e($t['materi_judul']) ?></div>
                            <?php else: ?>
                                <div class="text-xs text-muted">Tugas Mandiri</div>
                            <?php endif; ?>
                            <?php if (!empty($t['link_pengumpulan'])): ?>
                                <div class="text-xs mt-1">
                                    <a href="<?= e($t['link_pengumpulan']) ?>" target="_blank" class="text-info text-decoration-none"><i class="bi bi-box-arrow-up-right me-1"></i>Link Pengumpulan</a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($t['deskripsi'])): ?>
                                <div class="text-xs mt-1 text-muted" style="max-width: 250px;">
                                    <strong>Format:</strong> <?= nl2br(e(truncate($t['deskripsi'], 60))) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge badge-primary mb-1"><?= e($t['kelas_ekskul']) ?></span><br>
                            <span class="text-xs text-muted">Target: <?= e($t['target']) ?></span>
                        </td>
                        <td>
                            <span class="badge badge-info"><?= $t['terkumpul'] ?> Siswa</span>
                        </td>
                        <td>
                            <div class="<?= $isPast ? 'text-danger fw-bold' : '' ?>">
                                <?= date('d M Y, H:i', strtotime($t['deadline'])) ?>
                            </div>
                            <?php if ($isPast): ?>
                                <span class="badge bg-danger text-white text-xs mt-1">Berakhir</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="<?= e(BASE_URL) ?>/pembimbing/tugas-form.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil-square"></i>
                                </a>
                                <a href="<?= e(BASE_URL) ?>/pembimbing/tugas.php?delete=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger" title="Hapus" onclick="return confirm('Hapus tugas ini beserta semua data pengumpulannya?');">
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
