# Product Requirements Document (PRD)
# Multimedia Class — Sistem Pembelajaran Ekskul Multimedia YABIKA

**Versi:** 1.0
**Tanggal:** 29 Juli 2026
**Disusun untuk:** Ekstrakurikuler Multimedia — MTs & MA YABIKA
**Dibuat dengan bantuan:** Claude (drafting untuk implementasi via Antigravity)

---

## 1. Latar Belakang & Tujuan

Ekstrakurikuler Multimedia di MTs & MA YABIKA membina 3 bidang: **Editing Video & Desain Grafis**, **Arduino Robotic**, dan **Web Development**. Ekskul ini dibimbing oleh beberapa pembimbing (bukan hanya satu orang), sehingga dibutuhkan sistem terpusat yang bisa diakses siswa dan pembimbing secara near-real-time untuk:

- Menggantikan pencatatan manual absensi, tugas, dan progres belajar.
- Memberi visibilitas roadmap belajar per siswa (progress bar).
- Memberi admin kontrol penuh atas data pengguna dan periode ajaran.

**Tujuan produk:**
1. Satu sistem terpadu untuk 3 role: Admin, Pembimbing, Siswa.
2. Progres belajar & tugas terlacak otomatis per semester/tahun ajaran.
3. Absensi anti-titip lewat verifikasi lokasi (radius).
4. Bisa dijalankan lokal di Laragon saat development, dan dipindah ke hosting tanpa perombakan besar.

---

## 2. Asumsi & Keputusan Teknis (Disepakati)

| Area | Keputusan |
|---|---|
| Backend | **PHP** (native atau minimal framework-less dulu, sesuai gaya Brulee project) |
| Database | **SQLite**, mode **WAL** (Write-Ahead Logging) untuk konkurensi multi-user |
| Frontend | HTML, CSS, JavaScript (vanilla), **Bootstrap** untuk komponen & ikon |
| UI Style | Flat/modern minimalis, "school vibes", **bukan glassmorphism** — palet warna mengikuti logo (biru muda/periwinkle + putih) |
| File materi (pdf/ppt/video) & foto profil | **Link eksternal** (Google Drive, dsb) — bukan upload ke server |
| Real-time | **AJAX polling** (interval beberapa detik), bukan WebSocket — cukup untuk kebutuhan status online, progress, & perubahan semester |
| Dev environment | **Laragon** (lokal) |
| Deployment target | Hosting shared/VPS — struktur harus portable (path relatif, config env terpisah) |
| Absensi | **Manual** oleh pembimbing (roll-call langsung saat ekskul jalan) — status: Hadir/Izin/Sakit. *(Tidak jadi pakai verifikasi lokasi/geolocation)* |
| Password | Disimpan agar bisa **dilihat admin** (bukan hash satu arah) — *catatan risiko di §9* |

---

## 3. Struktur Peran (Roles)

### 3.1 Admin
Kontrol penuh sistem: kelola akun, tahun ajaran/semester, approval pembimbing & siswa, monitoring aktivitas.

### 3.2 Pembimbing (Mentor)
Mengelola kelas yang diampu: materi, tugas, absensi, penilaian, progres siswa.

### 3.3 Siswa
Mengikuti pembelajaran: absen (dengan verifikasi lokasi), belajar materi, kumpul tugas, pantau progres & nilai.

---

## 4. Fitur per Role

### 4.1 Admin

**4.1.1 Autentikasi**
- Login akun tunggal (bukan sistem daftar): `Admin_Multimedia` / `multimediayabika09` (disimpan di konfigurasi awal, disarankan bisa diganti dari settings).

**4.1.2 Manajemen Pengguna**
- Approve/reject pendaftaran pembimbing & siswa.
- Approve reset password (baik dari siswa maupun pembimbing).
- Approve pengeluaran siswa (dari pengajuan pembimbing).
- Tambah/edit/hapus akun pembimbing & siswa.
- Lihat password semua user (lihat catatan risiko §9).
- Lihat siapa yang sedang online/aktif mengakses sistem.

**4.1.3 Manajemen Periode Ajaran**
- Input tahun ajaran aktif (mis. 2026/2027) & semester (Ganjil/Genap).
- Tombol "Akhiri Semester" → mengunci data semester berjalan, otomatis mengalirkan perubahan periode ke seluruh dashboard pembimbing & siswa (via polling).

**4.1.4 Monitoring**
- Lihat nilai & progres seluruh siswa (read-only — tidak bisa mengubah nilai/kehadiran, hanya pembimbing yang berwenang).

### 4.2 Pembimbing

**4.2.1 Pendaftaran & Autentikasi**
- Form daftar: nama, umur, jenis kelamin, pas foto 3x4 (link/unggah sesuai keputusan §2), kelas yang diampu (Web/Desain-Editing/Arduino), email, no. HP, sosial media, password bebas format.
- Status akun "pending" sampai disetujui admin.
- Lupa password → ajukan reset → menunggu approval admin.
- Ganti password → wajib input password lama dahulu.

