# Software Requirements Specification (SRS)
# Aplikasi "Tiket Layanan" (Helpdesk Internal)

| Atribut | Nilai |
|---|---|
| Versi dokumen | 0.4 (draf) |
| Tanggal | 17 November 2026 |
| Penyusun | (isi nama asesi) |
| Skema sertifikasi | Web Developer, 03/02/LSPP1/SKM.03/01/2022 |
| Tujuan | Portofolio bukti 17 unit kompetensi FR.APL.02 |
| Status | Draf untuk ditinjau |

### Riwayat revisi

| Versi | Tanggal | Perubahan | Oleh |
|---|---|---|---|
| 0.1 | 16-11-2026 | Draf awal | (isi) |
| 0.2 | 16-11-2026 | Perjelas aturan tiket, SLA, alert, pengujian, dan keterlacakan 17 unit | (isi) |
| 0.3 | 16-11-2026 | Sesuaikan versi CI4 dan paket ekspor dengan hasil instalasi serta tambahkan sequence nomor tiket | (isi) |
| 0.4 | 17-11-2026 | Rencana rilis §9 disesuaikan dengan urutan kerja nyata (tag v0.8, v0.9-audit, v0.9, v1.0, v1.1); tambah endpoint acknowledge alert dan penugasan admin; tambah matriks status bukti `docs/MATRIKS-KETERLACAKAN.md` | (isi) |

---

## Daftar isi

1. Pendahuluan
2. Deskripsi umum
3. Analisis dan pemilihan teknologi (Unit 1, 3)
4. Kebutuhan fungsional
5. Kebutuhan non-fungsional
6. Kebutuhan data dan basis data
7. Kebutuhan antarmuka
8. Skenario data (demo dan uji unit)
9. Rencana rilis, migrasi, dan pembaruan
10. Rencana pengujian
11. Artefak pendukung di luar aplikasi utama
12. Matriks keterkaitan 17 unit kompetensi
13. Struktur repositori yang diharapkan
14. Lampiran: glosarium dan asumsi
15. Matriks keterlacakan elemen kompetensi

---

## 1. Pendahuluan

### 1.1 Tujuan dokumen

Dokumen ini mendefinisikan kebutuhan perangkat lunak aplikasi **Tiket Layanan**, yaitu sistem helpdesk internal berbasis web untuk mencatat, menindaklanjuti, dan memantau keluhan atau permintaan layanan. Dokumen ini juga menjadi acuan bagi pengembang (termasuk AI agent) dan bagi asesor untuk menelusuri bagaimana setiap fitur dan artefak memenuhi 17 unit kompetensi skema Web Developer.

### 1.2 Lingkup produk

Tiket Layanan memungkinkan:

- Pemohon membuat tiket, melampirkan berkas, dan memantau statusnya.
- Petugas mengambil, memproses, dan menyelesaikan tiket.
- Admin mengelola pengguna, kategori, melihat dashboard, alert, log, dan pemantauan resource server.

Aplikasi berjalan **lokal di XAMPP**. Tidak memakai layanan cloud (S3) maupun Telegram. Semua notifikasi dan alert ditampilkan di dalam web.

Di luar lingkup: aplikasi mobile, integrasi email/Telegram/WhatsApp, pembayaran, multi-tenant, deployment publik.

### 1.3 Definisi dan singkatan

| Istilah | Arti |
|---|---|
| Tiket | Satu catatan keluhan/permintaan layanan |
| SLA | Service Level Agreement, batas waktu penyelesaian tiket menurut prioritas |
| CI4 | CodeIgniter 4 |
| QB | Query Builder CI4 |
| OTP | One Time Password |
| Alert | Peringatan otomatis saat kondisi tertentu terpenuhi |
| Throttling | Pembatasan agar alert yang sama tidak berulang dalam selang waktu tertentu |
| A01 s.d. A17 | Kode dokumen bukti per unit kompetensi |

### 1.4 Referensi

- Tabel Final Pemetaan Portofolio PKL ke 17 Unit (`pkl/tabel-final-17-unit.md`).
- Daftar Cetak Asesmen BNSP (`pkl/daftar-cetak-asesmen.md`).
- IEEE Std 830-1998, Recommended Practice for Software Requirements Specifications (sebagai acuan struktur).
- Dokumentasi resmi CodeIgniter 4, MySQL/MariaDB, Chart.js, Bootstrap 5.

### 1.5 Gambaran dokumen

Bab 2 menjelaskan konteks dan pengguna. Bab 3 berisi alasan pemilihan teknologi. Bab 4 sampai 7 adalah kebutuhan inti. Bab 8 sampai 11 adalah data, rilis, pengujian, dan artefak pendukung. Bab 12 adalah matriks keterkaitan ke 17 unit.

---

## 2. Deskripsi umum

### 2.1 Perspektif produk

Aplikasi web mandiri (monolit MVC) dengan arsitektur:

```
Browser (Bootstrap + JS + Chart.js)
        │  HTTP (lokal, Apache XAMPP)
        ▼
CodeIgniter 4 (Controller → Service → Model)
        │  MySQLi driver / Query Builder
        ▼
MariaDB/MySQL (XAMPP)          Folder writable/uploads (lampiran)
```

### 2.2 Fungsi utama (ringkas)

1. Autentikasi dengan OTP yang ditampilkan di layar/log (tanpa layanan eksternal).
2. Manajemen tiket: buat, ubah, ambil, proses, selesaikan, tutup.
3. Komentar dan lampiran tiket.
4. Dashboard statistik dengan grafik dan slideshow animasi.
5. Notifikasi dan alert di dalam web dengan throttling dan pemulihan otomatis.
6. Pemantauan resource (CPU, RAM, disk, koneksi DB).
7. Ekspor laporan Excel/PDF.
8. Log aktivitas dan log error.

### 2.3 Kelas pengguna

| Peran | Deskripsi | Hak utama |
|---|---|---|
| Pemohon | Pegawai yang mengajukan layanan | Buat tiket, lihat tiket sendiri, komentar, unggah lampiran |
| Petugas | Staf yang menangani tiket | Lihat antrean, ambil tiket, ubah status, komentar, lihat alert |
| Admin | Pengelola sistem | Semua hak Petugas, kelola pengguna dan kategori, dashboard, monitor, ekspor, log |

### 2.4 Lingkungan operasi

| Komponen | Spesifikasi |
|---|---|
| OS | Windows 10/11 (pengembangan dan demo) |
| Server | XAMPP 8.2.x: Apache 2.4, PHP 8.2, MariaDB 10.4+ |
| Framework | CodeIgniter 4.7.x (via Composer; 4.7.6 pada implementasi lokal) |
| Browser uji | Chrome, Firefox, Edge versi terbaru |
| Perangkat | Laptop pribadi (spesifikasi dicatat di A02) |

### 2.5 Batasan desain dan implementasi

- Hanya berjalan lokal (localhost). Tanpa cloud dan tanpa layanan pesan eksternal.
- Bahasa antarmuka: Bahasa Indonesia.
- Kode mengikuti PSR-12 dan `.editorconfig`.
- Kredensial disimpan di `.env` yang tidak di-commit.
- Versi awal (v0.x) sengaja memakai raw SQL `mysqli`, kemudian dimigrasi ke Query Builder di v1.0 (bukti Unit 10).

### 2.6 Asumsi dan ketergantungan

- XAMPP dan Composer sudah terpasang.
- Pemantauan resource memakai fungsi PHP dan perintah OS lokal (`wmic`/`typeperf` di Windows, `/proc` di Linux).
- Library pihak ketiga diambil via Composer atau CDN versi tetap.
- Semua waktu disimpan dalam UTC dan ditampilkan dalam zona `Asia/Jakarta`. Jam SLA berarti jam kalender, termasuk malam, akhir pekan, dan hari libur.

---

