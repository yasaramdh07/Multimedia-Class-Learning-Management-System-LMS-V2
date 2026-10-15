<?php
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDB();
    // Hapus entri pengajuan_kelas yang kelas_baru nya kosong (data invalid dari bug)
    $stmt = $db->prepare("DELETE FROM pengajuan_kelas WHERE kelas_baru = '' OR kelas_baru IS NULL");
    $stmt->execute();
    $hapus = $stmt->rowCount();
    echo "Berhasil menghapus {$hapus} entri invalid dari tabel pengajuan_kelas.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
