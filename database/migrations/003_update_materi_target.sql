-- ============================================================
--  Migration 003 — Update Materi Target Enum
--  Mengubah kolom target dari ('semua','siswa_tertentu') 
--  menjadi ('Semua Jenjang','MTs','MA')
-- ============================================================

PRAGMA foreign_keys=OFF;

-- Rename tabel lama
ALTER TABLE materi RENAME TO materi_old;

-- Buat tabel baru dengan constraint yang diupdate
CREATE TABLE materi (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    kelas_ekskul   TEXT    NOT NULL,
    judul          TEXT    NOT NULL,
    link_file      TEXT    NOT NULL,   
    tipe           TEXT    NOT NULL CHECK(tipe IN ('pdf','ppt','video')),
    target         TEXT    NOT NULL DEFAULT 'Semua Jenjang'
                           CHECK(target IN ('Semua Jenjang','MTs','MA')),
    tahun_ajaran_id INTEGER NOT NULL REFERENCES tahun_ajaran(id) ON DELETE RESTRICT,
    dibuat_oleh    INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at     TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

-- Copy data dari tabel lama ke baru (mengubah 'semua' menjadi 'Semua Jenjang')
INSERT INTO materi (id, kelas_ekskul, judul, link_file, tipe, target, tahun_ajaran_id, dibuat_oleh, created_at)
SELECT 
    id, kelas_ekskul, judul, link_file, tipe, 
    CASE WHEN target = 'semua' THEN 'Semua Jenjang' ELSE 'Semua Jenjang' END, 
    tahun_ajaran_id, dibuat_oleh, created_at 
FROM materi_old;

-- Drop tabel lama
DROP TABLE materi_old;

-- Bikin ulang index
CREATE INDEX IF NOT EXISTS idx_materi_kelas      ON materi(kelas_ekskul);
CREATE INDEX IF NOT EXISTS idx_materi_ta         ON materi(tahun_ajaran_id);
CREATE INDEX IF NOT EXISTS idx_materi_pembimbing ON materi(dibuat_oleh);

PRAGMA foreign_keys=ON;
