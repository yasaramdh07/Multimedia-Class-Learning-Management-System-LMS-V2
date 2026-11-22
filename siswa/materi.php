<?php
/**
 * ============================================================
 *  Siswa Dashboard — Roadmap Materi + Ceklis Progress
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_SISWA);

$db  = getDB();
$uid = $_SESSION['user_id'];

$ta   = $db->query("SELECT * FROM tahun_ajaran WHERE status='aktif' LIMIT 1")->fetch();
$taId = $ta['id'] ?? 0;

// Handle ceklis AJAX / POST dari siswa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ceklis_materi') {
    header('Content-Type: application/json');
    verifyCsrf();
    $materiId = (int)$_POST['materi_id'];
    $status   = (int)$_POST['status']; // 0 = batal, 1 = selesai

    // Get jenjang siswa
    $stmtJenjang = $db->prepare("SELECT jenjang FROM siswa_detail WHERE user_id = ?");
    $stmtJenjang->execute([$uid]);
    $jenjang = $stmtJenjang->fetchColumn();

    // Pastikan materi memang ada dan untuk kelas yang diikuti siswa ini
    $cek = $db->prepare("
        SELECT m.id FROM materi m
        JOIN siswa_kelas_ekskul ske ON m.kelas_ekskul = ske.kelas_ekskul
        WHERE m.id = ? AND ske.siswa_id = ? AND m.tahun_ajaran_id = ?
          AND (m.target = 'Semua Jenjang' OR m.target = ?)
    ");
    $cek->execute([$materiId, $uid, $taId, $jenjang]);
    if ($cek->fetch()) {
        $upsert = $db->prepare("
            INSERT INTO progress_materi (siswa_id, materi_id, status_ceklis, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(siswa_id, materi_id) DO UPDATE SET status_ceklis = excluded.status_ceklis, updated_at = CURRENT_TIMESTAMP
        ");
        $upsert->execute([$uid, $materiId, $status]);
        ob_clean();
        echo json_encode(['ok' => true]);
    } else {
        http_response_code(403);
        ob_clean();
        echo json_encode(['ok' => false, 'msg' => 'Tidak diizinkan.']);
    }
    exit;
}

$pageTitle = 'Materi Pelajaran';
require_once __DIR__ . '/../includes/siswa-header.php';
// Ambil materi per kelas ekskul
$filterEkskul = $_GET['kelas'] ?? ($ekskulList[0] ?? '');
if (!in_array($filterEkskul, $ekskulList) && !empty($filterEkskul)) {
    $filterEkskul = $ekskulList[0] ?? '';
}

$materiList = [];
if ($taId && $filterEkskul) {
    $stmt = $db->prepare("
        SELECT m.*,
               COALESCE(pm.status_ceklis, 0) AS sudah_ceklis
        FROM materi m
        LEFT JOIN progress_materi pm ON pm.materi_id = m.id AND pm.siswa_id = ?
        WHERE m.kelas_ekskul = ? AND m.tahun_ajaran_id = ?
          AND (m.target = 'Semua Jenjang' OR m.target = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$uid, $filterEkskul, $taId, $siswaInfo['jenjang']]);
    $materiList = $stmt->fetchAll();
}

$totalMateri  = count($materiList);
$sudahCeklis  = count(array_filter($materiList, fn($m) => $m['sudah_ceklis']));
$pctCeklis    = $totalMateri > 0 ? round($sudahCeklis / $totalMateri * 100) : 0;
?>

<div class="d-flex justify-content-between align-items-end mb-4 flex-wrap gap-3">
    <div>
        <h1 class="h3 fw-bold mb-1" style="color: var(--clr-primary-700);">Roadmap Materi</h1>
        <p class="text-muted mb-0">Pelajari materi dari pembimbing dan ceklis setelah selesai dibaca/ditonton.</p>
    </div>
    <?php if (count($ekskulList) > 1): ?>
    <div class="d-flex gap-2">
        <?php foreach ($ekskulList as $e): ?>
        <a href="?kelas=<?= urlencode($e) ?>" class="btn btn-sm <?= $filterEkskul === $e ? 'btn-primary' : 'btn-outline-primary' ?>">
            <?= htmlspecialchars($e) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (!$taId): ?>
    <div class="alert alert-warning">Tidak ada tahun ajaran aktif saat ini.</div>
<?php elseif (empty($materiList)): ?>
    <div class="panel"><div class="panel-body text-center text-muted py-5">
        <i class="bi bi-journal-x fs-1 d-block mb-2 text-muted"></i>
        Belum ada materi yang diunggah oleh pembimbing untuk kelas ini.
    </div></div>
<?php else: ?>

<!-- Progress bar keseluruhan -->
<div class="panel mb-4">
    <div class="panel-body py-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold text-sm">Progress Materi – <?= e($filterEkskul) ?></span>
            <span class="badge badge-<?= $pctCeklis >= 75 ? 'success' : ($pctCeklis >= 40 ? 'warning' : 'danger') ?> px-3">
                <?= $sudahCeklis ?>/<?= $totalMateri ?> (<?= $pctCeklis ?>%)
            </span>
        </div>
        <div style="height: 12px; background: var(--clr-surface-2); border-radius: 99px; overflow: hidden;">
            <div id="materi-progress-bar" style="width: <?= $pctCeklis ?>%; height: 100%; background: linear-gradient(90deg, var(--clr-primary-400), var(--clr-primary-600)); border-radius: 99px; transition: width 0.8s ease;"></div>
        </div>
    </div>
</div>

<!-- Roadmap cards -->
<div class="materi-roadmap">
    <?php foreach ($materiList as $i => $m):
        $icons = ['pdf' => 'bi-filetype-pdf text-danger', 'ppt' => 'bi-filetype-ppt text-warning', 'video' => 'bi-play-btn-fill text-success'];
        $ic    = $icons[$m['tipe']] ?? 'bi-file-earmark';
    ?>
    <div class="roadmap-item <?= $m['sudah_ceklis'] ? 'done' : '' ?>" id="roadmap-<?= $m['id'] ?>">
        <!-- Connector line -->
        <?php if ($i < $totalMateri - 1): ?>
        <div class="roadmap-line"></div>
        <?php endif; ?>

        <!-- Dot -->
        <div class="roadmap-dot <?= $m['sudah_ceklis'] ? 'done' : '' ?>" data-index="<?= ($i + 1) ?>">
            <?= $m['sudah_ceklis'] ? '<i class="bi bi-check-lg"></i>' : ($i + 1) ?>
        </div>

        <!-- Card -->
        <div class="roadmap-card">
            <div class="d-flex align-items-start gap-3">
                <i class="bi <?= $ic ?> fs-2 flex-shrink-0 mt-1"></i>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                        <div>
                            <h3 class="fw-semibold text-primary-700 mb-1 fs-6"><?= e($m['judul']) ?></h3>
                            <div class="d-flex gap-2 flex-wrap">
                                <span class="badge badge-secondary"><?= strtoupper($m['tipe']) ?></span>
                                <span class="badge badge-primary"><?= e($m['target']) ?></span>
                                <span class="text-xs text-muted mt-1">Ditambahkan <?= date('d M Y', strtotime($m['created_at'])) ?></span>
                            </div>
                        </div>
                        <div class="d-flex gap-2 align-items-center flex-shrink-0">
                            <a href="<?= e($m['link_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Buka Materi
                            </a>
                            <!-- Ceklis toggle -->
                            <button
                                class="btn btn-sm <?= $m['sudah_ceklis'] ? 'btn-success' : 'btn-outline-secondary' ?> ceklis-btn"
                                data-materi-id="<?= $m['id'] ?>"
                                data-status="<?= $m['sudah_ceklis'] ? '1' : '0' ?>"
                                title="<?= $m['sudah_ceklis'] ? 'Tandai Belum Selesai' : 'Tandai Sudah Selesai' ?>">
                                <i class="bi bi-<?= $m['sudah_ceklis'] ? 'check2-circle' : 'circle' ?>"></i>
                                <?= $m['sudah_ceklis'] ? 'Sudah Dibaca' : 'Ceklis Selesai' ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endif; ?>

<style>
.materi-roadmap { display: flex; flex-direction: column; gap: 0; }
.roadmap-item {
    display: flex;
    gap: 20px;
    position: relative;
    padding-bottom: 24px;
}
.roadmap-line {
    position: absolute;
    left: 19px;
    top: 40px;
    bottom: 0;
    width: 2px;
    background: var(--clr-border);
}
.roadmap-item.done .roadmap-line { background: var(--clr-primary-300); }
.roadmap-dot {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: white;
    border: 2px solid var(--clr-border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    color: var(--clr-text-muted);
    flex-shrink: 0;
    z-index: 1;
}
.roadmap-dot.done {
    background: var(--clr-primary-600);
    border-color: var(--clr-primary-600);
    color: white;
}
.roadmap-card {
    flex: 1;
    background: white;
    border: 1px solid var(--clr-border);
    border-radius: var(--radius-xl);
    padding: var(--sp-4) var(--sp-5);
    box-shadow: var(--shadow-sm);
    transition: box-shadow 0.2s;
}
.roadmap-item.done .roadmap-card {
    border-color: var(--clr-primary-200);
    background: var(--clr-primary-50);
}
.roadmap-card:hover { box-shadow: var(--shadow-md); }
.ceklis-btn { transition: all 0.2s; }
</style>

<script>
const BASE_URL = document.querySelector('meta[name="base-url"]')?.content ?? '';
const CSRF_TOKEN = '<?= e(csrfToken()) ?>';

document.querySelectorAll('.ceklis-btn').forEach(btn => {
    btn.addEventListener('click', async function() {
        const materiId = this.dataset.materiId;
        const curStatus = parseInt(this.dataset.status);
        const newStatus = curStatus === 1 ? 0 : 1;

        this.disabled = true;

        const fd = new FormData();
        fd.append('action', 'ceklis_materi');
        fd.append('materi_id', materiId);
        fd.append('status', newStatus);
        fd.append('csrf_token', CSRF_TOKEN);

        const res = await fetch(BASE_URL + '/siswa/materi.php', { method: 'POST', body: fd });
        const json = await res.json();

        if (json.ok) {
            this.dataset.status = newStatus;
            const item = document.getElementById('roadmap-' + materiId);
            const dot  = item.querySelector('.roadmap-dot');
            const line = item.querySelector('.roadmap-line');

            if (newStatus === 1) {
                this.className = 'btn btn-sm btn-success ceklis-btn';
                this.innerHTML = '<i class="bi bi-check2-circle"></i> Sudah Dibaca';
                item.classList.add('done');
                dot.classList.add('done');
                dot.innerHTML = '<i class="bi bi-check-lg"></i>';
                if (line) line.style.background = 'var(--clr-primary-300)';
            } else {
                this.className = 'btn btn-sm btn-outline-secondary ceklis-btn';
                this.innerHTML = '<i class="bi bi-circle"></i> Ceklis Selesai';
                item.classList.remove('done');
                dot.classList.remove('done');
                // Restore nomor
                dot.innerHTML = dot.dataset.index;
                if (line) line.style.background = '';
            }

            // Update progress bar (hitung ulang dari DOM)
            const total = document.querySelectorAll('.ceklis-btn').length;
            const done  = document.querySelectorAll('.ceklis-btn.btn-success').length;
            const pct   = total > 0 ? Math.round(done / total * 100) : 0;
            const bar   = document.getElementById('materi-progress-bar');
            if (bar) bar.style.width = pct + '%';
        }

        this.disabled = false;
    });
});
</script>

<?php require_once __DIR__ . '/../includes/siswa-footer.php'; ?>
