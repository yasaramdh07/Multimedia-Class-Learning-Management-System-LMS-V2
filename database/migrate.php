<?php
/**
 * ============================================================
 *  Multimedia Class — Database Migrator
 * ============================================================
 *  Jalankan via CLI: php database/migrate.php
 *  Akan menjalankan semua file SQL di /database/migrations/
 *  yang belum pernah dijalankan (dicek via tabel migrations).
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';

$db = getDB();

// Tabel tracking migrasi
$db->exec("CREATE TABLE IF NOT EXISTS migrations (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    filename   TEXT NOT NULL UNIQUE,
    applied_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
)");

$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

$applied = $db->query("SELECT filename FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

$ran = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        echo "  [skip] $name (sudah dijalankan)\n";
        continue;
    }

    $rawSql = file_get_contents($file);
    $sql    = preg_replace('/--[^\r\n]*/u', '', $rawSql);
    $sql    = preg_replace('/\/\*.*?\*\//su', '', $sql);
    $stmts  = array_filter(array_map('trim', explode(';', $sql)), fn($s) => $s !== '');

    try {
        $db->beginTransaction();
        foreach ($stmts as $stmt) {
            $db->exec($stmt);
        }
        $db->prepare("INSERT INTO migrations (filename) VALUES (?)")->execute([$name]);
        $db->commit();
        echo "  [ok]   $name\n";
        $ran++;
    } catch (Exception $e) {
        $db->rollBack();
        echo "  [ERROR] $name: " . $e->getMessage() . "\n";
        exit(1);
    }
}

if ($ran === 0) {
    echo "Tidak ada migrasi baru.\n";
} else {
    echo "\n✅ $ran migrasi berhasil dijalankan.\n";
}

// Seed admin account ke app_settings jika belum ada
$adminKey = $db->prepare("SELECT value FROM app_settings WHERE key='admin_username'")->execute();
$existing = $db->query("SELECT COUNT(*) FROM app_settings WHERE key='admin_username'")->fetchColumn();

if (!$existing) {
    $db->prepare("INSERT INTO app_settings (key, value) VALUES (?, ?)")->execute(['admin_username', ADMIN_DEFAULT_USERNAME]);
    $db->prepare("INSERT INTO app_settings (key, value) VALUES (?, ?)")->execute(['admin_password', ADMIN_DEFAULT_PASSWORD]);
    echo "✅ Admin credentials di-seed ke app_settings.\n";
} else {
    echo "   Admin credentials sudah ada di app_settings.\n";
}
