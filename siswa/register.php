<?php
/**
 * ============================================================
 *  Siswa — Halaman Pendaftaran
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (isLoggedIn()) redirectToDashboard();

$errors  = [];
$success = false;
$old     = [];

// Ambil daftar tahun ajaran aktif dari DB
$db_temp = getDB();
$tahunAjaranList = $db_temp->query(
    "SELECT id, tahun, semester FROM tahun_ajaran ORDER BY id DESC"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $old = $_POST;

    $nama             = post('nama', '');
    $username         = post('username', '');
    $email            = post('email', '');
    $kelas            = post('kelas', '');
    $jenjang          = post('jenjang', '');
    $tahun_ajaran_id  = (int) post('tahun_ajaran_id', 0);
    $kelas_ekskul     = $_POST['kelas_ekskul'] ?? [];
    $wali_kelas       = post('wali_kelas', '');
    $no_wa            = post('no_wa', '');
    $nama_ortu        = post('nama_ortu', '');
    $no_hp_ortu       = post('no_hp_ortu', '');
    $alamat           = post('alamat_domisili', '');
    $password         = post('password', '');
    $pw_confirm       = post('password_confirm', '');

    // Validasi
    if (empty($nama))               $errors[] = 'Nama lengkap wajib diisi.';
    if (empty($username) || preg_match('/\s/', $username) || strtolower($username) !== $username) {
        $errors[] = 'Username wajib diisi, huruf kecil, dan tanpa spasi.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';
    if (empty($kelas))              $errors[] = 'Kelas wajib dipilih.';
    if (!in_array($jenjang, ['MTs','MA'])) $errors[] = 'Jenjang wajib dipilih (MTs atau MA).';
    if (empty($kelas_ekskul))       $errors[] = 'Pilih minimal satu kelas ekskul.';
    foreach ($kelas_ekskul as $k) {
        if (!in_array($k, KELAS_EKSKUL)) $errors[] = 'Kelas ekskul tidak valid: ' . e($k);
    }
    if (empty($wali_kelas))         $errors[] = 'Nama wali kelas wajib diisi.';
    if (empty($no_wa))              $errors[] = 'No WA aktif wajib diisi.';
    if (empty($nama_ortu))          $errors[] = 'Nama orang tua/wali wajib diisi.';
    if (empty($no_hp_ortu))         $errors[] = 'No HP orang tua wajib diisi.';
    if (empty($alamat))             $errors[] = 'Alamat domisili wajib diisi.';
    if (strlen($password) < 6)      $errors[] = 'Password minimal 6 karakter.';
    if ($password !== $pw_confirm)  $errors[] = 'Konfirmasi password tidak cocok.';

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

        // Cek email atau username sudah ada?
        $check = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
        $check->execute([$email, $username]);
        if ($check->fetch()) {
            $errors[] = 'Email atau Username sudah terdaftar. Gunakan yang lain.';
        } else {
            $db->beginTransaction();
            try {
                // Insert users
                $stmt = $db->prepare("
                    INSERT INTO users (role, nama, username, email, no_hp, password, status, foto_profil_link)
                    VALUES ('siswa', ?, ?, ?, ?, ?, 'pending', ?)
                ");
                $stmt->execute([$nama, $username, $email, $no_wa, $password, $foto_link]);
                $userId = (int) $db->lastInsertId();

                // Insert siswa_detail
                $stmt2 = $db->prepare("
                    INSERT INTO siswa_detail
                        (user_id, kelas, jenjang, wali_kelas, no_wa, nama_ortu, no_hp_ortu, alamat_domisili)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt2->execute([$userId, $kelas, $jenjang, $wali_kelas, $no_wa, $nama_ortu, $no_hp_ortu, $alamat]);

                // Insert siswa_kelas_ekskul (many-to-many)
                $stmt3 = $db->prepare("
                    INSERT INTO siswa_kelas_ekskul (siswa_id, kelas_ekskul) VALUES (?, ?)
                ");
                foreach ($kelas_ekskul as $ke) {
                    $stmt3->execute([$userId, $ke]);
                }

                $db->commit();
                $success = true;
                $old = [];
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
                error_log('[Register Siswa] ' . $e->getMessage());
            }
        }
    }
}

$pageTitle = 'Daftar Siswa — ' . APP_NAME;
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
                Akunmu sudah dibuat dan sedang <strong>menunggu verifikasi admin</strong>.
                Kamu akan bisa login setelah admin menyetujui pendaftaranmu. Hubungi pembimbing kamu untuk follow-up.
            </p>
            <a href="<?= e(BASE_URL) ?>/?role=siswa" class="btn btn-primary btn-lg">
                <i class="bi bi-box-arrow-in-right"></i> Ke Halaman Login
            </a>
        </div>

        <?php else: ?>

        <div class="auth-title">Daftar sebagai Siswa</div>
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

        <form method="POST" action="" enctype="multipart/form-data" data-loading id="form-register-siswa">
            <?= csrfField() ?>

            <!-- ── SEKSI 1: Data Diri ── -->
            <div class="form-section">
                <div class="form-section-title"><i class="bi bi-person-vcard"></i> Data Diri</div>

                <div class="form-group">
                    <label class="form-label" for="nama">Nama Lengkap <span style="color:var(--clr-danger)">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-person"></i>
                        <input type="text" id="nama" name="nama" class="form-control"
                               placeholder="Nama sesuai kartu pelajar" required
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

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:var(--sp-4);">
                    <div class="form-group">
                        <label class="form-label">Jenjang <span style="color:var(--clr-danger)">*</span></label>
                        <div class="radio-group">
                            <label class="radio-item">
                                <input type="radio" name="jenjang" value="MTs" id="jenjang_mts"
                                       <?= (($old['jenjang'] ?? 'MTs') === 'MTs') ? 'checked' : '' ?>>
                                <i class="bi bi-book"></i> MTs
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="jenjang" value="MA" id="jenjang_ma"
                                       <?= (($old['jenjang'] ?? '') === 'MA') ? 'checked' : '' ?>>
                                <i class="bi bi-mortarboard"></i> MA
                            </label>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="kelas">Kelas <span style="color:var(--clr-danger)">*</span></label>
                        <div class="input-icon-wrap">
                            <i class="bi bi-door-open"></i>
                            <select id="kelas" name="kelas" class="form-control" required style="padding-left:calc(var(--sp-4) + 1.3rem);">
                                <option value="">— Pilih Kelas —</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="tahun_ajaran_id">Tahun Ajaran</label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-calendar3"></i>
                        <select id="tahun_ajaran_id" name="tahun_ajaran_id" class="form-control" style="padding-left:calc(var(--sp-4) + 1.3rem);">
                            <option value="">— Pilih Tahun Ajaran —</option>
                            <?php foreach ($tahunAjaranList as $ta): ?>
                            <option value="<?= e($ta['id']) ?>"
                                <?= (($old['tahun_ajaran_id'] ?? '') == $ta['id']) ? 'selected' : '' ?>>
                                <?= e($ta['tahun']) ?> — <?= e($ta['semester']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (empty($tahunAjaranList)): ?>
                    <div class="form-text" style="color:var(--clr-warning);">⚠️ Belum ada tahun ajaran. Admin perlu menambahkan dulu.</div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="wali_kelas">Wali Kelas <span style="color:var(--clr-danger)">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-person-check"></i>
                        <input type="text" id="wali_kelas" name="wali_kelas" class="form-control"
                               placeholder="Nama wali kelas" required
                               value="<?= e($old['wali_kelas'] ?? '') ?>">
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
            </div>

            <!-- ── SEKSI 2: Kelas Ekskul ── -->
            <div class="form-section">
                <div class="form-section-title"><i class="bi bi-camera-video"></i> Pilihan Kelas Ekskul</div>
                <div class="form-text" style="margin-bottom:var(--sp-3);">Bisa pilih lebih dari satu kelas.</div>
                <div class="checkbox-group">
                    <?php foreach (KELAS_EKSKUL as $kelas): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="kelas_ekskul[]" value="<?= e($kelas) ?>"
                               <?= in_array($kelas, $old['kelas_ekskul'] ?? []) ? 'checked' : '' ?>>
                        <span class="checkbox-label"><?= e($kelas) ?></span>
                        <i class="bi bi-check-circle-fill checkbox-icon"></i>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── SEKSI 3: Kontak & Orang Tua ── -->
            <div class="form-section">
                <div class="form-section-title"><i class="bi bi-people"></i> Kontak Tambahan</div>

                <div class="form-group">
                    <label class="form-label" for="no_wa">No WA Aktif <span style="color:var(--clr-danger)">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-whatsapp"></i>
                        <input type="tel" id="no_wa" name="no_wa" class="form-control"
                               placeholder="08xxxxxxxxxx" required
                               value="<?= e($old['no_wa'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="nama_ortu">Nama Orang Tua/Wali <span style="color:var(--clr-danger)">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-person-hearts"></i>
                        <input type="text" id="nama_ortu" name="nama_ortu" class="form-control"
                               placeholder="Nama lengkap orang tua/wali" required
                               value="<?= e($old['nama_ortu'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="no_hp_ortu">No HP Orang Tua <span style="color:var(--clr-danger)">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-phone"></i>
                        <input type="tel" id="no_hp_ortu" name="no_hp_ortu" class="form-control"
                               placeholder="08xxxxxxxxxx" required
                               value="<?= e($old['no_hp_ortu'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="alamat_domisili">Alamat Domisili <span style="color:var(--clr-danger)">*</span></label>
                    <textarea id="alamat_domisili" name="alamat_domisili" required
                              class="form-control textarea" placeholder="Alamat lengkap tempat tinggal saat ini"
                              rows="3"><?= e($old['alamat_domisili'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- ── SEKSI 4: Password ── -->
            <div class="form-section">
                <div class="form-section-title"><i class="bi bi-lock"></i> Password Akun</div>

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
                Sudah punya akun? <a href="<?= e(BASE_URL) ?>/?role=siswa">Masuk sebagai Siswa</a>
            </div>
        </div>

        <?php endif; ?>

    </div><!-- /.auth-card -->
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const jenjangRadios = document.querySelectorAll('input[name="jenjang"]');
    const selectKelas = document.getElementById('kelas');
    
    const oldKelas = "<?= e($old['kelas'] ?? '') ?>";
    
    function populateKelas() {
        const val = document.querySelector('input[name="jenjang"]:checked')?.value;
        let options = '<option value="">— Pilih Kelas —</option>';
        if (val === 'MTs') {
            const arr = ['7A','7B','7C','7D','8A','8B','8C','8D','9A','9B','9C','9D'];
            arr.forEach(k => {
                const sel = (k === oldKelas) ? 'selected' : '';
                options += `<option value="${k}" ${sel}>${k}</option>`;
            });
        } else if (val === 'MA') {
            const arr = ['10','11','12'];
            arr.forEach(k => {
                const sel = (k === oldKelas) ? 'selected' : '';
                options += `<option value="${k}" ${sel}>${k}</option>`;
            });
        }
        selectKelas.innerHTML = options;
    }
    
    jenjangRadios.forEach(r => r.addEventListener('change', populateKelas));
    populateKelas(); // Init load
    
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
