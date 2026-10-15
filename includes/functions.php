<?php
/**
 * ============================================================
 *  Multimedia Class — General Helper Functions
 * ============================================================
 */

/**
 * Sanitasi output HTML (XSS prevention).
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirect dengan header Location.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

/**
 * Kirim respons JSON dan hentikan eksekusi.
 *
 * @param mixed $data    Data yang akan di-encode
 * @param int   $status  HTTP status code
 */
function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Validasi apakah request method sesuai.
 */
function requireMethod(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }
}

/**
 * Validasi apakah request adalah AJAX (X-Requested-With: XMLHttpRequest).
 */
function isAjax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
}

/**
 * Ambil input dari $_POST dengan fallback default & trim.
 */
function post(string $key, mixed $default = null): mixed
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

/**
 * Ambil input dari $_GET dengan fallback default & trim.
 */
function get(string $key, mixed $default = null): mixed
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/**
 * Hash password menggunakan bcrypt.
 */
function hashPassword(string $plain): string
{
    return password_hash($plain, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verifikasi password.
 */
function verifyPassword(string $plain, string $hash): bool
{
    return password_verify($plain, $hash);
}

/**
 * Format tanggal dari SQLite ke format Indonesia.
 * Contoh: '2025-08-15' → '15 Agustus 2025'
 */
function formatTanggal(string $date): string
{
    $bulan = [
        1  => 'Januari', 2  => 'Februari', 3  => 'Maret',
        4  => 'April',   5  => 'Mei',       6  => 'Juni',
        7  => 'Juli',    8  => 'Agustus',   9  => 'September',
        10 => 'Oktober', 11 => 'November',  12 => 'Desember',
    ];

    try {
        $dt = new DateTime($date);
        return $dt->format('j') . ' ' . ($bulan[(int)$dt->format('n')]) . ' ' . $dt->format('Y');
    } catch (Exception) {
        return $date;
    }
}

/**
 * Truncate string panjang untuk preview.
 */
function truncate(string $text, int $maxLen = 80, string $suffix = '…'): string
{
    if (mb_strlen($text) <= $maxLen) {
        return $text;
    }
    return mb_substr($text, 0, $maxLen) . $suffix;
}

/**
 * Generate CSRF token dan simpan di sesi.
 */
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verifikasi CSRF token dari form POST.
 */
function verifyCsrf(): void
{
    $token = post('csrf_token', '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}

/**
 * Buat hidden input CSRF untuk form HTML.
 */
function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

/**
 * Handle upload foto profil.
 * @param array $file $_FILES['input_name']
 * @return string|null Path file yang berhasil diupload (relative ke BASE_URL), atau null jika gagal/tidak ada.
 */
function uploadProfilePhoto(array $file): ?string
{
    if (!isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null; // Tidak ada file atau error upload
    }

    $size = $file['size'];
    if ($size > 2 * 1024 * 1024) { // Max 2MB
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $ext = $finfo->file($file['tmp_name']);
    
    $allowedTypes = [
        'jpg' => 'image/jpeg',
        'png' => 'image/png',
        'jpeg' => 'image/jpeg'
    ];

    $ext = array_search($ext, $allowedTypes, true);
    if ($ext === false) {
        return null; // Tipe file tidak diizinkan
    }

    // Buat nama file unik
    $filename = sprintf('%s.%s', sha1_file($file['tmp_name']) . '_' . time(), $ext);
    
    // Path tujuan upload (absolute di server)
    $targetDir = __DIR__ . '/../assets/img/profiles/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    
    $targetPath = $targetDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Return path relative untuk disimpan di database
        return BASE_URL . '/assets/img/profiles/' . $filename;
    }

    return null;
}

/**
 * Auto-migrasi: pastikan tabel absensi menerima status 'alpa'.
 *
 * SQLite tidak bisa ALTER CHECK constraint, jadi tabel dibuat ulang
 * (create new → copy data → drop old → rename). Aman dipanggil berkali-kali:
 * jika constraint sudah memuat 'alpa', fungsi langsung return.
 */
function ensureAbsensiAlpa(PDO $db): void
{
    $sql = $db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='absensi'")->fetchColumn();
    if (!$sql || str_contains($sql, "'alpa'")) {
        return;
    }

    // PRAGMA foreign_keys tidak bisa diubah di dalam transaksi
    $db->exec('PRAGMA foreign_keys=OFF;');
    try {
        $db->beginTransaction();
        $db->exec("
            CREATE TABLE absensi_new (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                sesi_absensi_id  INTEGER NOT NULL REFERENCES sesi_absensi(id) ON DELETE CASCADE,
                siswa_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                status           TEXT    NOT NULL CHECK(status IN ('hadir','izin','sakit','alpa')),
                waktu_dicatat    TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
                dicatat_oleh     INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
                UNIQUE(sesi_absensi_id, siswa_id)
            )
        ");
        $db->exec("
            INSERT INTO absensi_new (id, sesi_absensi_id, siswa_id, status, waktu_dicatat, dicatat_oleh)
            SELECT id, sesi_absensi_id, siswa_id, status, waktu_dicatat, dicatat_oleh FROM absensi
        ");
        $db->exec("DROP TABLE absensi");
        $db->exec("ALTER TABLE absensi_new RENAME TO absensi");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_absensi_sesi  ON absensi(sesi_absensi_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_absensi_siswa ON absensi(siswa_id)");
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('[Migrasi absensi alpa] ' . $e->getMessage());
    } finally {
        $db->exec('PRAGMA foreign_keys=ON;');
    }
}
