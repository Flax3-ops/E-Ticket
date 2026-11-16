# A14 — Pengujian (Unit 14, J.620100.036.02)

## 1. Peralatan, dokumen, dan tim (elemen 1)

| Aspek | Isi |
|---|---|
| Peralatan (1.1) | PHPUnit 10.5.67 (uji dinamis, driver/stub), PHPStan 2.3.6 level 5 dan PHP_CodeSniffer 4.0.4 PSR-12 (statis), `php -l`, skrip HTTP `tests/http/run.php` dan `stress.php`, ApacheBench 2.3 (`ab`), `Get-Counter` (CPU/RAM), Chrome/Edge via CDP (`tests/browser/capture.mjs`) |
| Dokumen pendukung (1.2) | SRS v0.4, `MATRIKS-KETERLACAKAN.md`, berkas hasil di `docs/` (daftar §6) |
| Tim dan peran (1.3) | Asesi: pelaksana pengujian. Asesor: peninjau pada demonstrasi. Pembantu AI menjalankan skrip dan mencatat hasil atas arahan asesi |
| Lingkungan | Windows 11 build 26200; Core i5-11400H 6 inti/16 GB; PHP 8.2.12; MariaDB 10.4.32; Apache 2.4.58; CI4 4.7.6. Data: `tiket_demo` (100 tiket, dipulihkan dari backup sebelum tiap putaran), `tiket_test` (PHPUnit), `tiket_loadtest` (100.000 tiket) |

## 2. Driver dan stub (elemen 2)

| Unit yang diuji | Driver | Stub | Berkas |
|---|---|---|---|
| `AlertService` (throttle, pulih, metrik tak tersedia) | Kelas uji PHPUnit memanggil `check()` berulang | Kelas anonim `ResourceReaderInterface` (CPU 99% / gagal baca) disuntikkan ke `MonitorService` | `tests/unit/AlertServiceTest.php` |
| `TicketService` | PHPUnit dalam transaksi yang di-rollback | Tidak perlu; DB uji sungguhan | `TicketServiceTest.php` |
| `OtpService` | PHPUnit | — | `OtpServiceTest.php` |
| `TicketListQuery` | PHPUnit | `RawTicketListQuery` (implementasi lama) sebagai pembanding | `TicketListQuery*Test.php` |
| Alur HTTP seluruh peran | `tests/http/run.php` (klien curl dengan cookie dan CSRF) | — | `docs/A14-http-results.md` |

Hasil tercatat pada lembar uji di bawah (2.3).

## 3. Pelaksanaan dan lembar uji (elemen 3)

Program uji dikompilasi/diperiksa dengan `php -l`; hasil terkini (17 Nov 2026, v1.1):

| Uji | Harapan | Aktual | Status |
|---|---|---|---|
| `vendor/bin/phpunit --no-coverage` | Semua lulus | **29 tes, 115 assertion** (TicketListQuery 7, TicketService 6, AlgorithmsTiket 3, AlertService 3, Equivalence 2, TicketEntity 2, OtpService 2, Health 2, ExampleSession 1, Algorithms 1) | Lulus |
| `vendor/bin/phpstan analyse` level 5 (Controllers, Services, Libraries, Commands, Filters, Models, Entities, Helpers) | 0 error | 0 error | Lulus (analisis statis) |
| `vendor/bin/phpcs` PSR-12 (idem) | 0 error | 0 error | Lulus (analisis statis) |
| `php -l` seluruh `util/`, `oop-demo/`, `algoritma/` | Tanpa galat sintaks | Lulus (kecuali `util/koreksi-sintaks/sebelum.php`, yang sengaja salah) | Lulus |
| `php tests/http/run.php` (Apache) | Semua pemeriksaan lulus | **119/119** | Lulus |
| Skrip SQL `08_uji_objek.sql` | Objek DB sesuai | Lihat A07 | Lulus |
| Uji browser Chrome/Edge | Tanpa galat konsol/overflow, A04 dalam batas | 24 halaman × 2 browser, 0 masalah | Lulus; Firefox terbatas (A12) |

Cakupan uji HTTP lintas peran (`A14-http-results.md`): login/OTP/logout, CRUD kategori dan pengguna, 16 penolakan + 14 akses sah per rute, XSS, SLA 4 jam, pengambilan serentak, transisi status, buka ulang 7 hari, tiket Ditutup final, lampiran (palsu, >2 MB, maks 3, nama acak, 6 peran unduh), prioritas+SLA, penugasan admin, soft delete, JSON dashboard, ekspor Excel terfilter dan PDF, acknowledge alert, akun nonaktif, OTP salah 3×.

## 4. Stress test (elemen 4)

Berkas lengkap: `A14-stress-results-production.md`, `A14-stress-results-development.md`, `A14-stress-results-development-opcache.md`. Kondisi awal dan selama uji dicatat per detik (Get-Counter). Instance Apache terpisah (`scripts/apache-tiket.conf`, port 8082), data 100 tiket.