## 3. Analisis dan pemilihan teknologi (Unit 1 dan Unit 3)

### 3.1 Tabel kebutuhan teknis

| ID | Kebutuhan | Prioritas | Sumber |
|---|---|---|---|
| KT-01 | Berjalan lokal tanpa internet saat demo | Wajib | Kondisi asesmen |
| KT-02 | Dapat dipelajari dan dibangun cepat | Wajib | Waktu terbatas |
| KT-03 | Mendukung MVC, migrasi DB, seeder, CLI | Wajib | Unit 5, 7, 17 |
| KT-04 | Mendukung pengujian unit | Wajib | Unit 14 |
| KT-05 | Grafik interaktif dan animasi ringan | Wajib | Unit 12, 16 |
| KT-06 | Antarmuka responsif tanpa build tool | Disarankan | Unit 4 |
| KT-07 | Ekspor Excel dan PDF | Disarankan | Fitur laporan |
| KT-08 | Sudah dikuasai pengembang | Disarankan | Pengalaman PKL E-Kinerja |

### 3.2 Perbandingan alternatif dan keputusan

| Area | Alternatif | Kelebihan | Kekurangan | Keputusan dan alasan |
|---|---|---|---|---|
| Lingkungan server | **XAMPP** / Docker / Laragon | XAMPP: sekali pasang, umum di asesmen, tanpa virtualisasi | Docker: perlu WSL2, berat di laptop; Laragon: kurang umum | **XAMPP** karena memenuhi KT-01, KT-02, KT-08 |
| Bahasa backend | **PHP 8.2** / Node.js / Python | PHP bawaan XAMPP, sudah dikuasai | Node/Python perlu runtime tambahan | **PHP 8.2** |
| Framework | **CodeIgniter 4** / Laravel 11 / PHP native | CI4 ringan, migrasi dan spark CLI, sudah dipakai di E-Kinerja | Laravel lebih berat dan butuh waktu belajar; native tanpa struktur | **CI4 4.7.x** memenuhi KT-02, KT-03, KT-04, KT-08; pin 4.5.x ditolak Composer karena advisori keamanan |
| Basis data | **MariaDB (XAMPP)** / PostgreSQL / SQLite | Bawaan XAMPP, dukung view, procedure, trigger, GRANT | PostgreSQL perlu instalasi; SQLite tanpa procedure dan GRANT | **MariaDB** (memenuhi Unit 7 dan 8) |
| CSS | **Bootstrap 5** / Tailwind / CSS sendiri | Komponen siap pakai, tanpa build | Tailwind perlu build; CSS sendiri lama | **Bootstrap 5** (KT-06) |
| Grafik | **Chart.js** / ApexCharts / D3.js | Ringan, dokumentasi baik, animasi bawaan | ApexCharts lebih berat; D3 rumit | **Chart.js** (KT-05) |
| Ekspor Excel | **PhpSpreadsheet** / buat CSV sendiri | Format .xlsx lengkap | CSV tidak berformat | **PhpSpreadsheet** |
| Ekspor PDF | **Dompdf** / html2pdf.js / TCPDF | Server-side, dari HTML view | html2pdf.js hasil bergantung browser | **Dompdf** |
| Pengujian | **PHPUnit + PHPStan** / Pest | Bawaan CI4, standar industri | Pest perlu adaptasi | **PHPUnit + PHPStan level 5** |
| Stress test | **ApacheBench (`ab`)** / JMeter / k6 | Sudah ada di XAMPP (`apache/bin/ab.exe`) | JMeter berat; k6 perlu instalasi | **ab** |
| Version control | **Git + GitHub** | Riwayat commit sebagai bukti | - | **Git** dengan tag versi |

### 3.3 Pakai library vs buat sendiri (Unit 3)

| Komponen | Pakai library | Buat sendiri | Keputusan |
|---|---|---|---|
| Grafik | Chart.js | Canvas manual | Library, karena waktu dan kualitas |
| Slideshow statistik | Library carousel | Modul `renderFrame()` sendiri | **Buat sendiri** (bukti Unit 12) |
| Sorting/searching tiket | `ORDER BY`, `usort` | Algoritma sendiri | Keduanya: produksi pakai SQL, modul `algoritma/` buat sendiri (Unit 9) |
| Ekspor Excel | PhpSpreadsheet | Tulis XML sendiri | Library |
| Notifikasi | Library toast | Komponen sendiri | Buat sendiri (sederhana) |

PoC dibuat di folder `poc/` untuk membandingkan Chart.js dan ApexCharts serta PhpSpreadsheet dan CSV manual. Setiap PoC mencatat fitur sederhana yang dijalankan, langkah demonstrasi, manfaat terukur/teramati, cara integrasi ke CI4, dependensi, dan batasan penggunaan. Catatan uji tools A01 memuat versi, perintah/langkah, hasil, kendala, dan keputusan.

### 3.4 Risiko teknologi

| Risiko | Dampak | Kemungkinan | Mitigasi |
|---|---|---|---|
| Port 80/3306 bentrok | Aplikasi tidak jalan | Sedang | Ubah port di `httpd.conf`/`my.ini`, catat di README |
| Versi PHP XAMPP tidak cocok CI4 | Error instalasi | Rendah | Kunci PHP 8.2, cek `composer check-platform-reqs` |
| CDN tidak terjangkau saat demo | Tampilan rusak | Sedang | Simpan salinan lokal di `public/vendor` |
| Data uji 100 ribu memperlambat demo | Demo lambat | Sedang | Database uji terpisah (`tiket_loadtest`) |
| Fungsi resource OS berbeda | Monitor tidak akurat | Sedang | Adapter per OS (Windows/Linux) dengan fallback |

---

## 4. Kebutuhan fungsional

Format ID: `F-<modul>-<nomor>`. Prioritas: W = wajib, S = disarankan, O = opsional.

### 4.1 Modul Autentikasi (AUTH)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| F-AUTH-01 | Pengguna login dengan email dan password (hash `password_hash` bcrypt) | W |
| F-AUTH-02 | Setelah password benar, sistem membuat OTP 6 digit dengan `random_int`, berlaku 5 menit, disimpan dalam bentuk hash | W |
| F-AUTH-03 | OTP hanya ditampilkan pada halaman verifikasi milik sesi yang baru lolos password jika `APP_ENV=local` dan `DEMO_OTP=true`; OTP mentah tidak dicatat di log umum | W |
| F-AUTH-04 | OTP salah 3 kali mengunci akun 10 menit | S |
| F-AUTH-05 | Logout menghapus sesi | W |
| F-AUTH-06 | Filter `AuthFilter` dan `RoleFilter` membatasi rute per peran | W |
| F-AUTH-07 | OTP hanya dapat dipakai sekali; penerbitan kode baru membatalkan kode sebelumnya; log hanya mencatat pengguna, waktu, dan hasil verifikasi tanpa nilai OTP | W |

### 4.2 Modul Tiket (TKT)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| F-TKT-01 | Pemohon membuat tiket: judul, deskripsi, kategori, prioritas (Rendah/Sedang/Tinggi/Kritis) | W |
| F-TKT-02 | Sistem membuat nomor tiket unik format `TKT-YYYYMMDD-XXXX` | W |
| F-TKT-03 | Sistem menghitung `sla_due_at` dari `created_at` ditambah durasi prioritas (Kritis 4, Tinggi 8, Sedang 24, Rendah 72 jam kalender); tenggat tidak berubah karena pergantian status atau pembukaan ulang | W |
| F-TKT-04 | Daftar tiket dengan pagination, filter status/prioritas/kategori, dan pencarian judul | W |
| F-TKT-05 | Petugas mengambil tiket `Baru` tanpa penugasan; pengisian `assigned_to` dan perubahan ke `Diproses` berlangsung atomik sehingga hanya satu petugas berhasil | W |
| F-TKT-06 | Transisi status mengikuti tabel pada §4.2.1; tiket `Ditutup` tidak dapat dibuka ulang | W |
| F-TKT-07 | Perubahan status yang tidak sah ditolak (validasi transisi di `TicketService`) | W |
| F-TKT-08 | Setiap perubahan status dicatat ke `ticket_logs` oleh trigger DB dengan pelaku dari `tickets.status_changed_by` | W |
| F-TKT-09 | Pemohon hanya dapat mengubah judul, deskripsi, atau kategori tiket miliknya saat status Baru; aksi status mengikuti §4.2.1 | W |
| F-TKT-10 | Admin dapat menghapus tiket (soft delete) | S |
| F-TKT-11 | Perubahan prioritas hanya oleh Admin dengan alasan; `sla_due_at` dihitung ulang dari `created_at` memakai durasi prioritas baru dan perubahan dicatat | W |
| F-TKT-12 | Admin dapat memindahkan penugasan tiket dengan alasan yang dicatat di log aktivitas | S |