**4.2.2 Dashboard & Manajemen Kelas**
1. Cek kehadiran/absensi tiap siswa.
2. Cek progres belajar & tugas per siswa.
3. Tambah/edit materi & tugas — untuk seluruh kelas atau per siswa tertentu.
4. Kirim peringatan ke siswa (tidak hadir / tidak mengerjakan tugas).
5. Ajukan pengeluaran siswa (butuh approval admin) untuk siswa yang diabaikan peringatannya.
6. Absensi dilakukan **manual** oleh pembimbing: buka sesi absensi saat ekskul berlangsung (mis. tiap Jumat), lalu tandai status tiap siswa satu per satu — **Hadir / Izin / Sakit** (tanpa verifikasi lokasi). Rekap absensi bulanan seluruh siswa tersedia dari data ini. Baca materi & kumpul tugas tetap terbuka kapan saja kecuali ditutup manual oleh pembimbing (deadline).
7. Beri nilai per siswa berdasarkan kombinasi kehadiran + progres.
8. Lihat status siswa aktif/tidak aktif.
9. Lihat progres belajar tiap siswa (macet di tahap mana).
10. Export rekap absensi & nilai ke **PDF**.
11. Materi bisa berupa link PDF/PPT/Video (lihat §2).
12. Kelola profil sendiri (edit data).

**4.2.3 Aturan Ceklis**
- Ceklis **materi/pelajaran**: bisa dilakukan siswa sendiri.
- Ceklis **tugas** (progress selesai): **hanya pembimbing** yang boleh centang, setelah memverifikasi submission siswa.

### 4.3 Siswa

**4.3.1 Pendaftaran & Autentikasi**
- Form daftar: nama, kelas, jenjang (MTs/MA), tahun ajaran, pilihan kelas ekskul (bisa lebih dari satu), wali kelas, no. WA aktif, nama orang tua/wali, no. HP orang tua, email aktif, alamat domisili, pas foto 3x4, password bebas format.
- Status "pending" sampai disetujui admin.
- Lupa password → ajukan reset → approval admin. Ganti password wajib isi password lama.

**4.3.2 Dashboard**
1. Lihat & edit profil sendiri.
2. Lihat progres pembelajaran (progress bar).
3. Lihat roadmap tugas & materi (mengikuti struktur yang dibuat pembimbing).
4. Terima notifikasi peringatan dari pembimbing.
5. **Absensi manual oleh pembimbing**: siswa tidak absen sendiri lewat sistem — status kehadiran (Hadir/Izin/Sakit) diinput langsung oleh pembimbing saat roll-call di kelas. Siswa hanya bisa melihat hasil rekap absensinya di dashboard.
6. Kumpul tugas: folder zip, foto, video, link Google Drive, atau link postingan — format menyesuaikan ketentuan pembimbing per tugas. Setelah dikumpulkan, status "menunggu verifikasi pembimbing" (ceklis progress baru muncul setelah pembimbing approve).
7. Ceklis materi/pelajaran bisa dilakukan mandiri oleh siswa.
8. Di akhir semester: lihat rekap progres & total nilai selama satu semester.

---

## 5. Model Data (Rancangan Awal Entitas)

Rancangan tabel inti (disesuaikan lagi saat implementasi):

- **users** — id, role (admin/pembimbing/siswa), nama, email, no_hp, password, status (pending/approved/rejected), foto_profil_link, created_at
- **pembimbing_detail** — user_id, umur, jenis_kelamin, kelas_diampu, sosial_media
- **siswa_detail** — user_id, kelas, jenjang, wali_kelas, no_wa, nama_ortu, no_hp_ortu, alamat_domisili
- **siswa_kelas_ekskul** — siswa_id, kelas_ekskul (many-to-many, siswa bisa pilih >1 kelas)
- **tahun_ajaran** — id, tahun (mis. "2026/2027"), semester (Ganjil/Genap), status (aktif/berakhir)
- **materi** — id, kelas_ekskul, judul, link_file, tipe (pdf/ppt/video), target (semua/siswa tertentu), tahun_ajaran_id, dibuat_oleh (pembimbing_id)
- **tugas** — id, materi_id (opsional), judul, deadline, tahun_ajaran_id
- **submission_tugas** — id, tugas_id, siswa_id, jenis_kumpul (zip/foto/video/link), link_bukti, status (menunggu/diterima), diverifikasi_oleh, waktu_submit
- **progress_materi** — siswa_id, materi_id, status_ceklis (oleh siswa)
- **sesi_absensi** — id, pembimbing_id, kelas_ekskul, tanggal, status (buka/tutup)
- **absensi** — id, sesi_absensi_id, siswa_id, status (hadir/izin/sakit), waktu_dicatat, dicatat_oleh (pembimbing_id)
- **peringatan** — id, siswa_id, pembimbing_id, alasan, tanggal
- **pengajuan_pengeluaran** — id, siswa_id, pembimbing_id, alasan, status_approval_admin
- **nilai** — id, siswa_id, tahun_ajaran_id, nilai_kehadiran, nilai_progress, nilai_total, diberikan_oleh
- **activity_log** — user_id, waktu_login, waktu_logout/last_seen (untuk fitur "siapa yang online")

---

## 6. UI/UX Guidelines

