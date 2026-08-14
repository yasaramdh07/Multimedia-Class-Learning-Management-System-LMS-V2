<?php
/**
 * ============================================================
 *  Auth — Ganti Password (saat sedang login)
 * ============================================================
 *  Wajib masukin password lama dulu sebelum bisa ganti.
 *  Berlaku untuk pembimbing & siswa (admin punya menu sendiri).
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

// Admin tidak menggunakan halaman ini
if (currentRole() === ROLE_ADMIN) {
    redirect(BASE_URL . '/admin/index.php');
}

$userId  = (int) $_SESSION['user_id'];
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $pw_lama    = post('password_lama', '');
    $pw_baru    = post('password_baru', '');
    $pw_confirm = post('password_confirm', '');

    if (empty($pw_lama))          $errors[] = 'Password lama wajib diisi.';
    if (strlen($pw_baru) < 6)     $errors[] = 'Password baru minimal 6 karakter.';
    if ($pw_baru !== $pw_confirm)  $errors[] = 'Konfirmasi password baru tidak cocok.';

    if (empty($errors)) {
        $db   = getDB();
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user || $user['password'] !== $pw_lama) {
            $errors[] = 'Password lama yang kamu masukkan salah.';
        } elseif ($pw_lama === $pw_baru) {
            $errors[] = 'Password baru harus berbeda dari password lama.';
        } else {
            $upd = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$pw_baru, $userId]);
            $success = true;
        }
    }
}

$role      = currentRole();
$dashUrl   = BASE_URL . '/' . $role . '/index.php';
$pageTitle = 'Ganti Password — ' . APP_NAME;
require_once __DIR__ . '/../includes/auth-header.php';
?>

<main class="auth-wrapper" style="position:relative;z-index:1; margin-top:auto; margin-bottom:auto;">

    <a href="<?= e($dashUrl) ?>" class="auth-back">
        <i class="bi bi-arrow-left"></i> Kembali ke Dashboard
    </a>

    <div class="auth-card">

        <div class="auth-brand">
            <img src="<?= e(BASE_URL . LOGO_PATH) ?>" alt="Logo Multimedia" class="auth-logo">
        </div>

        <?php if ($success): ?>
        <div style="text-align:center; padding: var(--sp-4) 0;">
            <div style="font-size:3rem; margin-bottom:var(--sp-4);">🔐</div>
            <div class="auth-title" style="font-size:var(--fs-xl);">Password Berhasil Diubah!</div>
            <p style="color:var(--clr-text-secondary); font-size:var(--fs-sm); margin:var(--sp-3) 0 var(--sp-6); line-height:var(--lh-relaxed);">
                Password barumu sudah aktif. Gunakan password baru ini saat login berikutnya.
            </p>
            <a href="<?= e($dashUrl) ?>" class="btn btn-primary">
                <i class="bi bi-house-fill"></i> Kembali ke Dashboard
            </a>
        </div>

        <?php else: ?>

        <div class="auth-title">Ganti Password</div>
        <div class="auth-subtitle">Masukkan password lama untuk verifikasi, lalu tentukan password baru.</div>

        <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <div>
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Ada masalah:</strong>
                <ul style="margin:var(--sp-2) 0 0 var(--sp-4); padding:0; font-size:var(--fs-xs);">
                    <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="" data-loading>
            <?= csrfField() ?>

            <div class="form-group">
                <label class="form-label" for="password_lama">Password Lama <span style="color:var(--clr-danger)">*</span></label>
                <div class="input-icon-wrap">
                    <i class="bi bi-lock"></i>
                    <input type="password" id="password_lama" name="password_lama" class="form-control"
                           placeholder="Password saat ini" autocomplete="current-password">
                    <button type="button" class="toggle-pw" aria-label="Tampilkan password">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>

            <div class="divider-text" style="margin: var(--sp-5) 0;">Password Baru</div>

            <div class="form-group">
                <label class="form-label" for="password_baru">Password Baru <span style="color:var(--clr-danger)">*</span></label>
                <div class="input-icon-wrap">
                    <i class="bi bi-lock-fill"></i>
                    <input type="password" id="password" name="password_baru" class="form-control"
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
                <span class="btn-text"><i class="bi bi-check-circle-fill"></i> Simpan Password Baru</span>
                <span class="spinner d-none" aria-hidden="true"></span>
            </button>
        </form>

        <div class="auth-links">
            <div class="auth-link-row" style="font-size:var(--fs-xs); color:var(--clr-text-muted);">
                <i class="bi bi-info-circle"></i>
                Lupa password lama? Logout dulu, lalu gunakan fitur
                <a href="<?= e(BASE_URL) ?>/auth/lupa-password.php">Lupa Password</a>.
            </div>
        </div>

        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/../includes/auth-footer.php'; ?>
