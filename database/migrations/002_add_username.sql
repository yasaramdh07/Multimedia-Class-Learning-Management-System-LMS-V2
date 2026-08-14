-- ============================================================
--  Migration 002 — Tambah kolom username
--  Jalankan via: php database/migrate.php
-- ============================================================

PRAGMA foreign_keys=ON;

-- SQLite tidak mendukung ADD COLUMN dengan constraints (seperti UNIQUE atau NOT NULL tanpa DEFAULT) secara langsung dengan aman jika tabel sudah berisi data.
-- Namun, untuk SQLite >= 3.25.0, ADD COLUMN didukung secara terbatas.
-- Untuk amannya (menghindari rebuild tabel manual), kita akan tambahkan kolom biasa dulu,
-- lalu buat UNIQUE INDEX untuk menjaga keunikannya.

ALTER TABLE users ADD COLUMN username TEXT;

-- Kita perlu pastikan username yang kosong di-set menjadi null atau unique value.
-- Jika sudah ada data, biarkan NULL dulu (SQLite memperbolehkan multiple NULL pada unique constraint).

CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users(username);