#### 4.2.1 Aturan transisi dan penugasan

| Dari | Ke | Pelaku | Syarat |
|---|---|---|---|
| Baru | Diproses | Petugas | Mengambil tiket yang belum ditugaskan; penugasan dan transisi satu transaksi |
| Diproses | Selesai | Petugas yang ditugaskan | Mengisi catatan penyelesaian |
| Selesai | Diproses | Pemohon pemilik | Maksimal 7 × 24 jam setelah `resolved_at`; mengisi alasan pembukaan ulang |
| Selesai | Ditutup | Pemohon pemilik atau Admin | Tiket tidak dapat dibuka ulang setelah ditutup |

Permintaan pengambilan bersamaan memakai pembaruan bersyarat `status='Baru' AND assigned_to IS NULL` dalam transaksi. Tepat satu permintaan boleh mengubah baris; permintaan lain menerima pesan bahwa tiket telah diambil. Admin dapat mengoreksi transisi pada tiket yang belum Ditutup dengan alasan yang dicatat; tiket Ditutup tetap final. Semua transisi lain ditolak.

#### 4.2.2 Matriks akses tiket

| Aksi | Pemohon | Petugas | Admin |
|---|---|---|---|
| Lihat tiket | Milik sendiri | Semua tiket aktif | Semua tiket, termasuk soft delete |
| Ubah judul/deskripsi/kategori | Milik sendiri, status Baru | Tidak | Semua tiket aktif |
| Ambil tiket | Tidak | Tiket Baru tanpa penugasan | Dapat menugaskan petugas |
| Ubah status | Buka ulang/tutup milik sendiri sesuai §4.2.1 | Tiket yang ditugaskan kepadanya | Koreksi dengan alasan |
| Komentar/unduh lampiran | Milik sendiri | Tiket yang ditugaskan kepadanya | Semua tiket |

Izin diperiksa pada server untuk setiap aksi, endpoint JSON, dan unduhan; menyembunyikan tombol di UI bukan pengganti pemeriksaan izin. Semua daftar, grafik, ekspor, dan view biasa mengecualikan tiket soft delete kecuali tampilan khusus Admin.

### 4.3 Modul Komentar dan Lampiran (KOM)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| F-KOM-01 | Pemohon dan Petugas menambah komentar pada tiket | W |
| F-KOM-02 | Unggah lampiran (jpg, png, pdf; maks 2 MB; maks 3 per tiket) ke `writable/uploads` | W |
| F-KOM-03 | Nama berkas diacak, tipe MIME divalidasi | W |
| F-KOM-04 | Unduh lampiran hanya oleh pihak yang berhak | W |

### 4.4 Modul Master Data (MST)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| F-MST-01 | Admin CRUD kategori | W |
| F-MST-02 | Admin CRUD pengguna dan peran | W |
| F-MST-03 | Admin mengatur durasi SLA dan ambang alert di `settings`; perhitungan aplikasi dan fungsi/trigger DB memakai nilai yang sama | W |

### 4.5 Modul Dashboard dan Multimedia (DSB)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| F-DSB-01 | Kartu ringkasan: total tiket, tiket terbuka, lewat SLA, rata-rata waktu selesai | W |
| F-DSB-02 | Grafik Chart.js: tiket per status (donut), tiket per hari 30 hari (line), per kategori (bar) | W |
| F-DSB-03 | Data grafik diambil dari view agregat yang terpisah menurut status, kategori, dan hari; endpoint JSON mengembalikan `cards`, `by_status`, `by_category`, `by_day` tanpa menggandakan tiket | W |
| F-DSB-04 | **Slideshow statistik** buatan sendiri: modul JS `renderFrame()` menampilkan 4-5 slide (kartu, grafik, petugas teratas) dengan transisi fade/slide, timeline durasi tiap frame, tombol play/pause/next/prev | W |
| F-DSB-05 | Mode layar penuh slideshow untuk ditampilkan di monitor ruang layanan | S |
| F-DSB-06 | Peringkat petugas berdasarkan tiket selesai (memakai procedure/function DB) | S |

### 4.6 Modul Notifikasi dan Alert (NTF)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| F-NTF-01 | Notifikasi dalam web: ikon lonceng dengan badge jumlah belum dibaca | W |
| F-NTF-02 | Notifikasi dibuat saat tiket baru, diambil, status berubah, dan ada komentar | W |
| F-NTF-03 | Notifikasi dikirim melalui `NotifierInterface`; implementasi `DatabaseNotifier` | W |
| F-NTF-04 | Polling AJAX setiap 30 detik untuk notifikasi baru | W |
| F-NTF-05 | **Alert kritis** dengan aturan: (a) tiket Kritis belum diambil > 30 menit, (b) tiket lewat SLA, (c) error aplikasi tingkat `critical`/`error`, (d) resource melewati batas atas (CPU > 85%, RAM > 85%, disk > 90%, koneksi DB > 80% max) | W |
| F-NTF-06 | Alert ditampilkan sebagai banner merah di dashboard Petugas/Admin dan disimpan di tabel `alerts` | W |
| F-NTF-07 | Satu `rule_code + object_key` hanya memiliki satu alert aktif; tiap pemeriksaan saat masalah berlangsung menaikkan `occurrences` dan memperbarui `last_seen_at`; pengingat dikirim paling sering sekali per 15 menit | W |
| F-NTF-08 | Setelah dua pemeriksaan berturut-turut menunjukkan kondisi normal, alert ditandai pulih dan satu notifikasi pemulihan dikirim; masalah yang muncul lagi membuat alert baru | W |
| F-NTF-09 | `php spark alert:check` dijadwalkan setiap menit via Windows Task Scheduler; dashboard hanya membaca hasil pemeriksaan terakhir | W |
| F-NTF-10 | Admin dapat mengakui (acknowledge) alert | S |
| F-NTF-11 | Jika pembaca metrik OS gagal, monitor menampilkan `Tidak tersedia`, fitur tiket tetap berfungsi, kegagalan dicatat sebagai alert; setelah pembacaan berhasil lagi, tampilan dan alert pulih otomatis | W |
| F-NTF-12 | Jika koneksi DB gagal, aplikasi menampilkan halaman gangguan dengan fungsi terbatas, menulis error ke log lokal, dan kembali melayani permintaan baru setelah DB tersedia | W |

Penerima alert: tiket Kritis belum diambil → semua Petugas dan Admin; tiket lewat SLA → Petugas yang ditugaskan (jika ada) dan Admin; error aplikasi atau resource → Admin. Alert aktif pertama segera menghasilkan notifikasi. Pengingat untuk kondisi yang belum ditangani dibatasi 15 menit per alert. `acknowledge` tidak menyelesaikan kondisi; hanya hasil pemeriksaan normal yang melakukan pemulihan. Untuk metrik yang tidak tersedia, aturan ambang metrik tersebut tidak dievaluasi dari nilai simulasi.

