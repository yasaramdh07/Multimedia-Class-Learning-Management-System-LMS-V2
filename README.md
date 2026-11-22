# Multimedia YABIKA-LMS (Learning Management System) - V2 🚀

![Status](https://img.shields.io/badge/Status-Completed-success)
![Version](https://img.shields.io/badge/Version-2.0-blue)
![Tech](https://img.shields.io/badge/Tech-PHP%20Native%20|%20SQLite%20|%20Bootstrap%205-orange)
![License](https://img.shields.io/badge/License-MIT-green)

**Multimedia YABIKA-LMS V2** adalah sebuah Sistem Manajemen Pembelajaran (*Learning Management System*) kustom yang dibangun secara khusus untuk mendukung, mengelola, dan mendigitalisasi kegiatan ekstrakurikuler **Robotik, Animasi, dan IoT (Internet of Things)** di lingkungan **MTs dan MA YABIKA**.

Aplikasi ini merupakan **versi upgrade besar-besaran (V2)** dari iterasi sebelumnya. Pada V2 ini, sistem dirombak ulang (dari segi UI dan arsitektur database) untuk mengakomodasi alur kerja sekolah yang lebih kompleks, dengan fokus utama pada pemisahan kurikulum berdasarkan jenjang (MTs vs MA), manajemen peran yang lebih ketat, dan integrasi simulator *embedded system* di dalam browser.

---

## 🎯 Latar Belakang & Tujuan
Ekstrakurikuler teknologi seringkali kesulitan dalam mendistribusikan modul materi teknis (kode, skema, dll) dan merekapitulasi kehadiran serta nilai secara efisien. YABIKA-LMS V2 dibangun untuk:
1. **Sentralisasi Materi**: Menyediakan satu portal di mana siswa dapat mengakses materi pelajaran secara terstruktur sesuai tingkatannya.
2. **Praktik Tanpa Hardware**: Mengintegrasikan simulator elektronik sehingga siswa yang belum memiliki komponen fisik tetap bisa mempraktikkan kode dan merangkai sirkuit.
3. **Efisiensi Administrasi**: Memudahkan pembimbing (tentor) untuk melakukan absensi (hanya 1 klik) dan merekapitulasi nilai yang langsung dapat diunduh (PDF) untuk dilaporkan ke pihak sekolah.

---

## ✨ Fitur & Fungsionalitas Detail

Aplikasi ini mengadopsi arsitektur *Role-Based Access Control* (RBAC) dengan 3 aktor utama:

### 1. 👑 Administrator (Super User)
Admin bertugas sebagai pemegang kendali utama seluruh sistem sekolah:
- **Approval System Terpusat**: Pendaftar baru (Siswa maupun Pembimbing) tidak langsung aktif. Akun mereka berstatus `Pending` dan wajib disetujui (Approve) atau ditolak (Reject) oleh Admin. Hal ini mencegah orang luar masuk ke sistem sekolah.
- **Manajemen Tahun Ajaran**: Admin dapat mengatur dan mengganti Tahun Ajaran (Semester Ganjil/Genap). Data absensi dan nilai akan terikat pada periode aktif ini.
- **Monitoring Progres Real-Time**: Melihat *overview* progres seluruh siswa dari semua kelas tanpa harus masuk ke kelas tersebut.
- **Factory Reset (Zona Berbahaya)**: Fitur khusus yang dilindungi PIN untuk menghapus semua data (Siswa, Nilai, dll) untuk persiapan memulai tahun ajaran yang benar-benar baru, tanpa menghapus konfigurasi dasar aplikasi.
- **Live Search Users**: Pencarian instan (berbasis JavaScript) di tabel pengguna tanpa *page reload*.

### 2. 👨‍🏫 Pembimbing (Tentor)
Fokus pada manajemen kelas dan evaluasi peserta didik:
- **Absensi Digital (1-Click)**: Pembimbing dapat membuka sesi kelas dan melakukan absensi (Hadir/Izin/Sakit/Alpa) hanya dengan sekali klik (menggunakan *fetch API* AJAX) tanpa me-reload halaman.
- **Verifikasi Tugas**: Siswa yang melaporkan tugasnya akan masuk ke panel Pembimbing. Pembimbing dapat mengecek tugas lalu memberikan *Approval* (ceklis hijau).
- **Kalkulasi & Input Nilai**: Sistem secara otomatis mengkalkulasi persentase kehadiran dan penyelesaian modul. Pembimbing dapat menginput nilai manual (Nilai Akhir).
- **Export Laporan (PDF)**: 1-Klik cetak rekapitulasi kehadiran dan nilai per kelas ke format PDF berkat integrasi library **FPDF**. Laporan ini siap diserahkan ke Wali Kelas/Sekolah.
- **Pengajuan Pengeluaran Siswa**: Pembimbing tidak bisa menghapus siswa secara permanen, namun dapat "Mengajukan Pengeluaran" kepada Admin disertai alasannya.

### 3. 🎓 Siswa
Dirancang dengan antarmuka (UI) yang ramah anak dan *student-centric*:
- **Kurikulum Dinamis**: Dashboard siswa akan menyesuaikan materi berdasarkan jenjangnya.
  - **Jenjang MTs (SMP)**: Fokus pada logika dasar dan elektronika simpel menggunakan Arduino Uno.
  - **Jenjang MA (SMA)**: Fokus pada pemrograman terstruktur (C++) dan IoT (Internet of Things) menggunakan **ESP32** (Sinyal analog/digital, WiFi, Sensor).
- **Integrasi Wokwi Simulator**: Setiap materi dilengkapi dengan simulator *embedded system* (Wokwi iframe). Siswa dapat mengetik kode dan melihat lampu LED menyala secara virtual langsung dari web.
- **Panel Progres**: Siswa dapat memantau secara mandiri sejauh mana materi yang telah ia pelesaikan, persentase kehadiran, dan nilai akhirnya.

---

## 🛠️ Tech Stack & Arsitektur

Project ini sengaja dibangun tanpa framework berat (seperti Laravel) untuk memastikan aplikasi sangat ringan, *portable*, dan mudah di-*deploy* di *shared hosting* biasa milik sekolah.

- **Bahasa Pemrograman**: PHP Native 8.x
- **Database**: SQLite3. Dipilih karena sifatnya yang file-based, tidak memerlukan konfigurasi server database terpisah, sangat cepat untuk skala sekolah (ratusan user), dan memudahkan backup (cukup *copy* satu file `database.sqlite`).
- **Keamanan (Security)**:
  - **CSRF Protection**: Setiap form di aplikasi ini dilindungi oleh token CSRF untuk mencegah serangan pemalsuan permintaan silang.
  - **Password Hashing**: Mengamankan password (meskipun ada fitur untuk admin melihat plain-text demi alasan administratif khusus sekolah).
  - **Auth Session**: *Session-based authentication* yang secara ketat memvalidasi role/peran pada setiap *request* halaman.
- **Frontend / Antarmuka**:
  - **Bootstrap 5**: Menjamin UI yang responsif (rapi di HP, tablet, maupun PC).
  - **Vanilla JavaScript**: Digunakan untuk interaksi *Real-time Polling* (update UI tanpa refresh), AJAX requests (absensi), dan *Live Search* DOM Traversal.
  - **Custom CSS Design System**: Tampilan untuk modul dibedakan; modul MTs lebih *vibrant/fun*, sedangkan modul MA mengadopsi tema *clean, minimalis, dan Mac-styled code block* (khas programmer profesional).

---

## 📸 Tampilan Antarmuka (Screenshots)
*(Tambahkan gambar screenshot aplikasi di sini untuk portofolio)*

- `[Screenshot Dashboard Admin]`
- `[Screenshot Modul Pembelajaran dengan Simulator]`
- `[Screenshot Fitur Export PDF Nilai]`
- `[Screenshot Tampilan Mobile / HP]`

---

## 🚀 Panduan Instalasi (Untuk Developer Lokal)

Bagi Anda yang ingin mencoba menjalankan kode ini secara lokal (menggunakan XAMPP, Laragon, dll):

1. **Clone Repository**
   ```bash
   git clone https://github.com/username-anda/multimedia-yabika-lms.git
   cd multimedia-yabika-lms
   ```
2. **Persyaratan Server**
   - Pastikan PHP (min. v8.0) dan ekstensi `pdo_sqlite` aktif di `php.ini`.
3. **Konfigurasi Folder**
   - Pastikan aplikasi berada di root web server Anda atau sesuaikan `BASE_URL` pada file `config/database.php`.
   ```php
   // Contoh jika menggunakan folder:
   define('BASE_URL', 'http://localhost/multimedia-yabika-lms');
   ```
4. **Database**
   - Database sudah disertakan (`database/database.sqlite`).
   - Jika Anda ingin mereset data, login sebagai Admin dan gunakan fitur *Factory Reset*.
5. **Akun Uji Coba Default**
   - Aplikasi siap digunakan (untuk akun demo silakan buat melalui halaman Register dan *approve* via SQLite manager, atau sediakan *seeder* mandiri).

---
*Developed with ❤️ for YABIKA Education by Ilyasa Ramadhany*
