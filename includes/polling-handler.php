<?php
/**
 * ============================================================
 *  Multimedia Class — Polling Handler (AJAX Endpoint)
 * ============================================================
 *  Endpoint near-real-time via long-polling / short-polling.
 *  Dipanggil oleh frontend secara berkala untuk mendapatkan
 *  update status (notifikasi, absensi aktif, dsb.)
 * ============================================================
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Hanya izinkan AJAX request
if (!isAjax()) {
    jsonResponse(['error' => 'Forbidden'], 403);
}

// Harus login
if (!isLoggedIn()) {
    jsonResponse(['error' => 'Unauthenticated'], 401);
}

$action  = get('action', '');
$userId  = (int) ($_SESSION['user_id'] ?? 0);
$db      = getDB();

// Update last_seen setiap polling
logActivity($userId, false);

// ── Router aksi polling ───────────────────────────────────────
switch ($action) {

    // Cek apakah ada sesi absensi yang sedang buka untuk kelas siswa
    case 'cek_sesi_absensi':
        $stmt = $db->prepare('
            SELECT ske.kelas_ekskul
            FROM siswa_kelas_ekskul ske
            WHERE ske.siswa_id = ?
        ');
        $stmt->execute([$userId]);
        $kelasEkskul = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($kelasEkskul)) {
            jsonResponse(['sesi_aktif' => []]);
        }

        $placeholders = implode(',', array_fill(0, count($kelasEkskul), '?'));
        $stmt = $db->prepare("
            SELECT id, kelas_ekskul, tanggal
            FROM sesi_absensi
            WHERE status = 'buka'
              AND kelas_ekskul IN ($placeholders)
        ");
        $stmt->execute($kelasEkskul);
        jsonResponse(['sesi_aktif' => $stmt->fetchAll()]);
        break;

    // Cek status tahun ajaran aktif dan aktivitas umum lainnya
    case 'ping':
        $taAktif = $db->query("SELECT id, tahun, semester FROM tahun_ajaran WHERE status = 'aktif' LIMIT 1")->fetch();
        
        jsonResponse([
            'status'          => 'ok',
            'timestamp'       => date('Y-m-d H:i:s'),
            'user_id'         => $userId,
            'tahun_ajaran_id' => $taAktif ? (int)$taAktif['id'] : null,
            'tahun_ajaran'    => $taAktif ? $taAktif['tahun'] . ' - ' . $taAktif['semester'] : null
        ]);
        break;

    default:
        jsonResponse(['error' => 'Unknown action'], 400);
}