### 4.7 Modul Pemantauan Resource (MON)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| F-MON-01 | Endpoint `GET /monitor/metrics` mengembalikan JSON: CPU %, RAM terpakai/total, disk terpakai/total, jumlah koneksi DB (`SHOW STATUS LIKE 'Threads_connected'`), `max_connections`, waktu respons query uji, memori PHP | W |
| F-MON-02 | Halaman `/monitor` menampilkan grafik garis real-time (refresh 5 detik, simpan 60 titik) dengan **garis batas atas** tiap metrik serta label `Tidak tersedia` bila pembacaan gagal | W |
| F-MON-03 | Metrik disimpan ke tabel `resource_metrics` tiap menit oleh `php spark monitor:collect` | W |
| F-MON-04 | Riwayat 24 jam dapat ditampilkan | S |
| F-MON-05 | Melewati batas atas memicu alert (F-NTF-05 d) | W |
| F-MON-06 | Adapter `ResourceReaderInterface` dengan `WindowsResourceReader` dan `LinuxResourceReader` | S |

Sumber dan satuan: CPU dan RAM dari pembaca OS (%), disk dari partisi tempat aplikasi berada (%), koneksi DB dari `Threads_connected / max_connections` (%), waktu query uji (ms), dan memori proses PHP (MB). Ambang sebelum kondisi kritis: CPU > 85%, RAM > 85%, disk > 90%, koneksi DB > 80% dari maksimum. Sampel OS/DB dikumpulkan tiap menit untuk riwayat; grafik aktif boleh membaca tiap 5 detik. Nilai simulasi hanya boleh dipakai pada mode demo dan harus diberi label `Simulasi`, tidak dipakai untuk alert produksi.

### 4.8 Modul Laporan (LAP)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| F-LAP-01 | Ekspor daftar tiket terfilter ke Excel (PhpSpreadsheet) | W |
| F-LAP-02 | Ekspor ringkasan bulanan ke PDF (Dompdf) | S |

### 4.9 Modul Log dan Debugging (LOG)

| ID | Kebutuhan | Prioritas |
|---|---|---|
| F-LOG-01 | Log aktivitas pengguna (login, CRUD, ubah status) ke tabel `activity_logs` | W |
| F-LOG-02 | Error aplikasi dicatat dengan `log_message()` dan kode kesalahan internal (mis. `E-TKT-001`) | W |
| F-LOG-03 | Admin melihat daftar log aktivitas dengan filter | S |
| F-LOG-04 | Halaman error ramah pengguna yang menampilkan kode kesalahan | W |

### 4.10 Kasus penggunaan utama (ringkas)

**UC-01 Membuat tiket**: Pemohon login → klik "Tiket Baru" → isi form → kirim → sistem simpan, hitung SLA, buat notifikasi ke Petugas → tampil halaman detail. Target: maksimal 4 klik dari dashboard (Unit 4).

**UC-02 Memproses tiket**: Petugas melihat antrean → ambil tiket (status otomatis Diproses) → komentar → isi catatan penyelesaian dan ubah Selesai → Pemohon menerima notifikasi.

**UC-03 Alert SLA**: `alert:check` berjalan tiap menit → menemukan tiket lewat SLA → buat/perbarui satu alert aktif → banner muncul di dashboard → tiket diselesaikan → dua pemeriksaan normal → alert otomatis pulih dan satu notifikasi pemulihan dibuat.

**UC-04 Memantau resource**: Admin membuka `/monitor` → grafik CPU/RAM/disk/DB diperbarui tiap 5 detik → nilai melewati garis batas → pemeriksaan terjadwal membuat alert paling lambat pada siklus berikutnya.

---

## 5. Kebutuhan non-fungsional

| ID | Kategori | Kebutuhan | Cara verifikasi |
|---|---|---|---|
| NF-01 | Performa | Halaman daftar tiket p95 < 500 ms pada data demo 100 tiket, 30 permintaan setelah pemanasan, perangkat demo dicatat di A02 | Debug Toolbar dan lembar uji |
| NF-02 | Performa | Query pencarian judul halaman pertama (20 baris) p95 < 100 ms pada 100 ribu tiket setelah index, 30 pengulangan setelah pemanasan; istilah pencarian dicatat | `EXPLAIN`, pengukuran waktu (A02, A08) |
| NF-03 | Skalabilitas | Uji 50 sesi pengguna sah secara bersamaan pada lingkungan lokal; catat keberhasilan, error, p95 waktu respons, CPU/RAM dan batas kemampuan perangkat, tanpa mengasumsikan `ab -c 50` setara 50 pengguna | Skenario terautentikasi dan `ab -n 1000 -c 50` sebagai uji HTTP dasar (A14) |
| NF-04 | Keamanan | Password bcrypt, OTP `random_int` dan kedaluwarsa, CSRF aktif, escaping output, validasi input | Code review (A13), PHPStan |
| NF-05 | Keamanan | Akun DB aplikasi `tiket_app` hanya hak `SELECT, INSERT, UPDATE, DELETE, EXECUTE` | Bukti `SHOW GRANTS` (A08) |
| NF-06 | Keamanan | `.env`, `writable/`, dan unggahan tidak di-commit | `.gitignore` |
| NF-07 | Usability | Responsif 360 px sampai 1920 px; tugas utama ≤ 4 klik; mode gelap/terang | Uji skenario (A04) |
| NF-08 | Kompatibilitas | Berjalan di Chrome, Firefox, Edge terbaru | Tabel uji kompatibilitas (A12) |
| NF-09 | Keandalan | Perubahan status, log trigger, dan pembuatan notifikasi berada dalam satu transaksi; bila salah satu gagal, seluruh perubahan dibatalkan | Uji kegagalan terencana, skrip SQL (A07) |
| NF-10 | Maintainability | PSR-12, PHPStan level 5 tanpa error, controller tipis dan logika di Service | Laporan PHPStan (A14), review (A13) |
| NF-11 | Portabilitas | Instalasi baru ≤ 10 langkah di README | README |
| NF-12 | Observability | Semua error tercatat dengan kode, resource dipantau tiap menit | A11, A16 |

---

## 6. Kebutuhan data dan basis data

### 6.1 Daftar tabel

Semua tabel memakai InnoDB, `utf8mb4`, primary key `id` BIGINT UNSIGNED AUTO_INCREMENT, dan kolom `created_at`, `updated_at`.

| Tabel | Kolom penting | Relasi/index |
|---|---|---|
| `users` | name, email (unique), password_hash, role ENUM('pemohon','petugas','admin'), is_active, locked_until | idx_role |
| `otp_codes` | user_id, code_hash, expires_at, attempts, used_at | FK users |
| `categories` | name (unique), description | - |
| `tickets` | ticket_no (unique), user_id, category_id, assigned_to, status_changed_by, title, description, priority ENUM, status ENUM, sla_due_at, resolved_at, deleted_at | FK users (termasuk `status_changed_by`), categories; index ditambah di v1.1: `idx_status_priority`, `idx_sla_due`, `idx_active_list (deleted_at, status, priority)`, FULLTEXT `ft_title` |
| `ticket_sequences` | ticket_date (PK), last_no | Sequence atomik per hari untuk `TKT-YYYYMMDD-XXXX`; maksimal 9.999 tiket/hari |
| `ticket_logs` | ticket_id, old_status, new_status, changed_by, changed_at | FK tickets; diisi trigger |
| `comments` | ticket_id, user_id, body | FK tickets, users |
| `attachments` | ticket_id, original_name, stored_name, mime, size | FK tickets |
| `notifications` | user_id, type, message, link, read_at | FK users; idx_user_read |
| `alerts` | rule_code, object_key, active_key, level, message, occurrences, first_seen_at, last_seen_at, last_notified_at, normal_checks, resolved_at, acknowledged_by, acknowledged_at (v1.1) | unique `active_key`; berisi `rule_code + object_key` saat aktif, `NULL` saat pulih |
| `resource_metrics` | cpu_pct, ram_used_mb, ram_total_mb, disk_used_gb, disk_total_gb, db_connections, db_max_connections, query_ms | idx_created_at |
| `activity_logs` | user_id, action, entity, entity_id, ip, user_agent | idx_user, idx_created_at |
| `settings` | key (unique), value | - |