**(a) 50 sesi terautentikasi serentak** — 50 pengguna berbeda (`load01..50`), masing-masing login password+OTP nyata, lalu 6 halaman × 5 putaran (1.500 permintaan):

| Konfigurasi | Login serentak p95 | Alur kerja p50 / p95 / maks | req/s | Error | CPU rata-rata saat alur kerja |
|---|---:|---:|---:|---:|---:|
| Production (v0.9-audit), tanpa OPcache | 2.023 ms | 1.205 / 1.420 / 1.493 ms | 34,1 | 0/1.700 | 96,5% |
| Development (Toolbar), tanpa OPcache | 2.550 ms | 1.584 / 1.993 / 2.976 ms | 24,4 | 0/1.700 | 96,4% |
| Development + OPcache (v1.1) | 1.399 ms | 620 / 795 / 1.691 ms | 56,5 | 0/1.700 | 93,1% |

Keberhasilan: **50 dari 50 sesi login dan bekerja tanpa error** pada ketiga konfigurasi. Kondisi awal: CPU 12–26%, RAM bebas 2,7–7,9 GB. Selama uji CPU jenuh (≈90–100%) — batas kemampuan perangkat ini: waktu respons p95 1,4–2,0 detik tanpa OPcache, 0,8 detik dengan OPcache.

**(b) ApacheBench `-n 1000 -c 50` (uji HTTP dasar, bukan bukti 50 pengguna):**

| Rute (production) | Selesai/Gagal | req/s | p50 / p95 / maks ms | CPU rata-rata |
|---|---:|---:|---:|---:|
| `/login` | 1000 / 0 | 41,6 | 1.116 / 1.895 / 2.657 | 98,4% |
| `/tickets` (satu cookie) | 1000 / 0 | 24,3 | 1.945 / 2.768 / 2.861 | 62,3% |
| `/monitor/metrics` (satu cookie admin) | 1000 / 0 | 15,6 | 3.397 / 4.365 / 6.277 | 47,8% |

`ab` dengan **satu** cookie sesi membuat 50 permintaan paralel antre pada kunci berkas sesi (CPU tidak jenuh pada `/tickets` dan `/monitor/metrics`); karena itu angkanya lebih rendah daripada 50 sesi berbeda dan tidak boleh dibaca sebagai kapasitas 50 pengguna. Sebelum perbaikan snapshot, `ab /monitor/metrics` tidak selesai dalam 10+ menit (A11 bug 6).

## 5. Perbandingan harapan vs aktual dan kondisi tidak sesuai (elemen 5)

| Harapan | Aktual | Selisih / analisis |
|---|---|---|
| NF-03: 50 sesi sah berhasil dengan p95 terukur | 50/50 berhasil; p95 0,8–2,0 s | Memenuhi keberhasilan; latensi dibatasi CPU. OPcache menurunkan p95 ±60% |
| NF-02: pencarian judul 100.000 tiket p95 < 100 ms | p95 0,69–0,93 ms (v1.0), 4,8 ms (v1.1) | Memenuhi; v1.1 lebih lambat 3,5 ms (deferred join) tetapi jauh di bawah target |
| NF-01: daftar tiket p95 < 500 ms pada 100 tiket | Alur `/tickets` dalam 50 sesi serentak p50 < 1 detik; satu pengguna (PHPUnit/HTTP) cepat | Pengukuran formal p95 30 permintaan satu pengguna **belum** dilakukan terpisah (sebagian terwakili `ab`); catat sebagai batas |
| Uji awal (16 Nov) `ab -n 50 -c 50` pada server pengembangan: p95 23.794 ms | Digantikan uji Apache di atas | Server pengembangan PHP tunggal-utas tidak mewakili |
| `ab /monitor/metrics` selesai | Tidak selesai >10 menit | Bug nyata: pembacaan CIM tiap permintaan; diperbaiki snapshot; kini 15,6 req/s dan selesai |
| Pembaca metrik OS tersedia (F-MON-01) | CPU/RAM "Tidak tersedia" pada halaman monitor | Bug nyata: `escapeshellarg` merusak skrip PowerShell di Windows; diperbaiki `-EncodedCommand` (A11 bug 15) |
| Dashboard/screenshot Monitor dari server 8081 | CPU/RAM tak tersedia | Server 8081 dijalankan akun Windows lain yang ditolak CIM; instance Apache akun pengguna berfungsi — batas lingkungan, bukan kode |

## 6. Pelaporan (elemen 6)

- Peralatan (6.1): lihat §1. Kondisi selama uji (6.2): CPU/RAM per fase pada berkas `A14-stress-results-*.md`; error 0.
- Data hasil (6.3): `A14-http-results.md`, `A14-stress-results-*.md`, `A07-hasil-eksekusi.txt`, `A10-benchmark-*.json`, `A09-benchmark.csv`, `A17-hasil-pembaruan.txt`, `docs/screenshots/hasil-*.json`.
- Analisis (6.4): §5. Batas klaim: satu laptop (klien uji ikut memakai CPU), data sintetik, 1.700 permintaan per konfigurasi; Firefox otomatis tidak diuji; review/pengujian oleh pihak independen belum ada.