- Gaya **flat/modern minimalis** — *bukan glassmorphism* (dianggap terlalu generik/"AI-generated look"). Fokus ke clean layout, whitespace cukup, kartu/card dengan shadow tipis & rounded corners secukupnya (bukan efek blur/transparan berlapis).
- Palet warna mengikuti **logo Multimedia YABIKA**: dominan **biru muda (periwinkle blue)** dengan putih sebagai base, aksen bisa diambil dari warna logo (biru muda gradasi lembut). Hindari warna yang kontras tajam dengan nuansa logo.
- Font: pilih font sans-serif yang **friendly & sedikit playful** (rounded terminal) agar senada dengan gaya tipografi logo (bold, rounded, sedikit "chunky") — mis. Baloo 2, Quicksand, atau Fredoka untuk heading; Poppins/Inter untuk body text agar tetap terbaca rapi.
- Aksen ikon bisa terinspirasi elemen logo (petir/electric, speech bubble) untuk mempertegas nuansa "digital creativity" tanpa mengulang logo secara literal.
- Framework CSS: **Bootstrap** (komponen + ikon, mis. Bootstrap Icons) — dikustomisasi warnanya sesuai palet biru muda di atas, bukan warna default Bootstrap.
- Nuansa keseluruhan: minimalis, ringan, "school vibes" yang modern & ramah — bukan berat/dekoratif.
- Setiap dashboard (Admin/Pembimbing/Siswa) menyertakan link ke **website Multimedia** dan **Instagram Multimedia**.
- Logo Multimedia & YABIKA sudah tersedia di `assets/logo`.

---

## 7. Struktur Folder (Disarankan)

```
multimedia-class/
├── assets/
│   ├── logo/                  (sudah tersedia)
│   ├── css/
│   ├── js/
│   └── img/
├── config/
│   └── database.php           (path SQLite, env-aware: lokal vs hosting)
├── admin/
│   ├── dashboard.php
│   ├── users.php
│   ├── tahun-ajaran.php
│   └── ...
├── pembimbing/
│   ├── dashboard.php
│   ├── absensi.php
│   ├── materi.php
│   ├── tugas.php
│   ├── nilai.php
│   └── ...
├── siswa/
│   ├── dashboard.php
│   ├── absensi.php
│   ├── progress.php
│   ├── tugas.php
│   └── ...
├── includes/
│   ├── auth.php
│   ├── functions.php
│   └── polling-handler.php    (endpoint AJAX untuk update near-real-time)
├── database/
│   └── multimedia_class.sqlite
└── index.php                  (landing/login router)
```

Catatan portabilitas Laragon → Hosting:
- Semua path file pakai **path relatif**, tidak hardcode `localhost` atau path absolut Laragon.
- Koneksi database & base URL diletakkan di satu file config (`config/database.php` / `config/app.php`) yang bisa diubah cukup di satu tempat saat pindah ke hosting.
- File SQLite diletakkan di luar folder yang bisa diakses langsung via URL (atau diberi proteksi `.htaccess`) supaya tidak bisa didownload publik.

---

## 8. Kebutuhan Non-Fungsional

- **Konkurensi:** SQLite WAL mode aktif dari awal untuk menghindari database lock saat banyak siswa mengakses bersamaan (terutama saat sesi absensi).
- **Near-real-time:** AJAX polling interval singkat (mis. tiap 5-10 detik) untuk: status online, perubahan tahun ajaran/semester, progress bar, notifikasi peringatan.
- **Export:** Rekap absensi & nilai bisa diexport ke PDF (dan idealnya juga Excel, opsional).
- **Skalabilitas ringan:** Sistem ditujukan untuk skala ekskul sekolah (puluhan-ratusan siswa), bukan skala enterprise — SQLite cukup memadai selama WAL diaktifkan.

---

## 9. Risiko & Catatan Penting

- **Password terlihat admin:** Menyimpan password dalam bentuk yang bisa dibaca kembali oleh admin (bukan hash satu arah) adalah trade-off keamanan yang disengaja untuk kemudahan operasional ekskul. Untuk sistem skala kecil/internal ini risikonya relatif rendah, tapi jika suatu saat ingin ditingkatkan keamanannya, ini adalah kandidat pertama untuk direvisi (mis. pakai reversible encryption dengan admin key, bukan plaintext).
- **File eksternal (Google Drive dll):** perlu ketentuan jelas ke siswa soal permission link (misal: "Anyone with the link can view") supaya pembimbing tidak kesulitan mengakses.

---

## 10. Di Luar Cakupan (Out of Scope) — v1

- Notifikasi push/email otomatis (v1 cukup notifikasi in-app).
- Live video conference / streaming kelas.
- Multi-sekolah/multi-tenant (sistem ini khusus untuk YABIKA).

---

## 11. Langkah Selanjutnya

1. Review PRD ini bersama pembimbing lain (jika perlu masukan tambahan).
2. Finalisasi struktur tabel database (SQL schema).
3. Susun wireframe/mockup untuk 3 dashboard (Admin, Pembimbing, Siswa).
4. Breakdown ke task list implementasi per modul untuk dieksekusi via Antigravity.