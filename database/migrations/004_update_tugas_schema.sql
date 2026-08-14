-- ============================================================
--  Migration 004 — Update Tugas Schema
--  Menambah kelas_ekskul, target, dan dibuat_oleh pada tugas
--  agar tugas bisa berdiri sendiri tanpa materi.
-- ============================================================

PRAGMA foreign_keys=OFF;

-- Rename tabel lama
ALTER TABLE tugas RENAME TO tugas_old;

-- Buat tabel baru dengan kolom tambahan
CREATE TABLE tugas (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    materi_id       INTEGER REFERENCES materi(id) ON DELETE SET NULL,  
    kelas_ekskul    TEXT    NOT NULL,
    judul           TEXT    NOT NULL,
    deadline        TEXT    NOT NULL,   
    target          TEXT    NOT NULL DEFAULT 'Semua Jenjang'
                            CHECK(target IN ('Semua Jenjang','MTs','MA')),
    tahun_ajaran_id INTEGER NOT NULL REFERENCES tahun_ajaran(id) ON DELETE RESTRICT,
    dibuat_oleh     INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

-- Note: Karena data tugas masih kosong (baru development), 
-- kita tidak perlu copy data lama yang tidak valid (karena kurang foreign key buat_oleh & kelas_ekskul).
-- Jika sudah ada data, ini akan bermasalah. Berasumsi masih kosong/bisa di-drop.

INSERT INTO tugas (id, materi_id, kelas_ekskul, judul, deadline, target, tahun_ajaran_id, dibuat_oleh, created_at)
SELECT 
    t.id, t.materi_id, 
    COALESCE(m.kelas_ekskul, 'Unknown'), 
    t.judul, t.deadline, 
    COALESCE(m.target, 'Semua Jenjang'), 
    t.tahun_ajaran_id, 
    COALESCE(m.dibuat_oleh, 1), 
    t.created_at
FROM tugas_old t
LEFT JOIN materi m ON t.materi_id = m.id;

-- Drop tabel lama
DROP TABLE tugas_old;

-- Recreate index
CREATE INDEX IF NOT EXISTS idx_tugas_materi ON tugas(materi_id);
CREATE INDEX IF NOT EXISTS idx_tugas_ta     ON tugas(tahun_ajaran_id);
CREATE INDEX IF NOT EXISTS idx_tugas_kelas  ON tugas(kelas_ekskul);
CREATE INDEX IF NOT EXISTS idx_tugas_pembimbing ON tugas(dibuat_oleh);

PRAGMA foreign_keys=ON;
