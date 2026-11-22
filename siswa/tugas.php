<?php
/**
 * ============================================================
 *  Siswa Dashboard — Tugas & Submit
 * ============================================================
 */
$pageTitle = 'Tugas & Pengumpulan';
require_once __DIR__ . '/../includes/siswa-header.php';

$db  = getDB();
$uid = $_SESSION['user_id'];

$ta   = $db->query("SELECT * FROM tahun_ajaran WHERE status='aktif' LIMIT 1")->fetch();
$taId = $ta['id'] ?? 0;

$success = '';
$error   = '';

// Auto-open submit modal jika ada ?submit=tugas_id
$autoOpenTugas = (int)($_GET['submit'] ?? 0);

// ── Proses Submit Tugas ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'submit_tugas') {
    verifyCsrf();

    $tugasId    = (int)post('tugas_id');
    $jenisTipe  = post('jenis_kumpul');  // zip|foto|video|link
    $linkBukti  = post('link_bukti');

    $validTipe = ['zip', 'foto', 'video', 'link'];
    if (empty($tugasId) || !in_array($jenisTipe, $validTipe) || empty($linkBukti)) {
        $error = 'Semua kolom wajib diisi dengan benar.';
    } else {
        // Pastikan tugas memang untuk kelas yang diikuti siswa ini
        $cek = $db->prepare("
            SELECT t.id FROM tugas t
            JOIN siswa_kelas_ekskul ske ON t.kelas_ekskul = ske.kelas_ekskul
            WHERE t.id = ? AND ske.siswa_id = ? AND t.tahun_ajaran_id = ?
              AND (t.target = 'Semua Jenjang' OR t.target = ?)
        ");
        $cek->execute([$tugasId, $uid, $taId, $siswaInfo['jenjang']]);
        if (!$cek->fetch()) {
            $error = 'Tugas tidak ditemukan atau tidak untuk jenjang kamu.';
        } else {
            // Cek sudah ada submission sebelumnya
            $ada = $db->prepare("SELECT id, status FROM submission_tugas WHERE tugas_id = ? AND siswa_id = ?");
            $ada->execute([$tugasId, $uid]);
            $existing = $ada->fetch();

            if ($existing && $existing['status'] === 'diterima') {
                $error = 'Tugas ini sudah pernah dikumpulkan dan sudah diterima pembimbing.';
            } elseif ($existing) {
                // Update (resubmit)
                $upd = $db->prepare("UPDATE submission_tugas SET jenis_kumpul=?, link_bukti=?, status='menunggu', waktu_submit=CURRENT_TIMESTAMP WHERE id=?");
                $upd->execute([$jenisTipe, $linkBukti, $existing['id']]);
                $success = 'Tugas berhasil dikumpulkan ulang. Menunggu verifikasi pembimbing.';
            } else {
                // Insert baru
                $ins = $db->prepare("INSERT INTO submission_tugas (tugas_id, siswa_id, jenis_kumpul, link_bukti, status) VALUES (?, ?, ?, ?, 'menunggu')");
                $ins->execute([$tugasId, $uid, $jenisTipe, $linkBukti]);
                $success = 'Tugas berhasil dikumpulkan! Menunggu verifikasi pembimbing.';
            }
        }
    }
}

// ── Ambil Semua Tugas ─────────────────────────────────────────
$filterEkskul = $_GET['kelas'] ?? ($ekskulList[0] ?? '');
if (!in_array($filterEkskul, $ekskulList) && !empty($filterEkskul)) {
    $filterEkskul = $ekskulList[0] ?? '';
}

