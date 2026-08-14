-- ============================================================
--  Multimedia Class — Schema Database
--  Engine : SQLite 3
--  Catatan: Jalankan PRAGMA journal_mode=WAL; sebelum CREATE TABLE
--           (sudah di-handle otomatis di config/database.php)
-- ============================================================

PRAGMA journal_mode=WAL;
PRAGMA foreign_keys=ON;


-- ==============================================================
--  1. USERS
--  Tabel induk semua pengguna (admin / pembimbing / siswa).
--  File profil DISIMPAN SEBAGAI LINK (Google Drive, dll.),
--  bukan path fisik.
-- ==============================================================
CREATE TABLE IF NOT EXISTS users (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    role             TEXT    NOT NULL CHECK(role IN ('admin','pembimbing','siswa')),
    nama             TEXT    NOT NULL,
    email            TEXT    NOT NULL UNIQUE,
    username         TEXT    UNIQUE,
    no_hp            TEXT,
    password         TEXT    NOT NULL,                 -- bcrypt hash
    status           TEXT    NOT NULL DEFAULT 'pending'
                             CHECK(status IN ('pending','approved','rejected')),
    foto_profil_link TEXT,                             -- URL (Google Drive, dll.)
    created_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE INDEX IF NOT EXISTS idx_users_role     ON users(role);
CREATE INDEX IF NOT EXISTS idx_users_status   ON users(status);
CREATE INDEX IF NOT EXISTS idx_users_email    ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);


-- ==============================================================
--  2. PEMBIMBING_DETAIL
--  Data tambahan khusus pembimbing.
-- ==============================================================
CREATE TABLE IF NOT EXISTS pembimbing_detail (
    user_id       INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    umur          INTEGER,
    jenis_kelamin TEXT CHECK(jenis_kelamin IN ('L','P')),
    kelas_diampu  TEXT,                -- contoh: 'Fotografi,Videografi'
    sosial_media  TEXT                 -- JSON atau plain string (IG, TikTok, dll.)
);


-- ==============================================================
--  3. SISWA_DETAIL
--  Data tambahan khusus siswa.
-- ==============================================================
CREATE TABLE IF NOT EXISTS siswa_detail (
    user_id           INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    kelas             TEXT,            -- contoh: '8A', '10 IPA 1'
    jenjang           TEXT CHECK(jenjang IN ('MTs','MA')),
    wali_kelas        TEXT,
    no_wa             TEXT,
    nama_ortu         TEXT,
    no_hp_ortu        TEXT,
    alamat_domisili   TEXT
);


-- ==============================================================
--  4. SISWA_KELAS_EKSKUL  (Many-to-Many)
--  Satu siswa bisa memilih lebih dari satu kelas ekskul.
-- ==============================================================
CREATE TABLE IF NOT EXISTS siswa_kelas_ekskul (
    siswa_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    kelas_ekskul TEXT   NOT NULL,      -- contoh: 'Fotografi', 'Videografi', 'Desain Grafis'
    PRIMARY KEY (siswa_id, kelas_ekskul)
);

CREATE INDEX IF NOT EXISTS idx_ske_kelas ON siswa_kelas_ekskul(kelas_ekskul);


-- ==============================================================
--  5. TAHUN_AJARAN
-- ==============================================================
CREATE TABLE IF NOT EXISTS tahun_ajaran (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    tahun    TEXT NOT NULL,            -- contoh: '2025/2026'
    semester TEXT NOT NULL CHECK(semester IN ('Ganjil','Genap')),
    status   TEXT NOT NULL DEFAULT 'aktif'
                  CHECK(status IN ('aktif','berakhir')),
    UNIQUE(tahun, semester)
);


