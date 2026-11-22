<?php
/**
 * ============================================================
 *  Auth — Lupa Password (Ajukan Reset ke Admin)
 * ============================================================
 *  Berlaku untuk pembimbing & siswa.
 *  User memasukkan email + password baru → masuk antrian.
 *  Admin approve → password langsung berubah.
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) redirectToDashboard();

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $email      = post('email', '');
    $new_pass   = post('new_password', '');
    $pw_confirm = post('password_confirm', '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (strlen($new_pass) < 6) $errors[] = 'Password baru minimal 6 karakter.';
    if ($new_pass !== $pw_confirm) $errors[] = 'Konfirmasi password tidak cocok.';

    if (empty($errors)) {
        $db = getDB();
        $stmt = $db->prepare(
            "SELECT id, role, nama, status FROM users WHERE email = ? AND role IN ('pembimbing','siswa') LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            $errors[] = 'Email tidak ditemukan. Pastikan email sesuai dengan yang didaftarkan.';
        } else {
            // Cek apakah sudah ada pengajuan yang masih menunggu
            $existing = $db->prepare(
                "SELECT id FROM password_reset_requests WHERE user_id = ? AND status = 'menunggu' LIMIT 1"
            );
            $existing->execute([$user['id']]);

            if ($existing->fetch()) {
                $errors[] = 'Kamu sudah punya pengajuan reset password yang sedang menunggu approval admin. Silakan tunggu.';
            } else {
                $ins = $db->prepare(
                    "INSERT INTO password_reset_requests (user_id, new_password) VALUES (?, ?)"
                );
                $ins->execute([$user['id'], $new_pass]);
                $success = true;
            }
        }
    }
}

$pageTitle = 'Lupa Password — ' . APP_NAME;
require_once __DIR__ . '/../includes/auth-header.php';
?>

<main class="auth-wrapper" style="position:relative;z-index:1; margin-top:auto; margin-bottom:auto;">

    <a href="<?= e(BASE_URL) ?>/" class="auth-back">
        <i class="bi bi-arrow-left"></i> Kembali ke Login
    </a>

    <div class="auth-card">

        <div class="auth-brand">
            <img src="<?= e(BASE_URL . LOGO_PATH) ?>" alt="Logo Multimedia" class="auth-logo">
        </div>

        <?php if ($success): ?>
        <div style="text-align:center; padding: var(--sp-4) 0;">
            <div style="font-size:3rem; margin-bottom:var(--sp-4);">📬</div>
            <div class="auth-title" style="font-size:var(--fs-xl);">Pengajuan Terkirim!</div>
            <p style="color:var(--clr-text-secondary); font-size:var(--fs-sm); margin:var(--sp-3) 0 var(--sp-6); line-height:var(--lh-relaxed);">
                Pengajuan reset password kamu sudah masuk ke antrian admin.
                Setelah disetujui, password baru yang kamu masukkan tadi akan langsung aktif.
                Hubungi pembimbing kamu untuk mempercepat prosesnya.
            </p>
            <a href="<?= e(BASE_URL) ?>/" class="btn btn-primary">
                <i class="bi bi-house-fill"></i> Kembali ke Login
            </a>
        </div>

        <?php else: ?>

        <div class="auth-title">Lupa Password?</div>
        <div class="auth-subtitle">
            Masukkan email terdaftar dan password baru yang kamu inginkan.
            Admin akan memverifikasi dan mengaktifkan password barumu.
        </div>

        <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <div>
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Perhatikan:</strong>
                <ul style="margin:var(--sp-2) 0 0 var(--sp-4); padding:0; font-size:var(--fs-xs);">
                    <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <div class="alert alert-info" style="margin-bottom:var(--sp-5);">
            <i class="bi bi-info-circle-fill"></i>
            <span>Fitur ini hanya untuk <strong>Pembimbing</strong> dan <strong>Siswa</strong>. Admin mengatur password sendiri dari menu Settings.</span>
        </div>

        <form method="POST" action="" data-loading>
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="email">Email Terdaftar <span style="color:var(--clr-danger)">*</span></label>
                <div class="input-icon-wrap">
                    <i class="bi bi-envelope"></i>
                    <input type="email" id="email" name="email" class="form-control"
                           placeholder="email@kamu.com" autocomplete="email" required
                           value="<?= e($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password Baru <span style="color:var(--clr-danger)">*</span></label>
                <div class="input-icon-wrap">
                    <i class="bi bi-lock"></i>
                    <input type="password" id="password" name="new_password" class="form-control"
                           placeholder="Minimal 6 karakter" autocomplete="new-password">
                    <button type="button" class="toggle-pw" aria-label="Tampilkan password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div class="pw-strength-bar"><div class="pw-strength-fill"></div></div>
                <div class="pw-strength-text"></div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password_confirm">Konfirmasi Password Baru <span style="color:var(--clr-danger)">*</span></label>
                <div class="input-icon-wrap">
                    <i class="bi bi-lock-fill"></i>
                    <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                           placeholder="Ulangi password baru" autocomplete="new-password">
                    <button type="button" class="toggle-pw" aria-label="Tampilkan password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div id="pw-confirm-feedback" class="invalid-feedback"></div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block">
                <span class="btn-text"><i class="bi bi-send-fill"></i> Ajukan Reset Password</span>
                <span class="spinner d-none" aria-hidden="true"></span>
            </button>
        </form>

        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/../includes/auth-footer.php'; ?>