### 6.2 Objek SQL (Unit 7)

| Objek | Nama | Fungsi |
|---|---|---|
| View | `v_ticket_summary` | Satu baris per status: `status`, `total`; hanya tiket aktif (bukan soft delete) |
| View | `v_ticket_sla_summary` | Satu baris per status: `status`, `overdue_total`; hanya tiket aktif |
| View | `v_ticket_by_category`, `v_ticket_by_day` | Agregat terpisah per kategori dan per tanggal pembuatan untuk grafik |
| View | `v_ticket_sla` | Tiket dengan selisih waktu ke SLA dan flag terlambat |
| View gabungan | `v_dashboard` | JOIN `v_ticket_summary` dan `v_ticket_sla_summary` pada `status`; tepat satu baris per status, dengan `total` dan `overdue_total` |
| Function | `fn_sla_hours(priority)` | Membaca durasi SLA sesuai prioritas dari `settings`; nilai awal 4/8/24/72 jam |
| Function | `fn_resolution_minutes(ticket_id)` | Lama penyelesaian tiket dalam menit |
| Procedure | `sp_close_ticket(ticket_id, user_id)` | Menutup tiket dalam transaksi (`START TRANSACTION`, `COMMIT`, `ROLLBACK` saat error dengan `DECLARE EXIT HANDLER`) |
| Procedure | `sp_officer_ranking(start, end)` | Peringkat petugas |
| Trigger | `trg_ticket_status_log` | AFTER UPDATE pada `tickets`, menulis ke `ticket_logs` jika status berubah; `changed_by = NEW.status_changed_by`; menolak perubahan status tanpa pelaku |
| Trigger | `trg_ticket_before_insert` | Mengisi `sla_due_at = created_at + fn_sla_hours(priority)`; perubahan prioritas oleh Admin dihitung ulang di Service dalam transaksi |
| Index | `idx_status_priority`, `idx_sla_due`, `ft_title`, `idx_active_list` | Dibuat di migrasi, diukur dengan `EXPLAIN` dan benchmark 100.000 tiket (A02, A10) |
| Hak akses | `CREATE USER 'tiket_app'@'localhost'` + `GRANT` terbatas; `REVOKE` contoh | Bukti Unit 7 dan 8 |

Semua objek SQL disimpan juga sebagai berkas di `database/sql/` (mis. `01_views.sql`, `02_functions.sql`, `03_procedures.sql`, `04_triggers.sql`, `05_indexes.sql`, `06_grants.sql`, `07_transaction_demo.sql`) agar mudah dicetak dan dieksekusi.

Skrip A07 juga menunjukkan `INSERT`, `UPDATE`, `DELETE`, JOIN antar tabel, JOIN antar dua view, hasil `EXPLAIN`, input/output procedure dan function, hasil trigger, serta perubahan data sebelum/sesudah `COMMIT` dan `ROLLBACK`. Pengubahan SLA oleh Admin memperbarui `settings`; pengujian membuktikan hasil fungsi dan perhitungan tiket berikutnya sesuai nilai baru. Setiap perubahan status wajib mengisi `status_changed_by` dalam UPDATE yang sama; uji DB menolak atau mendeteksi perubahan status tanpa pelaku.

### 6.3 ERD (ringkas)

```
users 1───* tickets *───1 categories
  │            │
  │            ├──* ticket_logs
  │            ├──* comments *───1 users
  │            └──* attachments
  ├──* notifications
  ├──* otp_codes
  └──* activity_logs
alerts, resource_metrics, settings (mandiri)
```

---

## 7. Kebutuhan antarmuka

### 7.1 Antarmuka pengguna (halaman)

| Halaman | Peran | Isi utama |
|---|---|---|
| Login, OTP | Semua | Form, pesan error, info OTP simulasi |
| Dashboard | Semua (isi beda per peran) | Kartu ringkasan, grafik, banner alert, tombol slideshow |
| Daftar tiket | Semua | Tabel, filter, pencarian, pagination, tombol ekspor (Admin) |
| Form tiket | Pemohon | Judul, deskripsi, kategori, prioritas, lampiran |
| Detail tiket | Semua | Info, timeline status, komentar, lampiran, tombol aksi sesuai peran |
| Notifikasi | Semua | Daftar notifikasi, tandai dibaca |
| Alert | Petugas, Admin | Daftar alert aktif/pulih, jumlah kejadian, acknowledge |
| Monitor | Admin | Grafik real-time CPU/RAM/disk/DB dengan garis batas atas |
| Slideshow | Admin | Layar penuh statistik animasi |
| Master kategori, pengguna | Admin | CRUD |
| Log aktivitas | Admin | Tabel dengan filter |
| Pengaturan | Admin | SLA, ambang alert |

Wireframe tiap halaman dibuat (bisa sketsa atau Figma/draw.io) sebagai lampiran A04.

A04 mencatat skenario dari dashboard setelah login: buat tiket (maksimal 4 aksi), ambil tiket (3), beri komentar (3), selesaikan/tutup tiket (3), dan ekspor laporan (4). Untuk tiap skenario dicatat titik awal, urutan aksi, jumlah maksimum, wireframe yang mendukung, dan hasil uji nyata. Skenario tambahan harus memiliki jumlah aksi di bawah batas atas 4 bila berlaku untuk tugas utama.

### 7.2 Antarmuka perangkat lunak (endpoint internal)

| Metode | Rute | Keterangan |
|---|---|---|
| GET | `/api/dashboard/summary` | JSON dari `v_dashboard` dan view agregat grafik lain |
| GET | `/api/notifications/unread` | JSON notifikasi belum dibaca |
| POST | `/api/notifications/{id}/read` | Tandai dibaca |
| GET | `/api/alerts/active` | JSON alert aktif |
| GET | `/monitor/metrics` | JSON metrik resource (snapshot dipakai ulang 3 detik agar pembacaan OS tidak dipanggil tiap permintaan) |
| POST | `/alerts/{id}/ack` | Admin mengakui alert aktif (F-NTF-10); mengisi `acknowledged_by` dan `acknowledged_at`, tidak memulihkan alert |
| POST | `/tickets/{id}/assign` | Admin menetapkan/memindahkan petugas dengan alasan (F-TKT-12) |

`/api/dashboard/summary` mengembalikan objek `cards`, `by_status`, `by_category`, dan `by_day`. Setiap item agregat menyebut kunci kelompok dan `total`; tiket soft delete dikecualikan. Endpoint JSON tanpa sesi atau tanpa izin mengembalikan HTTP 403 (bukan pengalihan HTML), objek yang tidak ada/tersembunyi HTTP 404, dan metrik yang gagal dibaca memakai `null` dengan status `unavailable`, bukan angka nol.

### 7.3 Antarmuka CLI (spark)

| Perintah | Fungsi |
|---|---|
| `php spark migrate` / `migrate:rollback` | Migrasi dan rollback skema |
| `php spark db:seed DemoSeeder` | Data demo |
| `php spark db:seed LoadTestSeeder` | Data uji 100 ribu |
| `php spark alert:check` | Evaluasi aturan alert |
| `php spark monitor:collect` | Simpan metrik resource |
| `php spark backup:db` | Backup DB via `mysqldump` sebelum pembaruan |

---

## 8. Skenario data

