<?php
/**
 * ============================================================
 *  Siswa Dashboard — Notifikasi Peringatan dari Pembimbing
 * ============================================================
 */
$pageTitle = 'Peringatan dari Pembimbing';
require_once __DIR__ . '/../includes/siswa-header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

// Ambil semua peringatan
$stmt = $db->prepare("
    SELECT p.*, u.nama AS nama_pembimbing, pd.kelas_diampu
    FROM peringatan p
    JOIN users u ON p.pembimbing_id = u.id
    LEFT JOIN pembimbing_detail pd ON pd.user_id = u.id
    WHERE p.siswa_id = ?
    ORDER BY p.tanggal DESC, p.id DESC
");
$stmt->execute([$uid]);
$allPeringatan = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1" style="color: var(--clr-primary-700);">Peringatan dari Pembimbing</h1>
        <p class="text-muted mb-0">Catatan peringatan yang diberikan pembimbing untuk kamu.</p>
    </div>
    <?php if (!empty($allPeringatan)): ?>
    <span class="badge badge-danger px-3 py-2 fs-xs">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        <?= count($allPeringatan) ?> Peringatan Total
    </span>
    <?php endif; ?>
</div>

<?php if (empty($allPeringatan)): ?>
<div class="panel">
    <div class="panel-body text-center py-5">
        <i class="bi bi-shield-check" style="font-size: 3rem; color: #22c55e; display: block; margin-bottom: 1rem;"></i>
        <h3 class="fw-bold text-success mb-2">Kamu Bebas Peringatan!</h3>
        <p class="text-muted mb-0">Terus pertahankan kehadiran dan semangat belajar yang baik 🎉</p>
    </div>
</div>
<?php else: ?>

<div class="d-flex flex-column gap-3">
<?php foreach ($allPeringatan as $i => $p):
    $isRecent = strtotime($p['tanggal']) >= strtotime('-30 days');
?>
<div class="panel mb-0 <?= $isRecent ? '' : '' ?>">
    <div class="panel-body py-3">
        <div class="d-flex gap-3 align-items-start">
            <!-- Icon & Count -->
            <div style="
                width: 44px; height: 44px; flex-shrink: 0;
                background: <?= $isRecent ? '#fee2e2' : 'var(--clr-surface-2)' ?>;
                border-radius: var(--radius-lg);
                display: flex; align-items: center; justify-content: center;
                font-size: 1.3rem; color: <?= $isRecent ? '#dc2626' : 'var(--clr-text-muted)' ?>;">
                <i class="bi bi-exclamation-triangle-fill"></i>
            </div>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start mb-1 gap-2 flex-wrap">
                    <div>
                        <span class="fw-semibold text-sm"><?= e($p['nama_pembimbing']) ?></span>
                        <?php if ($p['kelas_diampu']): ?>
                        <span class="text-xs text-muted ms-2">· <?= e($p['kelas_diampu']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($isRecent): ?>
                        <span class="badge badge-danger">Terbaru</span>
                        <?php endif; ?>
                        <span class="text-xs text-muted"><?= date('d M Y', strtotime($p['tanggal'])) ?></span>
                    </div>
                </div>
                <p class="text-sm mb-0 p-3 rounded" style="background: <?= $isRecent ? '#fff8f8' : 'var(--clr-surface-2)' ?>; border-left: 3px solid <?= $isRecent ? '#ef4444' : 'var(--clr-border)' ?>; white-space: pre-wrap;"><?= e($p['alasan']) ?></p>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<div class="mt-4 p-4 text-center" style="background: var(--clr-primary-50); border-radius: var(--radius-xl); border: 1px solid var(--clr-primary-100);">
    <i class="bi bi-lightbulb-fill text-warning fs-4 d-block mb-2"></i>
    <p class="mb-0 text-sm text-primary-700">Peringatan dari pembimbing adalah bentuk kepedulian agar kamu bisa terus berkembang. Yuk jadikan motivasi! 💪</p>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/../includes/siswa-footer.php'; ?>