-- ==============================================================
--  6. MATERI
--  link_file = URL eksternal (Google Drive, YouTube, dsb.).
-- ==============================================================
CREATE TABLE IF NOT EXISTS materi (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    kelas_ekskul   TEXT    NOT NULL,
    judul          TEXT    NOT NULL,
    link_file      TEXT    NOT NULL,   -- URL, bukan path fisik
    tipe           TEXT    NOT NULL CHECK(tipe IN ('pdf','ppt','video')),
    target         TEXT    NOT NULL DEFAULT 'Semua Jenjang'
                           CHECK(target IN ('Semua Jenjang','MTs','MA')),
    tahun_ajaran_id INTEGER NOT NULL REFERENCES tahun_ajaran(id) ON DELETE RESTRICT,
    dibuat_oleh    INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at     TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE INDEX IF NOT EXISTS idx_materi_kelas      ON materi(kelas_ekskul);
CREATE INDEX IF NOT EXISTS idx_materi_ta         ON materi(tahun_ajaran_id);
CREATE INDEX IF NOT EXISTS idx_materi_pembimbing ON materi(dibuat_oleh);


-- ==============================================================
--  7. TUGAS
--  Bisa terikat ke materi (materi_id nullable) atau berdiri sendiri.
-- ==============================================================
CREATE TABLE IF NOT EXISTS tugas (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    materi_id       INTEGER REFERENCES materi(id) ON DELETE SET NULL,  -- nullable
    kelas_ekskul    TEXT    NOT NULL,
    judul           TEXT    NOT NULL,
    deskripsi       TEXT    NOT NULL DEFAULT '',
    link_pengumpulan TEXT    NOT NULL DEFAULT '',
    deadline        TEXT    NOT NULL,   -- ISO 8601: 'YYYY-MM-DD HH:MM:SS'
    target          TEXT    NOT NULL DEFAULT 'Semua Jenjang'
                            CHECK(target IN ('Semua Jenjang','MTs','MA')),
    tahun_ajaran_id INTEGER NOT NULL REFERENCES tahun_ajaran(id) ON DELETE RESTRICT,
    dibuat_oleh     INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    created_at      TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE INDEX IF NOT EXISTS idx_tugas_materi ON tugas(materi_id);
CREATE INDEX IF NOT EXISTS idx_tugas_ta     ON tugas(tahun_ajaran_id);
CREATE INDEX IF NOT EXISTS idx_tugas_kelas  ON tugas(kelas_ekskul);
CREATE INDEX IF NOT EXISTS idx_tugas_pembimbing ON tugas(dibuat_oleh);


-- ==============================================================
--  8. SUBMISSION_TUGAS
--  link_bukti = URL (Google Drive, YouTube, link publik, dsb.)
-- ==============================================================
CREATE TABLE IF NOT EXISTS submission_tugas (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    tugas_id        INTEGER NOT NULL REFERENCES tugas(id) ON DELETE CASCADE,
    siswa_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    jenis_kumpul    TEXT    NOT NULL CHECK(jenis_kumpul IN ('zip','foto','video','link')),
    link_bukti      TEXT    NOT NULL,   -- URL, bukan upload fisik
    status          TEXT    NOT NULL DEFAULT 'menunggu'
                            CHECK(status IN ('menunggu','diterima')),
    diverifikasi_oleh INTEGER REFERENCES users(id) ON DELETE SET NULL,
    waktu_submit    TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    UNIQUE(tugas_id, siswa_id)          -- 1 siswa 1 submission per tugas
);

CREATE INDEX IF NOT EXISTS idx_sub_tugas ON submission_tugas(tugas_id);
CREATE INDEX IF NOT EXISTS idx_sub_siswa ON submission_tugas(siswa_id);


-- ==============================================================
--  9. PROGRESS_MATERI
--  Ceklis baca/tonton materi per siswa.
-- ==============================================================
CREATE TABLE IF NOT EXISTS progress_materi (
    siswa_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    materi_id    INTEGER NOT NULL REFERENCES materi(id) ON DELETE CASCADE,
    status_ceklis INTEGER NOT NULL DEFAULT 0 CHECK(status_ceklis IN (0,1)),  -- 0=belum, 1=selesai
    updated_at   TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    PRIMARY KEY (siswa_id, materi_id)
);


-- ==============================================================
--  10. SESI_ABSENSI
--  Pembimbing membuka/menutup sesi absensi per kelas per tanggal.
-- ==============================================================
CREATE TABLE IF NOT EXISTS sesi_absensi (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    pembimbing_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    kelas_ekskul  TEXT    NOT NULL,
    tanggal       TEXT    NOT NULL,   -- 'YYYY-MM-DD'
    status        TEXT    NOT NULL DEFAULT 'buka'
                          CHECK(status IN ('buka','tutup')),
    dibuka_pada   TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    ditutup_pada  TEXT
);

CREATE INDEX IF NOT EXISTS idx_sesi_pembimbing ON sesi_absensi(pembimbing_id);
CREATE INDEX IF NOT EXISTS idx_sesi_kelas      ON sesi_absensi(kelas_ekskul);
CREATE INDEX IF NOT EXISTS idx_sesi_tanggal    ON sesi_absensi(tanggal);


-- ==============================================================
--  11. ABSENSI
--  dicatat_oleh bisa pembimbing (isi manual) atau siswa sendiri (self-absen).
-- ==============================================================
CREATE TABLE IF NOT EXISTS absensi (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    sesi_absensi_id  INTEGER NOT NULL REFERENCES sesi_absensi(id) ON DELETE CASCADE,
    siswa_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status           TEXT    NOT NULL CHECK(status IN ('hadir','izin','sakit')),
    waktu_dicatat    TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    dicatat_oleh     INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE(sesi_absensi_id, siswa_id)  -- 1 siswa 1 catatan per sesi
);

CREATE INDEX IF NOT EXISTS idx_absensi_sesi  ON absensi(sesi_absensi_id);
CREATE INDEX IF NOT EXISTS idx_absensi_siswa ON absensi(siswa_id);


-- ==============================================================
--  12. PERINGATAN
--  Pembimbing memberikan peringatan ke siswa.
-- ==============================================================
CREATE TABLE IF NOT EXISTS peringatan (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    siswa_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    pembimbing_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    alasan        TEXT    NOT NULL,
    tanggal       TEXT    NOT NULL DEFAULT (date('now','localtime'))
);

CREATE INDEX IF NOT EXISTS idx_peringatan_siswa ON peringatan(siswa_id);


-- ==============================================================
--  13. PENGAJUAN_PENGELUARAN
--  Siswa mengajukan, disetujui admin.
-- ==============================================================
CREATE TABLE IF NOT EXISTS pengajuan_pengeluaran (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    siswa_id              INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    pembimbing_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
    alasan                TEXT    NOT NULL,
    status_approval_admin TEXT    NOT NULL DEFAULT 'menunggu'
                                  CHECK(status_approval_admin IN ('menunggu','disetujui','ditolak')),
    created_at            TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE INDEX IF NOT EXISTS idx_pengajuan_siswa ON pengajuan_pengeluaran(siswa_id);


-- ==============================================================
--  14. NILAI
--  Rekap nilai akhir per siswa per tahun ajaran.
-- ==============================================================
CREATE TABLE IF NOT EXISTS nilai (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    siswa_id         INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    tahun_ajaran_id  INTEGER NOT NULL REFERENCES tahun_ajaran(id) ON DELETE RESTRICT,
    nilai_kehadiran  REAL    DEFAULT 0,
    nilai_progress   REAL    DEFAULT 0,
    nilai_total      REAL    DEFAULT 0,   -- bisa dihitung computed atau disimpan manual
    diberikan_oleh   INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_at       TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    UNIQUE(siswa_id, tahun_ajaran_id)
);

CREATE INDEX IF NOT EXISTS idx_nilai_siswa ON nilai(siswa_id);
CREATE INDEX IF NOT EXISTS idx_nilai_ta    ON nilai(tahun_ajaran_id);


-- ==============================================================
--  15. ACTIVITY_LOG
--  Tracking login & last_seen per user (untuk polling near-real-time).
-- ==============================================================
CREATE TABLE IF NOT EXISTS activity_log (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    waktu_login  TEXT    NOT NULL DEFAULT (datetime('now','localtime')),
    last_seen    TEXT    NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE INDEX IF NOT EXISTS idx_actlog_user     ON activity_log(user_id);
CREATE INDEX IF NOT EXISTS idx_actlog_lastseen ON activity_log(last_seen);