| | Skenario Demo | Skenario Uji Unit |
|---|---|---|
| Tujuan | Presentasi ke asesor | Bukti Unit 2, 8, 14 (index, EXPLAIN, estimasi, stress test) |
| Database | `tiket_demo` | `tiket_loadtest` (terpisah agar demo bersih) |
| Jumlah data | 100 tiket, 10 pengguna (1 admin, 3 petugas, 6 pemohon), 6 kategori, sekitar 200 komentar | 100.000 tiket (dapat diatur `--count`), 200 pengguna |
| Cara membuat | `php spark db:seed DemoSeeder` | `php spark db:seed LoadTestSeeder` (insert batch 1.000 baris) |
| Karakteristik | Data realistis berbahasa Indonesia, campuran semua status, beberapa tiket lewat SLA agar alert muncul | Data acak (Faker), distribusi prioritas dan status merata |
| Dipakai untuk | Semua tampilan, dashboard, slideshow, alert, monitor | `EXPLAIN` sebelum/sesudah index, ukur waktu query, `ab` |

### 8.1 Prosedur uji index (Unit 2 dan 8)

1. Jalankan `LoadTestSeeder` di `tiket_loadtest` dengan skema v1.0 (tanpa index tambahan).
2. Jalankan query uji: pencarian judul, filter status+prioritas, tiket lewat SLA. Catat `EXPLAIN` (`type`, `rows`) dan waktu (rata-rata 5 kali).
3. Jalankan migrasi v1.1 yang menambah index.
4. Ulangi langkah 2. Bandingkan dalam tabel dan tangkapan layar.
5. Ekstrapolasi untuk 1 juta baris sebagai estimasi skalabilitas (A02).

A02 mencatat lingkup sistem, jumlah pengguna/data awal dan proyeksi 10×/100×, spesifikasi CPU/RAM/disk perangkat uji, titik sempit query dan polling, kompleksitas pemrosesan yang relevan, kebutuhan hardware yang diperkirakan, serta asumsi dan batas estimasi. Hasil uji mencatat median/p95, persentase berhasil, error, pemakaian CPU/RAM, dan konfigurasi server agar dapat direproduksi.

---

## 9. Rencana rilis, migrasi, dan pembaruan (Unit 10 dan 17)

Rencana awal (v0.1 s.d. v1.1 dibangun bertahap sejak awal) tidak sesuai dengan urutan kerja nyata: aplikasi inti dibangun langsung dengan Query Builder dan baru dikirim sebagai satu commit. Agar riwayat tidak dipalsukan, rencana rilis direvisi menjadi urutan yang benar-benar terjadi dan dapat diperiksa lewat `git tag`:

| Tag | Isi | Bukti |
|---|---|---|
| `v0.8` | Implementasi awal seluruh aplikasi (commit `5874d8d`), sudah memakai Query Builder | `git show v0.8` |
| `v0.9-audit` | Perbaikan hasil audit fitur wajib: akses, lampiran, monitor; uji HTTP 106 pemeriksaan dan uji beban | `git diff v0.8 v0.9-audit`, A14 |
| **`v0.9`** | **Baseline raw SQL `mysqli`** untuk modul daftar/pencarian/ekspor tiket (`App\Models\TicketListQuery`), diukur dan diuji | `git show v0.9`, `docs/A10-benchmark-raw-sql-v0.9.json` |
| **`v1.0`** | **Migrasi raw SQL ke Query Builder** pada modul yang sama dengan uji kesetaraan fungsi | `git diff v0.9 v1.0`, A10 |
| **`v1.1`** | Patch: migrasi `ListIndexesAndAckTime` (index daftar tiket + kolom `acknowledged_at`), perbaikan temuan code review, halaman galat berkode | `git diff v1.0 v1.1`, A13, A17 |
| `v1.1.1` | Perbaikan setelah uji browser: pembaca CPU/RAM Windows (`-EncodedCommand`), label waktu grafik monitor, komentar `slideshow.js`, dokumen A01–A17 dan bukti uji | `git diff v1.1 v1.1.1`, A11 #15–16 |

Catatan kejujuran: baseline raw SQL (`v0.9`) dibuat pada 16–17 November 2026 sebagai modul pembanding untuk bukti Unit 10, bukan sebagai sisa pengembangan yang lebih lama. Seluruh tag dibuat pada rentang waktu yang sama dengan commit-nya; tidak ada tanggal commit yang dimanipulasi. Fitur aplikasi lain (komentar, lampiran, notifikasi, dashboard, alert, monitor, slideshow, ekspor) sudah ada pada `v0.8`.

### 9.1 Migrasi teknologi (Unit 10)

- **Dari**: query raw SQL `mysqli` dengan prepared statement yang ditulis langsung di `TicketListQuery` (perakitan WHERE dan `bind_param` manual; tag `v0.9`).
- **Ke**: CI4 Query Builder dengan binding otomatis (tag `v1.0`). Lingkup migrasi: daftar/pencarian/pagination tiket dan ekspor Excel.
- **Tambahan opsional**: penyimpanan sesi dari file (`FileHandler`) ke database (`DatabaseHandler`).
- Dokumen A10 memuat: alasan, inventaris query yang dimigrasi, perbandingan kode sebelum/sesudah, hasil uji fungsi yang sama, risiko, dan rencana rollback.
- A10 mencatat versi teknologi lama dan baru, keunggulan berdasarkan kebutuhan aplikasi, keputusan migrasi, serta fitur khusus Query Builder yang digunakan (binding parameter, builder kondisi, dan transaksi). Perbandingan memakai skenario dan hasil yang sama sebelum/sesudah migrasi; tag Git membuktikan kedua keadaan benar-benar pernah diimplementasikan.

### 9.2 Prosedur pembaruan (Unit 17)

1. Backup DB: `php spark backup:db` (memanggil `mysqldump`).
2. `git pull` / checkout tag baru.
3. `composer install`.
4. `php spark migrate`.
5. Uji asap (login, buat tiket, dashboard).
6. Jika gagal: `php spark migrate:rollback`, checkout tag sebelumnya, pulihkan backup.

Setiap migrasi wajib memiliki method `down()`.

A17 menyimpan daftar perbedaan setiap tag, langkah penerapan patch, perubahan skema/data, hasil uji asap sebelum/sesudah, lokasi backup, dan hasil demonstrasi rollback. Patch v1.1 diterapkan pada v1.0 yang berjalan; bukan sekadar kode yang ditulis tanpa pengujian pembaruan.

---

## 10. Rencana pengujian (Unit 8, 11, 14)

| Jenis | Alat | Cakupan minimal | Bukti |
|---|---|---|---|
| Analisis statis | PHPStan level 5 | `app/` | Laporan sebelum dan sesudah perbaikan |
| Gaya kode | PHP_CodeSniffer PSR-12 | `app/` | Laporan |
| Uji unit | PHPUnit | `TicketService` (transisi status, hitung SLA), `AlertService` (throttling, pemulihan), `OtpService` (kedaluwarsa, percobaan) | Hasil `vendor/bin/phpunit` |
| Driver dan stub | PHPUnit mock | `NotifierInterface` di-stub, `ResourceReaderInterface` di-stub | Kode uji |
| Uji basis data | Skrip SQL dan PHPUnit DB | Procedure, trigger, transaksi rollback | Lembar uji A08 |
| Stress test | `ab -n 1000 -c 50` | `/login`, `/tickets`, `/monitor/metrics` | Output `ab`, catatan requests/sec |
| Uji fungsional manual | Lembar uji | Semua UC | Tabel lulus/gagal |
| Uji kompatibilitas | Chrome, Firefox, Edge, mobile 360 px | Dashboard, slideshow | Tabel A12 |
| Debugging | Debug Toolbar, `log_message`, Xdebug (opsional) | Minimal 5 bug nyata | Tabel bug A11 |

Kriteria lulus pemeriksaan sintaks/build: `php -l` pada berkas PHP terkait, PHPCS dan PHPStan sesuai target tanpa error baru. Kriteria lulus eksekusi: skenario menghasilkan respons dan perubahan data yang diharapkan tanpa error tak tertangani. A11 mencatat langkah reproduksi, kode kesalahan, log/debugger, akar penyebab, perubahan, dan hasil uji ulang setiap bug.

