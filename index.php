<?php
/**
 * ============================================================
 *  Multimedia Class — index.php  (Login + Router)
 * ============================================================
 *  Deteksi role otomatis dari tab yang dipilih.
 *  Admin   → cek app_settings (username/password plain)
 *  Pembimbing/Siswa → cek tabel users (password plain)
 * ============================================================
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Sudah login → ke dashboard
if (isLoggedIn()) {
    redirectToDashboard();
}

$error   = '';
$success = '';
$role    = 'siswa'; // default tab

// Tampilkan pesan setelah factory reset
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $success = 'Factory Reset berhasil. Seluruh data telah dihapus. Silakan login kembali.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $role     = post('role', 'siswa');
    $login_id = post('login_id', '');
    $password = post('password', '');
    $username = post('username', ''); // This is for Admin login

    // ── Login Admin ──────────────────────────────────────────
    if ($role === 'admin') {
        $db = getDB();
        $adminUser = $db->query("SELECT value FROM app_settings WHERE key='admin_username'")->fetchColumn();
        $adminPass = $db->query("SELECT value FROM app_settings WHERE key='admin_password'")->fetchColumn();

        if ($username === $adminUser && $password === $adminPass) {
            // Buat sesi admin virtual (tidak ada di tabel users)
            if (session_status() === PHP_SESSION_NONE) session_start();
            session_regenerate_id(true);
            $_SESSION['user_id'] = 0;       // 0 = admin
            $_SESSION['role']    = 'admin';
            $_SESSION['nama']    = 'Admin';
            $_SESSION['email']   = 'admin@internal';
            header('Location: ' . BASE_URL . '/admin/index.php');
            exit;
        } else {
            $error = 'Username atau password admin salah.';
        }

    // ── Login Pembimbing / Siswa ─────────────────────────────
    } else {
        if (empty($login_id) || empty($password)) {
            $error = 'Username/Email dan password wajib diisi.';
        } else {
            $db   = getDB();
            $stmt = $db->prepare(
                "SELECT id, role, nama, email, password, status FROM users
                 WHERE (email = ? OR username = ?) AND role = ? LIMIT 1"
            );
            $stmt->execute([$login_id, $login_id, $role]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Email tidak ditemukan atau role tidak sesuai.';
            } elseif ($user['password'] !== $password) {
                $error = 'Password salah.';
            } elseif ($user['status'] === STATUS_PENDING) {
                $error = '__PENDING__'; // ditangani khusus di HTML
            } elseif ($user['status'] === STATUS_REJECTED) {
                $error = 'Pendaftaran kamu telah ditolak oleh admin. Silakan hubungi pembimbing untuk informasi lebih lanjut.';
            } else {
                createSession($user);
                logActivity((int)$user['id'], true);
                redirectToDashboard();
            }
        }
    }
}

$pageTitle = APP_NAME . ' — Login';
require_once __DIR__ . '/includes/auth-header.php';
?>

<main class="auth-wrapper" style="position:relative;z-index:1; margin-top: auto; margin-bottom: auto;">
    <div class="auth-card">

        <!-- Brand -->
        <div class="auth-brand">
            <img src="<?= e(BASE_URL . LOGO_PATH) ?>"
                 alt="Logo Multimedia Yabika"
                 class="auth-logo">
            <div class="auth-school-name">MTs &amp; MA YABIKA</div>
        </div>

        <div class="auth-title">Masuk ke Akun</div>
        <div class="auth-subtitle">Pilih role kamu lalu masukkan kredensial.</div>

        <!-- Tab Role Switcher -->
        <div class="auth-tabs" role="tablist">
            <button class="auth-tab <?= $role === 'siswa' ? 'active' : '' ?>" data-tab="siswa" role="tab" id="tab-siswa">
                <i class="bi bi-person-fill"></i> Siswa
            </button>
            <button class="auth-tab <?= $role === 'pembimbing' ? 'active' : '' ?>" data-tab="pembimbing" role="tab" id="tab-pembimbing">
                <i class="bi bi-person-badge-fill"></i> Pembimbing
            </button>
            <button class="auth-tab <?= $role === 'admin' ? 'active' : '' ?>" data-tab="admin" role="tab" id="tab-admin">
                <i class="bi bi-shield-lock-fill"></i> Admin
            </button>
        </div>

        <!-- Error / Pending Alert -->
        <?php if ($error === '__PENDING__'): ?>
        <div class="status-pending-banner" role="alert">
            <i class="bi bi-clock-history"></i>
            <div class="status-pending-banner-text">
                <strong>Akun Kamu Sedang Menunggu Verifikasi</strong>
                <span>Pendaftaranmu sudah diterima. Admin akan segera memeriksa dan menyetujui akunmu. Silakan tunggu notifikasi atau hubungi pembimbing kamu.</span>
            </div>
        </div>
        <?php elseif ($error): ?>
        <div class="alert alert-danger" role="alert" data-auto-dismiss="6000">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success" role="alert" data-auto-dismiss="5000">
            <i class="bi bi-check-circle-fill"></i>
            <span><?= e($success) ?></span>
        </div>
        <?php endif; ?>

        <!-- ── TAB: SISWA ── -->
        <div class="tab-panel" data-tab-panel="siswa" <?= $role !== 'siswa' ? 'hidden' : '' ?>>
            <form method="POST" action="" data-loading id="form-siswa">
                <?= csrfField() ?>
                <input type="hidden" name="role" id="role-input" value="siswa">

                <div class="form-group">
                    <label class="form-label" for="login-siswa">Username / Email</label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-person"></i>
                        <input type="text" id="login-siswa" name="login_id" class="form-control"
                               placeholder="Username atau email@siswa.com" autocomplete="username"
                               value="<?= ($role === 'siswa') ? e($_POST['login_id'] ?? '') : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pw-siswa">Password</label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-lock"></i>
                        <input type="password" id="pw-siswa" name="password" class="form-control"
                               placeholder="••••••••" autocomplete="current-password">
                        <button type="button" class="toggle-pw" aria-label="Tampilkan password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block" id="login-siswa-btn">
                    <span class="btn-text">Masuk</span>
                    <span class="spinner d-none" aria-hidden="true"></span>
                </button>
            </form>

            <div class="auth-links">
                <div class="auth-link-row">
                    Belum punya akun?
                    <a href="<?= e(BASE_URL) ?>/siswa/register.php">Daftar sebagai Siswa</a>
                </div>
                <div class="auth-link-row">
                    <a href="<?= e(BASE_URL) ?>/auth/lupa-password.php">Lupa password?</a>
                </div>
            </div>
        </div>

        <!-- ── TAB: PEMBIMBING ── -->
        <div class="tab-panel" data-tab-panel="pembimbing" <?= $role !== 'pembimbing' ? 'hidden' : '' ?>>
            <form method="POST" action="" data-loading id="form-pembimbing">
                <?= csrfField() ?>
                <input type="hidden" name="role" value="pembimbing">

                <div class="form-group">
                    <label class="form-label" for="login-pembimbing">Username / Email</label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-person"></i>
                        <input type="text" id="login-pembimbing" name="login_id" class="form-control"
                               placeholder="Username atau email@pembimbing.com" autocomplete="username"
                               value="<?= ($role === 'pembimbing') ? e($_POST['login_id'] ?? '') : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pw-pembimbing">Password</label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-lock"></i>
                        <input type="password" id="pw-pembimbing" name="password" class="form-control"
                               placeholder="••••••••" autocomplete="current-password">
                        <button type="button" class="toggle-pw" aria-label="Tampilkan password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block" id="login-pembimbing-btn">
                    <span class="btn-text">Masuk</span>
                    <span class="spinner d-none" aria-hidden="true"></span>
                </button>
            </form>

            <div class="auth-links">
                <div class="auth-link-row">
                    Belum punya akun?
                    <a href="<?= e(BASE_URL) ?>/pembimbing/register.php">Daftar sebagai Pembimbing</a>
                </div>
                <div class="auth-link-row">
                    <a href="<?= e(BASE_URL) ?>/auth/lupa-password.php">Lupa password?</a>
                </div>
            </div>
        </div>

        <!-- ── TAB: ADMIN ── -->
        <div class="tab-panel" data-tab-panel="admin" <?= $role !== 'admin' ? 'hidden' : '' ?>>
            <form method="POST" action="" data-loading id="form-admin">
                <?= csrfField() ?>
                <input type="hidden" name="role" value="admin">

                <div class="form-group">
                    <label class="form-label" for="username-admin">Username Admin</label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-person-lock"></i>
                        <input type="text" id="username-admin" name="username" class="form-control"
                               placeholder="Admin_Multimedia" autocomplete="username"
                               value="<?= ($role === 'admin') ? e($_POST['username'] ?? '') : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="pw-admin">Password Admin</label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-shield-lock"></i>
                        <input type="password" id="pw-admin" name="password" class="form-control"
                               placeholder="••••••••" autocomplete="current-password">
                        <button type="button" class="toggle-pw" aria-label="Tampilkan password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block" id="login-admin-btn"
                        style="background-color: var(--clr-role-admin); border-color: var(--clr-role-admin);">
                    <span class="btn-text"><i class="bi bi-shield-check me-1"></i> Masuk sebagai Admin</span>
                    <span class="spinner d-none" aria-hidden="true"></span>
                </button>
            </form>
        </div>

    </div><!-- /.auth-card -->
</main>

<script>
// Sinkronisasi hidden role input saat tab diklik
document.querySelectorAll('.auth-tab').forEach(tab => {
    tab.addEventListener('click', () => {
        // Update semua hidden role input
        document.querySelectorAll('input[name="role"]').forEach(inp => {
            inp.value = tab.dataset.tab;
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/auth-footer.php'; ?>
