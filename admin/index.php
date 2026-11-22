<?php
/**
 * ============================================================
 *  Admin Dashboard — Halaman Utama
 * ============================================================
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole(ROLE_ADMIN);
$pageTitle = 'Dashboard Admin — ' . APP_NAME;

$db = getDB();

$success = '';
$error = '';

// Proses Factory Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'factory_reset') {
    verifyCsrf();
    $pin = post('pin');
    
    if ($pin === '060923') {
        $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'app_settings'")->fetchAll(PDO::FETCH_COLUMN);
        
        try {
            $db->exec('PRAGMA foreign_keys = OFF');
            $db->beginTransaction();
            
            foreach ($tables as $table) {
                $db->exec("DELETE FROM $table");
            }
            
            $db->exec("DELETE FROM sqlite_sequence WHERE name != 'app_settings'");
            
            $db->commit();
            $db->exec('PRAGMA foreign_keys = ON');
            
            // Hapus semua sesi aktif (force logout semua user)
            $sessionPath = session_save_path() ?: sys_get_temp_dir();
            $sessionFiles = glob($sessionPath . DIRECTORY_SEPARATOR . 'sess_*');
            if ($sessionFiles) {
                foreach ($sessionFiles as $sf) {
                    @unlink($sf);
                }
            }

            // Hancurkan sesi admin saat ini, lalu redirect ke login dengan pesan
            session_unset();
            session_destroy();
            header('Location: ' . BASE_URL . '/index.php?reset=1');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $db->exec('PRAGMA foreign_keys = ON');
            $error = 'Gagal mereset database: ' . $e->getMessage();
        }
    } else {
        $error = 'PIN tidak valid. Factory reset dibatalkan.';
    }
}

// 1. Ambil Statistik
$totalSiswa = $db->query("SELECT COUNT(*) FROM users WHERE role = 'siswa' AND status = 'approved'")->fetchColumn();
$totalPembimbing = $db->query("SELECT COUNT(*) FROM users WHERE role = 'pembimbing' AND status = 'approved'")->fetchColumn();
$pendingUsers = $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();

// Ambil Tahun Ajaran Aktif
$taAktif = $db->query("SELECT tahun, semester FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1")->fetch();
$taText = $taAktif ? ($taAktif['tahun'] . ' - ' . $taAktif['semester']) : 'Belum Ada yang Aktif';

// 2. Ambil user online (last_seen < 5 menit yang lalu)
$limaMenitLalu = date('Y-m-d H:i:s', strtotime('-5 minutes'));
$stmtOnline = $db->prepare("
    SELECT u.nama, u.role, u.foto_profil_link, a.last_seen 
    FROM activity_log a
    JOIN users u ON a.user_id = u.id
    WHERE a.last_seen >= ?
    ORDER BY a.last_seen DESC
    LIMIT 10
");
$stmtOnline->execute([$limaMenitLalu]);
$onlineUsers = $stmtOnline->fetchAll();

// 3. Ambil pendaftar terbaru (Pending)
$stmtPending = $db->query("
    SELECT id, nama, role, email, created_at 
    FROM users 
    WHERE status = 'pending' 
    ORDER BY created_at ASC 
    LIMIT 5
");
$recentPending = $stmtPending->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> <?= e($success) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary-700 mb-1">Selamat Datang, Admin!</h1>
        <p class="text-secondary mb-0">Tahun Ajaran Aktif: <strong><?= e($taText) ?></strong></p>
    </div>
    <div>
        <a href="<?= e(BASE_URL) ?>/admin/tahun-ajaran.php" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-gear"></i> Set Tahun Ajaran
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="bi bi-people-fill"></i></div>
        <div class="stat-info">
            <div class="stat-label">Total Siswa Aktif</div>
            <div class="stat-value"><?= number_format($totalSiswa) ?></div>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success"><i class="bi bi-person-badge-fill"></i></div>
        <div class="stat-info">
            <div class="stat-label">Total Pembimbing</div>
            <div class="stat-value"><?= number_format($totalPembimbing) ?></div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon warning"><i class="bi bi-person-lines-fill"></i></div>
        <div class="stat-info">
            <div class="stat-label">Menunggu Approval</div>
            <div class="stat-value"><?= number_format($pendingUsers) ?></div>
        </div>
        <?php if ($pendingUsers > 0): ?>
        <a href="<?= e(BASE_URL) ?>/admin/pendaftar.php" class="stretched-link" aria-label="Lihat Pendaftar" style="position: absolute; top:0; left:0; right:0; bottom:0;"></a>
        <?php endif; ?>
    </div>
</div>

<div class="row" style="display: flex; flex-wrap: wrap; gap: var(--sp-6);">
    <!-- Col Kiri -->
    <div style="flex: 2; min-width: 300px;">
        <div class="panel">
            <div class="panel-header d-flex justify-content-between align-items-center">
                <h2 class="panel-title mb-0"><i class="bi bi-person-plus"></i> Pendaftar Menunggu</h2>
                <div class="d-flex gap-2">
                    <input type="text" class="form-control form-control-sm table-search" placeholder="Cari pendaftar..." style="max-width: 150px;">
                    <a href="<?= e(BASE_URL) ?>/admin/pendaftar.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>
            </div>
            <div class="panel-body p-0 table-responsive">
                <?php if (empty($recentPending)): ?>
                    <div class="p-4 text-center text-muted">Tidak ada pendaftar baru yang menunggu.</div>
                <?php else: ?>
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Nama</th>
                                <th>Role</th>
                                <th>Tanggal Daftar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPending as $u): ?>
                            <tr>
                                <td class="fw-semibold text-primary-700"><?= e($u['nama']) ?></td>
                                <td>
                                    <?php if ($u['role'] === 'siswa'): ?>
                                        <span class="badge badge-primary">Siswa</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Pembimbing</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-secondary"><?= date('d M Y, H:i', strtotime($u['created_at'])) ?></td>
                                <td>
                                    <a href="<?= e(BASE_URL) ?>/admin/pendaftar.php" class="btn btn-sm btn-primary py-1 px-2">Tinjau</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Col Kanan -->
    <div style="flex: 1; min-width: 250px;">
        <div class="panel">
            <div class="panel-header">
                <h2 class="panel-title"><i class="bi bi-broadcast"></i> Sedang Online</h2>
            </div>
            <div class="panel-body">
                <div class="text-xs text-muted mb-3">Aktif dalam 5 menit terakhir</div>
                
                <?php if (empty($onlineUsers)): ?>
                    <div class="text-center text-muted py-3">Tidak ada user yang sedang online saat ini.</div>
                <?php else: ?>
                    <div style="display:flex; flex-direction:column; gap: var(--sp-3);">
                        <?php foreach ($onlineUsers as $ou): ?>
                        <div style="display:flex; align-items:center; gap: var(--sp-3);">
                            <div class="user-avatar" style="width:40px; height:40px; overflow: hidden;">
                                <?php if ($ou['foto_profil_link']): ?>
                                    <img src="<?= e($ou['foto_profil_link']) ?>" alt="<?= e($ou['nama']) ?>" onerror="this.style.display='none'">
                                <?php else: ?>
                                    <i class="bi bi-person-fill"></i>
                                <?php endif; ?>
                            </div>
                            <div style="flex:1; min-width:0;">
                                <div class="fw-semibold text-primary-700" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?= e($ou['nama']) ?>
                                </div>
                                <div class="text-xs text-muted">
                                    <span class="status-dot online"></span>
                                    <?= ucfirst(e($ou['role'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="panel border-danger mt-4" style="border: 2px solid var(--clr-danger); background-color: #fff5f5;">
    <div class="panel-header d-flex justify-content-between align-items-center" style="border-bottom-color: #fecdd3;">
        <h2 class="panel-title text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i> Zona Berbahaya (Factory Reset)</h2>
    </div>
    <div class="panel-body">
        <p class="text-danger mb-3"><strong>Peringatan!</strong> Tindakan ini akan menghapus <strong>SELURUH</strong> data di database (Siswa, Pembimbing, Materi, Tugas, Nilai, dll) secara permanen. Tindakan ini tidak dapat dibatalkan!</p>
        <form method="POST" onsubmit="return confirm('APAKAH ANDA YAKIN? SEMUA DATA AKAN HILANG PERMANEN!');" class="d-flex gap-2 align-items-center flex-wrap">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="factory_reset">
            <input type="password" name="pin" class="form-control form-control-sm" style="max-width: 200px;" placeholder="Masukkan PIN untuk reset" required autocomplete="off">
            <button type="submit" class="btn btn-danger btn-sm">
                <i class="bi bi-trash3-fill"></i> Factory Reset
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