A14 mencatat peran penguji (asesi sebagai pelaksana; asesor sebagai peninjau saat demonstrasi), alat/versi, lingkungan, data awal, hasil yang diharapkan dan aktual, status lulus/gagal, serta analisis selisih. Driver/stub PHPUnit harus dijalankan dan hasilnya dicatat. PHPStan/PHPCS adalah analisis statis; PHPUnit dan stress test adalah pengujian dinamis pelengkap. Uji stres mencatat kondisi awal dan selama pengujian, termasuk CPU, RAM, error, waktu respons p95, dan hasil `ab` sebagai uji HTTP dasar. Uji alur terautentikasi memakai sesi sah dan mencatat keberhasilan 50 pengguna serentak secara terpisah.

---

## 11. Artefak pendukung di luar aplikasi utama

| Folder | Isi | Unit |
|---|---|---|
| `util/` | 4-5 skrip PHP CLI terstruktur: input keyboard/output layar, diskon dengan percabangan dan perulangan, prosedur dan fungsi bersama dengan komentar, array berukuran/dimensi/tipe yang dicatat serta diurutkan, baca CSV dan tulis ringkasan; bukti `php -l` sebelum/sesudah koreksi sintaks | 5 |
| `algoritma/` | Implementasi sendiri: bubble/insertion sort dan merge sort tiket per prioritas, linear dan binary search nomor tiket; pengukuran waktu (`hrtime`) untuk n = 100, 1.000, 10.000; analisis Big-O waktu dan memori; penjelasan variabel, konstanta, varian/invarian loop; flowchart alur status tiket; pseudocode | 9 |
| `poc/` | PoC Chart.js vs ApexCharts, PhpSpreadsheet vs CSV manual | 3 |
| `oop-demo/` | Demonstrasi class, visibility, inheritance, polymorphism melalui interface, namespace, dan simulasi overloading PHP memakai `__call()` dengan penjelasan keterbatasannya; terpisah dari logika produksi | 6 |
| `database/sql/` | Skrip SQL objek DB dan hasil eksekusi | 7, 8 |
| `docs/` | Dokumen A01-A17, wireframe, storyboard slideshow, laporan review, laporan uji | Semua |

A06 menunjukkan `DatabaseNotifier` dan `DemoNotifier` dapat dipanggil melalui `NotifierInterface`, serta dua `ResourceReaderInterface` melalui kontrak yang sama. `oop-demo/` memperlihatkan pemanggilan metode dengan variasi argumen melalui `__call()` sebagai simulasi, bukan mengklaim PHP mendukung overloading berdasarkan tanda tangan metode secara native. A12 mencatat hardware/software minimum, sistem operasi dan browser uji, storyboard section/frame, durasi, target elemen DOM, beberapa pernyataan dalam satu frame (misalnya ubah teks, grafik, dan indikator progres), komentar kode, serta hasil menjalankan slideshow pada platform yang ditetapkan. A13 membandingkan kode dengan spesifikasi dan PSR-12, mencatat perbaikan tanpa perubahan perilaku; pengecualian guideline hanya ditulis bila ada alasan nyata, disertai komentar pada kode dan penjelasan di laporan.

---

## 12. Matriks keterkaitan 17 unit kompetensi

| Unit | Kode | Fitur/artefak di aplikasi | Dokumen bukti | Kebutuhan terkait |
|---|---|---|---|---|
| 1 Menganalisis tools | J.620100.001.01 | `composer.json`, README instalasi, `.editorconfig`, riwayat Git | A01: tabel kebutuhan, alternatif, alasan, risiko (Bab 3) | KT-01 s.d. KT-08 |
| 2 Skalabilitas | J.620100.002.01 | `LoadTestSeeder`, index v1.1, pagination | A02: lingkup, lingkungan, titik sempit, estimasi 10x/100x, kebutuhan hardware | NF-02, NF-03, Bab 8.1 |
| 3 Library/framework | J.620100.003.01 | CI4, Chart.js, PhpSpreadsheet, Dompdf, folder `poc/` | A03: pakai vs buat sendiri, limitasi, hasil PoC | Bab 3.3 |
| 4 User experience | J.620100.006.01 | Layout responsif, mode gelap, alur tiket ≤ 4 klik, auto-hitung SLA di form | A04: skenario, hitung aksi, batas atas, wireframe | NF-07, UC-01 |
| 5 Pemrograman terstruktur | J.620100.017.02 | Folder `util/` | A05: kode dan tangkapan layar hasil | Bab 11 |
| 6 OOP | J.620100.018.02 | `TicketService`, `AlertService`, `OtpService`, interface dan dua implementasinya, entity `Ticket` dengan getter/setter, inheritance model, namespace, `oop-demo/` | A06: diagram kelas, polymorphism, simulasi overloading PHP, cuplikan, `php -l` | F-NTF-03, F-MON-06, §11 |
| 7 SQL | J.620100.020.02 | Tabel InnoDB dengan PK/FK, view, view gabungan, function, procedure, trigger, index, transaksi, GRANT | A07: skrip `database/sql/` dan hasil eksekusi | Bab 6.2 |
| 8 Akses basis data | J.620100.021.02 | Query Builder, akun `tiket_app`, transaksi di Service | A08: skenario uji, telusur statement, EXPLAIN sebelum/sesudah, `SHOW GRANTS` | NF-05, NF-09, Bab 8.1 |
| 9 Algoritma | J.620100.022.02 | Validasi transisi status (state machine), folder `algoritma/` | A09: flowchart, pseudocode, Big-O, tabel waktu | Bab 11 |
| 10 Migrasi teknologi | J.620100.024.02 | Raw SQL `mysqli` (v0.x) ke Query Builder (v1.0), sesi file ke DB (opsional) | A10: evaluasi, perbandingan, risiko, rollback | Bab 9.1 |
| 11 Debugging | J.620100.025.02 | `log_message`, kode kesalahan `E-xxx-nnn`, Debug Toolbar, commit "fix" | A11: tabel bug, kode kesalahan, tangkapan layar debugger/log | F-LOG-02, F-LOG-04 |
| 12 Multimedia | J.620100.030.02 | Slideshow statistik `renderFrame()` dengan transisi dan timeline, animasi Chart.js | A12: storyboard, timeline frame, uji kompatibilitas browser | F-DSB-04, NF-08 |
| 13 Code review | J.620100.032.001 | Refactor controller ke Service, perbaikan temuan di v1.1 | A13: laporan temuan, diff sebelum/sesudah, komentar PENGECUALIAN bila ada | NF-10 |
| 14 Pengujian statis | J.620100.036.02 | PHPStan, PHPCS, PHPUnit dengan stub/driver, `ab` | A14: laporan linter, lembar uji, hasil stress test | Bab 10 |
| 15 Alert notification | J.620100.044.01 | Notifikasi dalam web, `alerts` dengan aturan, throttling 15 menit, graceful failure, pemulihan otomatis, `alert:check` | A15: analisis kondisi alert, level, penerima, frekuensi, uji kegagalan terbatas dan pemulihan | F-NTF-01 s.d. F-NTF-12 |
| 16 Pemantauan resource | J.620100.045.01 | `/monitor` real-time, `/monitor/metrics`, `resource_metrics`, garis batas atas | A16: metrik yang dipantau, ambang, tangkapan layar | F-MON-01 s.d. F-MON-06 |
| 17 Pembaruan perangkat lunak | J.620100.047.01 | Migrasi bertahap dengan `down()`, tag v0.1/v0.5/v1.0/v1.1, `backup:db` | A17: ringkasan diff antar tag, prosedur backup dan rollback, bukti `migrate`/`migrate:rollback` | Bab 9.2 |

---

