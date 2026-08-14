<?php
/**
 * ============================================================
 *  Pembimbing — Halaman Pendaftaran
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) redirectToDashboard();

$errors  = [];
$success = false;
$old     = []; // repopulate form

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $old = $_POST;

    $nama         = post('nama', '');
    $username     = post('username', '');
    $umur         = (int) post('umur', 0);
    $jenis_kelamin= post('jenis_kelamin', '');
    $kelas_diampu = $_POST['kelas_diampu'] ?? [];
    $email        = post('email', '');
    $no_hp        = post('no_hp', '');
    $sosial_media = post('sosial_media', '');
    $password     = post('password', '');
    $pw_confirm   = post('password_confirm', '');

    // Validasi
    if (empty($nama))           $errors[] = 'Nama wajib diisi.';
    if (empty($username) || preg_match('/\s/', $username) || strtolower($username) !== $username) {
        $errors[] = 'Username wajib diisi, huruf kecil, dan tanpa spasi.';
    }
    if ($umur < 18 || $umur > 70) $errors[] = 'Umur harus antara 18–70 tahun.';
    if (!in_array($jenis_kelamin, ['L','P'])) $errors[] = 'Jenis kelamin wajib dipilih.';
    if (empty($kelas_diampu))   $errors[] = 'Pilih minimal satu kelas yang diampu.';
    foreach ($kelas_diampu as $k) {
        if (!in_array($k, KELAS_EKSKUL)) {
            $errors[] = 'Kelas tidak valid: ' . e($k);
        }
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (empty($no_hp))          $errors[] = 'No HP wajib diisi.';
    if (strlen($password) < 6)  $errors[] = 'Password minimal 6 karakter.';
    if ($password !== $pw_confirm) $errors[] = 'Konfirmasi password tidak cocok.';

    // Validasi Upload Foto
    $foto_link = '';
    if (!isset($_FILES['foto_profil']) || $_FILES['foto_profil']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Foto 3x4 wajib diupload.';
    } else {
        $uploaded = uploadProfilePhoto($_FILES['foto_profil']);
        if (!$uploaded) {
            $errors[] = 'Gagal upload foto. Pastikan format PNG/JPG/JPEG dan ukuran max 2MB.';
        } else {
            $foto_link = $uploaded;
        }
    }

    if (empty($errors)) {
        $db = getDB();

        // Cek email/username duplikat
        $check = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
        $check->execute([$email, $username]);
        if ($check->fetch()) {
            $errors[] = 'Email atau Username sudah terdaftar. Gunakan yang lain atau login langsung.';
        } else {
            // Simpan ke users
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("
                    INSERT INTO users (role, nama, username, email, no_hp, password, status, foto_profil_link)
                    VALUES ('pembimbing', ?, ?, ?, ?, ?, 'pending', ?)
                ");
                $stmt->execute([$nama, $username, $email, $no_hp, $password, $foto_link]);
                $userId = (int) $db->lastInsertId();

                // Simpan ke pembimbing_detail
                $stmt2 = $db->prepare("
                    INSERT INTO pembimbing_detail (user_id, umur, jenis_kelamin, kelas_diampu, sosial_media)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt2->execute([
                    $userId,
                    $umur,
                    $jenis_kelamin,
                    implode(',', $kelas_diampu),
                    $sosial_media
                ]);

                $db->commit();
                $success = true;
                $old = []; // Clear form
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
                error_log('[Register Pembimbing] ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Daftar Pembimbing — ' . APP_NAME;
require_once __DIR__ . '/../includes/auth-header.php';
?>

<main class="auth-wrapper auth-wide" style="position:relative;z-index:1;">

    <a href="<?= e(BASE_URL) ?>/" class="auth-back">
        <i class="bi bi-arrow-left"></i> Kembali ke Login
    </a>

    <div class="auth-card">

        <div class="auth-brand">
            <img src="<?= e(BASE_URL . LOGO_PATH) ?>" alt="Logo Multimedia" class="auth-logo">
            <div class="auth-school-name">MTs &amp; MA YABIKA</div>
        </div>

        <?php if ($success): ?>
        <div style="text-align:center; padding: var(--sp-6) 0;">
            <div style="font-size:3.5rem; margin-bottom:var(--sp-4);">🎉</div>
            <div class="auth-title" style="font-size:var(--fs-xl);">Pendaftaran Berhasil!</div>
            <p style="color:var(--clr-text-secondary); font-size:var(--fs-sm); margin:var(--sp-3) 0 var(--sp-6); line-height:var(--lh-relaxed);">
                Akun pembimbingmu sudah dibuat dan sedang <strong>menunggu verifikasi admin</strong>.
                Kamu akan bisa login setelah admin menyetujui pendaftaranmu.
            </p>
            <a href="<?= e(BASE_URL) ?>/?role=pembimbing" class="btn btn-primary btn-lg">
                <i class="bi bi-box-arrow-in-right"></i> Ke Halaman Login
            </a>
        </div>

        <?php else: ?>

        <div class="auth-title">Daftar sebagai Pembimbing</div>
        <div class="auth-subtitle">Isi semua data di bawah. Akun akan aktif setelah diverifikasi admin.</div>

        <?php if ($errors): ?>
        <div class="alert alert-danger" role="alert">
            <div>
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Ada <?= count($errors) ?> kesalahan:</strong>
                <ul style="margin:var(--sp-2) 0 0 var(--sp-4); padding:0; font-size:var(--fs-xs);">
                    <?php foreach ($errors as $err): ?>
                    <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" data-loading id="form-register-pembimbing">
            <?= csrfField() ?>

            <!-- ── SEKSI 1: Data Pribadi ── -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-person-vcard"></i> Data Pribadi
                </div>

                <div class="form-group">
                    <label class="form-label" for="nama">Nama Lengkap <span style="color:var(--clr-danger)">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-person"></i>
                        <input type="text" id="nama" name="nama" class="form-control"
                               placeholder="Nama sesuai KTP" required
                               value="<?= e($old['nama'] ?? '') ?>">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--sp-4);">
                    <div class="form-group">
                        <label class="form-label" for="username">Username <span style="color:var(--clr-danger)">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-at"></i>
                            <input type="text" id="username" name="username" class="form-control"
                                   placeholder="Huruf kecil, tanpa spasi" required
                                   pattern="^[a-z0-9_]+$"
                                   title="Hanya huruf kecil, angka, dan underscore tanpa spasi"
                                   value="<?= e($old['username'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Aktif <span style="color:var(--clr-danger)">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-envelope"></i>
                            <input type="email" id="email" name="email" class="form-control"
                                   placeholder="email@kamu.com" autocomplete="email" required
                                   value="<?= e($old['email'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: var(--sp-4);">
                    <div class="form-group">
                        <label class="form-label" for="umur">Umur <span style="color:var(--clr-danger)">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-calendar2"></i>
                            <input type="number" id="umur" name="umur" class="form-control"
                                   placeholder="25" min="18" max="70" required
                                   value="<?= e($old['umur'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Jenis Kelamin <span style="color:var(--clr-danger)">*</span></label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="jenis_kelamin" value="L"
                                       <?= (($old['jenis_kelamin'] ?? '') === 'L') ? 'checked' : '' ?>>
                                <i class="bi bi-gender-male"></i> Laki-laki
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="jenis_kelamin" value="P"
                                       <?= (($old['jenis_kelamin'] ?? '') === 'P') ? 'checked' : '' ?>>
                                <i class="bi bi-gender-female"></i> Perempuan
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="foto_profil">Upload Foto 3×4 <span style="color:var(--clr-danger)">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-card-image"></i>
                        <input type="file" id="foto_profil" name="foto_profil" class="form-control" accept="image/png, image/jpeg, image/jpg" required style="padding: 10px 10px 10px 40px;">
                    </div>
                    <div class="form-text">Format: JPG/PNG, Maksimal: 2MB.</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="sosial_media">Sosial Media</label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-instagram"></i>
                        <input type="text" id="sosial_media" name="sosial_media" class="form-control"
                               placeholder="@username_instagram (atau link profil lain)"
                               value="<?= e($old['sosial_media'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- ── SEKSI 2: Kelas yang Diampu ── -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-mortarboard"></i> Kelas yang Diampu
                </div>
                <div class="form-text" style="margin-bottom:var(--sp-3);">Pilih satu atau lebih kelas ekskul yang kamu ampu.</div>
                <div class="checkbox-group">
                    <?php foreach (KELAS_EKSKUL as $kelas): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="kelas_diampu[]" value="<?= e($kelas) ?>"
                               <?= in_array($kelas, $old['kelas_diampu'] ?? []) ? 'checked' : '' ?>>
                        <span class="checkbox-label"><?= e($kelas) ?></span>
                        <i class="bi bi-check-circle-fill checkbox-icon"></i>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── SEKSI 3: Kontak ── -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-telephone"></i> Informasi Kontak Lainnya
                </div>

                <div class="form-group">
                    <label class="form-label" for="no_hp">No HP / WhatsApp <span style="color:var(--clr-danger)">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-phone"></i>
                        <input type="tel" id="no_hp" name="no_hp" class="form-control"
                               placeholder="08xxxxxxxxxx" required
                               value="<?= e($old['no_hp'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- ── SEKSI 4: Password ── -->
            <div class="form-section">
                <div class="form-section-title">
                    <i class="bi bi-lock"></i> Password Akun
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password <span style="color:var(--clr-danger)">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-lock"></i>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Minimal 6 karakter" autocomplete="new-password" required>
                        <button type="button" class="toggle-pw" aria-label="Tampilkan password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div class="pw-strength-bar"><div class="pw-strength-fill"></div></div>
                    <div class="pw-strength-text"></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirm">Konfirmasi Password <span style="color:var(--clr-danger)">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-lock-fill"></i>
                        <input type="password" id="password_confirm" name="password_confirm" class="form-control"
                               placeholder="Ulangi password" autocomplete="new-password" required>
                        <button type="button" class="toggle-pw" aria-label="Tampilkan password">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    <div id="pw-confirm-feedback" class="invalid-feedback"></div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg btn-block" id="submit-btn">
                <span class="btn-text"><i class="bi bi-person-plus-fill"></i> Daftar Sekarang</span>
                <span class="spinner d-none" aria-hidden="true"></span>
            </button>
        </form>

        <div class="auth-links">
            <div class="auth-link-row">
                Sudah punya akun? <a href="<?= e(BASE_URL) ?>/?role=pembimbing">Masuk sebagai Pembimbing</a>
            </div>
        </div>

        <?php endif; ?>

    </div><!-- /.auth-card -->
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Auto lowercase username
    const usernameInput = document.getElementById('username');
    if (usernameInput) {
        usernameInput.addEventListener('input', function() {
            this.value = this.value.toLowerCase().replace(/\s/g, '');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/auth-footer.php'; ?>
