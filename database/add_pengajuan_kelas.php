<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    $sql = "
    CREATE TABLE IF NOT EXISTS pengajuan_kelas (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        siswa_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        jenis_pengajuan TEXT NOT NULL CHECK(jenis_pengajuan IN ('tambah', 'ganti')),
        kelas_lama TEXT,
        kelas_baru TEXT NOT NULL,
        alasan TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'menunggu' CHECK(status IN ('menunggu', 'disetujui', 'ditolak')),
        created_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
    );
    ";
    $db->exec($sql);
    echo "Tabel pengajuan_kelas berhasil dibuat.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