## 13. Struktur repositori yang diharapkan

```
tiket-layanan/
├── app/
│   ├── Commands/        AlertCheck.php, MonitorCollect.php, BackupDb.php
│   ├── Config/
│   ├── Controllers/     Auth, Dashboard, Tickets, Comments, Notifications, Alerts, Monitor, Admin/*
│   ├── Database/
│   │   ├── Migrations/  (bertahap, semua punya down())
│   │   └── Seeds/       DemoSeeder.php, LoadTestSeeder.php
│   ├── Entities/        Ticket.php
│   ├── Filters/         AuthFilter.php, RoleFilter.php
│   ├── Libraries/
│   │   ├── Notifier/    NotifierInterface.php, DatabaseNotifier.php
│   │   └── Resource/    ResourceReaderInterface.php, WindowsResourceReader.php, LinuxResourceReader.php
│   ├── Models/
│   ├── Services/        TicketService.php, AlertService.php, OtpService.php
│   └── Views/
├── public/assets/js/    slideshow.js (renderFrame), monitor.js, notifications.js
├── database/sql/        01_views.sql ... 07_transaction_demo.sql
├── util/                skrip CLI Unit 5
├── oop-demo/            demonstrasi konsep OOP Unit 6
├── algoritma/           sorting, searching, benchmark Unit 9
├── poc/                 PoC Unit 3
├── tests/               unit/ dan database/
├── docs/                A01-A17, wireframe, storyboard
├── phpstan.neon
├── phpcs.xml
├── .env.example
└── README.md
```

---

## 14. Lampiran

### 14.1 Asumsi yang perlu dikonfirmasi

- Asesor menerima bukti berupa kode, tangkapan layar, dan dokumen tertulis (bukan hanya demo langsung).
- Pemantauan resource di Windows memakai `wmic`/PowerShell `Get-CimInstance`; bila tidak tersedia, dipakai fallback `sys_getloadavg` atau nilai simulasi yang ditandai jelas.
- Notifikasi dan alert di dalam web dirancang sebagai bukti Unit 15; mekanisme pengiriman, frekuensi, graceful failure, dan pemulihan harus didemonstrasikan agar dapat dinilai.

### 14.2 Kriteria selesai per rilis

- **v1.0 selesai** bila seluruh kebutuhan berprioritas W pada Bab 4 berjalan pada data demo (diperiksa `tests/http/run.php`), modul yang dimigrasi lulus uji kesetaraan fungsi, dan semua migrasi dapat di-rollback.
- **v1.1 selesai** bila index ditambahkan dengan bukti EXPLAIN, PHPStan level 5 tanpa error, uji PHPUnit lulus, dan temuan code review utama diperbaiki.
- **Portofolio selesai** bila setiap baris matriks Bab 12 memiliki artefak dan dokumen bukti.

---

## 15. Matriks keterlacakan elemen kompetensi

Status bukti aktual (direncanakan / diimplementasikan / diuji / terbukti) per subelemen dicatat pada `docs/MATRIKS-KETERLACAKAN.md`. Nomor elemen di bawah mengacu pada `daftar-17-unit.md`. Rentang nomor mencakup semua subelemen di dalamnya. Kolom bukti adalah target yang harus dibuat dan diperiksa; pencantuman di SRS saja belum berarti unit telah dipenuhi.

| Unit | Elemen/subelemen | Kebutuhan/rancangan | Artefak dan verifikasi |
|---|---|---|---|
| 1 | 1.1–1.2; 2.1–2.3 | §2.4–2.6, KT-01–08, §3.2–3.4 | A01: inventaris kebutuhan/alternatif, hasil uji coba tools, keputusan dan risiko |
| 2 | 1.1–1.2; 2.1–2.4 | NF-01–03, §8.1, analisis A02 | A02: lingkup, lingkungan, bottleneck, kompleksitas, pertumbuhan 10×/100×, hardware dan hasil ukur |
| 3 | 1.1–1.2; 2.1–2.2; 3.1–3.2 | §3.2–3.3 dan `poc/` | A03: perbandingan pakai/buat, PoC berjalan, manfaat, integrasi dan limitasi |
| 4 | 1.1–1.2; 2.1–2.3 | §4.2, §4.10, §7.1, NF-07 | A04: skenario, hitungan aksi, batas atas, wireframe, hasil uji interaksi |
| 5 | 1.1–1.3; 2.1–2.2; 3.1–3.4; 4.1–4.4; 5.1 dan 5.5; 6.1–6.2 | Spesifikasi `util/` §11 | A05: skrip input/output, kontrol, prosedur/fungsi berkomentar, array, file, hasil `php -l` dan koreksi |
| 6 | 1.1–1.4; 2.1–2.3; 3.1–3.3; 4.1–4.2; 5.1–5.2 | §4.6–4.7, `TicketService`, `NotifierInterface`, `ResourceReaderInterface`, `oop-demo/` | A06: diagram kelas, visibility, inheritance, polymorphism, simulasi overloading PHP, namespace dan `php -l` |
| 7 | 1.1–1.2; 2.1–2.2; 3.1–3.3; 4.1–4.4; 5.1–5.2; 6.1–6.2; 7.1–7.2; 8.1–8.2 | §6.1–6.2, `database/sql/` | A07: instalasi/eksekusi SQL, DML tabel/view, index, procedure, function, trigger, COMMIT/ROLLBACK dan hasil uji |
| 8 | 1.1–1.3; 2.1–2.2; 3.1–3.3; 4.1–4.4 | NF-05, NF-09, §6, §8.1 | A08: skenario, query dan logikanya, `SHOW GRANTS`, `EXPLAIN`, hasil/performa statement |
| 9 | 1.1–1.3; 2.1–2.4; 3.1–3.2; 4.1–4.3; 5.1–5.2 | §4.2.1 dan `algoritma/` §11 | A09: varian/invarian, flowchart, pseudocode, sorting/searching, benchmark waktu dan memori |
| 10 | 1.1–1.3; 2.1–2.2 | §9.1 migrasi raw SQL ke Query Builder | A10: evaluasi lama/baru, fitur khusus, bukti dua versi, kesetaraan fungsi dan rollback |
| 11 | 1.1–1.2; 2.1–2.3; 3.1–3.2 | F-LOG-01–04, §10 | A11: kriteria lulus, debugger/log, kode error, reproduksi, akar masalah, perbaikan dan uji ulang |
| 12 | 1.1–1.4; 2.1–2.3; 3.1–3.5; 4.1–4.3; 5.1–5.4 | F-DSB-04–05, §2.4, §11 | A12: kebutuhan platform, storyboard section/frame/timeline, target path/DOM, kode, instalasi dan uji kompatibilitas |
| 13 | 1.1–1.2; 2.1–2.2; 3.1–3.2 | NF-10, §9 v1.1, §11 | A13: temuan terhadap spesifikasi/guideline, diff perbaikan, uji perilaku, pengecualian berkomentar bila ada |
| 14 | 1.1–1.3; 2.1–2.3; 3.1–3.3; 4.2–4.4; 5.1–5.2; 6.1–6.4 | NF-01–03, §10 | A14: tim/peran, lingkungan, driver/stub, hasil statis/dinamis, stress test, pembandingan harapan/aktual dan laporan |
| 15 | 1.1–1.2; 2.1–2.2; 3.1–3.2 | F-NTF-01–12, §4.6 | A15: kondisi kritis, informasi/penerima, frekuensi, uji graceful failure dan pemulihan |
| 16 | 1.1–1.2; 2.1–2.2 | F-MON-01–06, §4.7 | A16: sumber/satuan metrik, ambang sebelum failure, grafik dengan garis batas dan hasil uji |
| 17 | 1.1–1.2; 2.1–2.2 | §9.2 dan patch v1.1 | A17: diff versi, rencana penerapan, patch terpasang, backup, uji asap dan rollback |
