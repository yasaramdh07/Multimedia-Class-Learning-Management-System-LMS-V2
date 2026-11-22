-- ============================================================
--  Migration 001 — Tambah tabel untuk auth sistem
--  Jalankan via: php database/migrate.php
-- ============================================================

PRAGMA foreign_keys=ON;

-- Tabel pengajuan reset password (oleh pembimbing/siswa)
-- Admin yang approve, lalu password baru di-set
CREATE TABLE IF NOT EXISTS password_reset_requests (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status       TEXT    NOT NULL DEFAULT 'menunggu'
                         CHECK(status IN ('menunggu','disetujui','ditolak')),
    new_password TEXT,              -- diisi user saat ajukan, admin lihat & approve
    catatan      TEXT,              -- catatan dari admin saat reject
    dibuat_pada  TEXT NOT NULL DEFAULT (datetime('now','localtime')),
    diproses_pada TEXT,
    diproses_oleh INTEGER REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_pwreset_user   ON password_reset_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_pwreset_status ON password_reset_requests(status);

-- Tabel settings aplikasi (key-value)
-- Digunakan untuk menyimpan: username/password admin, dll.
CREATE TABLE IF NOT EXISTS app_settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