$tugasList = [];
if ($taId && $filterEkskul) {
    $stmt = $db->prepare("
        SELECT t.*,
               m.judul AS materi_judul, m.link_file AS materi_link,
               st.id AS sub_id, st.jenis_kumpul, st.link_bukti, st.status AS sub_status, st.waktu_submit
        FROM tugas t
        LEFT JOIN materi m  ON t.materi_id = m.id
        LEFT JOIN submission_tugas st ON st.tugas_id = t.id AND st.siswa_id = ?
        WHERE t.kelas_ekskul = ? AND t.tahun_ajaran_id = ?
          AND (t.target = 'Semua Jenjang' OR t.target = ?)
        ORDER BY t.deadline ASC
    ");
    $stmt->execute([$uid, $filterEkskul, $taId, $siswaInfo['jenjang']]);
    $tugasList = $stmt->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1" style="color: var(--clr-primary-700);">Tugas & Pengumpulan</h1>
        <p class="text-muted mb-0">Kumpulkan tugas dengan menyertakan link bukti pekerjaan kamu.</p>
    </div>
    <?php if (count($ekskulList) > 1): ?>
    <div class="d-flex gap-2">
        <?php foreach ($ekskulList as $e): ?>
        <a href="?kelas=<?= urlencode($e) ?>" class="btn btn-sm <?= $filterEkskul === $e ? 'btn-primary' : 'btn-outline-primary' ?>">
            <?= e($e) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($success): ?>
<div class="alert alert-success" data-auto-dismiss="4000"><i class="bi bi-check-circle-fill me-2"></i><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= e($error) ?></div>
<?php endif; ?>

<?php if (!$taId): ?>
    <div class="alert alert-warning">Tidak ada tahun ajaran aktif.</div>
<?php elseif (empty($tugasList)): ?>
    <div class="panel"><div class="panel-body text-center text-muted py-5">
        <i class="bi bi-card-checklist fs-1 d-block mb-2"></i>
        Belum ada tugas untuk kelas ini.
    </div></div>
<?php else: ?>

<div class="d-flex flex-column gap-4">
<?php foreach ($tugasList as $t):
    $isPast      = strtotime($t['deadline']) < time();
    $hariSisa    = ceil((strtotime($t['deadline']) - time()) / 86400);
    $sudahKumpul = !empty($t['sub_id']);
    $diterima    = $t['sub_status'] === 'diterima';
    $menunggu    = $t['sub_status'] === 'menunggu';
?>
<div class="panel mb-0 tugas-card" id="tugas-<?= $t['id'] ?>">
    <div class="panel-header" style="background: <?= $diterima ? 'var(--clr-primary-50)' : ($isPast && !$sudahKumpul ? '#fff8f8' : 'var(--clr-surface-2)') ?>;">
        <div>
            <h3 class="fw-bold mb-1 text-sm" style="color: var(--clr-text-primary);"><?= e($t['judul']) ?></h3>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <span class="badge badge-secondary"><?= e($t['kelas_ekskul']) ?></span>
                <span class="badge badge-primary"><?= e($t['target']) ?></span>
                <?php if ($t['materi_judul']): ?>
                    <a href="<?= e($t['materi_link']) ?>" target="_blank" class="badge badge-warning text-decoration-none">
                        <i class="bi bi-link-45deg"></i> Materi: <?= e(truncate($t['materi_judul'], 25)) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <!-- Status badge -->
        <div class="flex-shrink-0 text-end">
            <?php if ($diterima): ?>
                <span class="badge badge-success px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Diterima</span>
            <?php elseif ($menunggu): ?>
                <span class="badge badge-warning px-3 py-2"><i class="bi bi-hourglass-split me-1"></i> Menunggu Verifikasi</span>
            <?php elseif ($isPast): ?>
                <span class="badge badge-danger px-3 py-2"><i class="bi bi-x-circle me-1"></i> Deadline Lewat</span>
            <?php elseif ($hariSisa <= 3): ?>
                <span class="badge badge-warning px-3 py-2"><i class="bi bi-alarm me-1"></i> <?= $hariSisa ?> Hari Lagi</span>
            <?php else: ?>
                <span class="badge badge-secondary px-3 py-2"><i class="bi bi-clock me-1"></i> <?= $hariSisa ?> Hari Lagi</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel-body py-3">
        <div class="row align-items-center">
            <div class="col-md-6">
                <div class="text-sm mb-1">
                    <i class="bi bi-calendar-event me-1 text-muted"></i>
                    <span class="text-muted">Deadline:</span>
                    <strong class="<?= $isPast ? 'text-danger' : '' ?> ms-1">
                        <?= date('d M Y, H:i', strtotime($t['deadline'])) ?>
                    </strong>
                </div>
                <?php if (!empty($t['deskripsi'])): ?>
                <div class="text-sm mt-2 text-muted">
                    <strong>Format / Instruksi:</strong><br>
                    <?= nl2br(e($t['deskripsi'])) ?>
                </div>
                <?php endif; ?>
                <?php if ($sudahKumpul): ?>
                <div class="text-sm">
                    <i class="bi bi-upload me-1 text-muted"></i>
                    <span class="text-muted">Dikumpulkan:</span>
                    <strong class="ms-1"><?= date('d M Y, H:i', strtotime($t['waktu_submit'])) ?></strong>
                    · <span class="badge badge-secondary"><?= strtoupper($t['jenis_kumpul']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6 d-flex justify-content-md-end gap-2 mt-2 mt-md-0">
                <?php if (!empty($t['link_pengumpulan'])): ?>
                    <a href="<?= e($t['link_pengumpulan']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-box-arrow-up-right me-1"></i> Buka Link Pengumpulan
                    </a>
                <?php endif; ?>
                <?php if (!$diterima): ?>
                    <button type="button"
                        class="btn btn-sm <?= $sudahKumpul ? 'btn-outline-warning' : 'btn-primary' ?> btn-submit-tugas"
                        data-tugas-id="<?= $t['id'] ?>"
                        data-tugas-judul="<?= e($t['judul']) ?>"
                        data-jenis="link"
                        data-link="Eksternal">
                        <i class="bi bi-<?= $sudahKumpul ? 'arrow-repeat' : 'check2-all' ?> me-1"></i>
                        <?= $sudahKumpul ? 'Tandai Ulang Selesai' : 'Tandai Selesai' ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php endif; ?>

<!-- ── Modal Submit Tugas ──────────────────────────────── -->
<div class="modal-overlay" id="modal-submit" style="display:none;">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3 class="modal-title">Tandai Tugas Selesai</h3>
            <button type="button" class="btn-icon" onclick="document.getElementById('modal-submit').style.display='none'">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="submit_tugas">
            <input type="hidden" name="tugas_id" id="modal-tugas-id">
            <!-- Hidden defaults -->
            <input type="hidden" name="jenis_kumpul" value="link">
            <input type="hidden" name="link_bukti" value="Eksternal">

            <div class="modal-body text-center py-4">
                <i class="bi bi-check-circle text-primary-500 mb-3 d-inline-block" style="font-size: 3rem;"></i>
                <h4 class="fw-bold mb-2">Konfirmasi Pengumpulan</h4>
                <p class="text-muted mb-0">Tugas: <strong id="modal-tugas-judul"></strong></p>
                <p class="text-muted mt-2 text-sm">Pastikan kamu sudah mengupload file tugasmu di link pengumpulan yang disediakan oleh pembimbing.</p>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: var(--sp-2);">
                <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('modal-submit').style.display='none'">Batal</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-all me-1"></i> Ya, Tandai Selesai</button>
            </div>
        </form>
    </div>
</div>

<style>
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 100; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.modal-content { background: white; border-radius: var(--radius-xl); box-shadow: var(--shadow-xl); width: 100%; overflow: hidden; }
.modal-header  { display: flex; align-items: center; justify-content: space-between; padding: var(--sp-4) var(--sp-5); border-bottom: 1px solid var(--clr-border); background: var(--clr-surface-2); }
.modal-title   { font-family: var(--font-heading); font-weight: var(--fw-bold); font-size: var(--fs-lg); margin: 0; }
.modal-body    { padding: var(--sp-5); }
.modal-footer  { padding: var(--sp-4) var(--sp-5); border-top: 1px solid var(--clr-border); background: var(--clr-surface-2); }
</style>

<script>
document.querySelectorAll('.btn-submit-tugas').forEach(btn => {
    btn.addEventListener('click', () => {
        const id    = btn.dataset.tugasId;
        const judul = btn.dataset.tugasJudul;

        document.getElementById('modal-tugas-id').value    = id;
        document.getElementById('modal-tugas-judul').textContent = judul;

        document.getElementById('modal-submit').style.display = 'flex';
    });
});

// Auto-open if ?submit=id
<?php if ($autoOpenTugas): ?>
const autoBtn = document.querySelector('[data-tugas-id="<?= $autoOpenTugas ?>"]');
if (autoBtn) autoBtn.click();
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/siswa-footer.php'; ?>
