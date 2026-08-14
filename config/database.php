<?php
/**
 * ============================================================
 *  Multimedia Class — Konfigurasi Utama
 * ============================================================
 *  Ubah nilai di file ini saja saat pindah environment
 *  (Laragon → cPanel hosting, dsb.).  Jangan hardcode
 *  nilai-nilai ini di tempat lain.
 * ============================================================
 */

// ------------------------------------------------------------------
// Base URL
// Deteksi otomatis scheme + host supaya portable di semua environment.
// Bisa di-override manual jika perlu (uncomment baris di bawah).
// ------------------------------------------------------------------

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script   = $_SERVER['SCRIPT_NAME'] ?? '';

// Naiki dua level dari /config/database.php → root project
$rootPath = rtrim(dirname(dirname($script)), '/');

define('BASE_URL', $scheme . '://' . $host . $rootPath);

// Manual override — uncomment jika butuh nilai statis:
// define('BASE_URL', 'https://multimedia.example.com');


// ------------------------------------------------------------------
// Path Absolut ke File SQLite
// Disimpan di luar document root (di folder /database/) yang sudah
// dilindungi .htaccess.  __DIR__ di sini = /config/, jadi naik satu
// level lalu masuk /database/.
// ------------------------------------------------------------------

define('DB_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'multimedia_class.sqlite');


// ------------------------------------------------------------------
// Fungsi Koneksi SQLite (PDO)
// Mengembalikan singleton PDO agar tidak membuka koneksi berulang.
// ------------------------------------------------------------------

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'sqlite:' . DB_PATH;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, null, null, $options);

            // ── WAL mode: mencegah lock saat banyak request bersamaan ──
            $pdo->exec('PRAGMA journal_mode=WAL;');

            // Optimasi tambahan yang direkomendasikan untuk SQLite + WAL
            $pdo->exec('PRAGMA synchronous=NORMAL;');   // Lebih cepat, masih aman
            $pdo->exec('PRAGMA foreign_keys=ON;');      // Enforce FK constraint
            $pdo->exec('PRAGMA busy_timeout=5000;');    // Tunggu 5 detik sebelum error lock
            $pdo->exec('PRAGMA cache_size=-8000;');     // Cache ~8 MB di memori
        } catch (PDOException $e) {
            // Jangan tampilkan detail path/DB di production
            error_log('[DB] Gagal konek SQLite: ' . $e->getMessage());
            http_response_code(500);
            exit('Database tidak dapat dijangkau. Silakan coba lagi nanti.');
        }
    }

    return $pdo;
}


// ------------------------------------------------------------------
// Konstanta Aplikasi
// ------------------------------------------------------------------

define('APP_NAME', 'Multimedia Class');
define('APP_VERSION', '1.0.0');

// Role yang valid
define('ROLE_ADMIN',      'admin');
define('ROLE_PEMBIMBING', 'pembimbing');
define('ROLE_SISWA',      'siswa');

// Status akun
define('STATUS_PENDING',  'pending');
define('STATUS_APPROVED', 'approved');
define('STATUS_REJECTED', 'rejected');

// ------------------------------------------------------------------
// Kredensial Admin (akun tunggal, disimpan di DB saat init)
// Ubah nilai ini HANYA satu kali sebelum deploy pertama kali,
// atau gunakan fitur ganti password dari settings admin.
// ------------------------------------------------------------------
define('ADMIN_DEFAULT_USERNAME', 'Admin_Multimedia');
define('ADMIN_DEFAULT_PASSWORD', 'multimediayabika09');

// Kelas Ekskul yang tersedia
define('KELAS_EKSKUL', [
    'Web Development',
    'Desain Grafis-Editing Video',
    'Arduino Robotic',
]);

// Logo path (relatif ke BASE_URL) — (1) = versi biru/brand
define('LOGO_PATH', '/assets/logo/logo-multimedia (1).png');
define('LOGO_DARK_PATH', '/assets/logo/logo-multimedia (2).png');
