<?php
/**
 * ============================================================
 *  Multimedia Class — Database Initializer
 * ============================================================
 *  Jalankan file ini SEKALI via browser atau CLI untuk membuat
 *  semua tabel dari schema.sql ke file SQLite.
 *
 *  Browser : http://multimedia.test/database/init.php
 *  CLI     : php database/init.php
 *
 *  PENTING: Hapus atau rename file ini setelah database berhasil
 *           diinisialisasi, agar tidak bisa diakses ulang.
 * ============================================================
 */

// Pastikan tidak diakses saat production (bisa tambahkan token check)
// define('INIT_TOKEN', 'ganti-ini-dengan-token-rahasia');
// if (($_GET['token'] ?? '') !== INIT_TOKEN) { http_response_code(403); exit('Forbidden'); }

require_once __DIR__ . '/../config/database.php';

$schemaFile = __DIR__ . '/schema.sql';

if (!file_exists($schemaFile)) {
    exit('[ERROR] schema.sql tidak ditemukan di ' . $schemaFile);
}

try {
    $db  = getDB();
    $rawSql = file_get_contents($schemaFile);

    // Hapus komentar -- single-line
    $sql = preg_replace('/--[^\r\n]*/u', '', $rawSql);
    // Hapus komentar /* block */
    $sql = preg_replace('/\/\*.*?\*\//su', '', $sql);

    // Split per titik-koma, buang yang kosong
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => $s !== ''
    );

    $db->beginTransaction();
    foreach ($statements as $stmt) {
        if (trim($stmt)) {
            $db->exec($stmt);
        }
    }
    $db->commit();

    echo "✅ Database berhasil diinisialisasi!\n";
    echo "   File: " . DB_PATH . "\n";
    echo "   Tabel dibuat: ";

    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    echo implode(', ', $tables) . "\n\n";
    echo "⚠️  Hapus atau rename file ini (database/init.php) setelah selesai!\n";

} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    echo "[ERROR] Gagal inisialisasi database:\n" . $e->getMessage() . "\n";
    exit(1);
}
