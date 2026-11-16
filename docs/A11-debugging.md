# A11 — Debugging (Unit 11, J.620100.025.02)

## 1. Persiapan (elemen 1)

Kode sesuai spesifikasi SRS (1.1). Alat bantu debugging (1.2): Debug Toolbar CI4 (`CI_ENVIRONMENT=development`), `log_message()` ke `writable/logs/log-YYYY-MM-DD.log`, `php -l`, PHPStan, PHPUnit, `EXPLAIN`, log Apache `D:\xampp\apache\logs\error-tiket.log`, konsol browser lewat CDP (`tests/browser/capture.mjs` mencatat `console.error`, `exceptionThrown`, dan respons HTTP ≥ 400).

## 2. Kriteria lulus (elemen 2.1–2.3)

| Kriteria | Cara memeriksa | Lulus bila |
|---|---|---|
| Kompilasi sintaks (2.1) | `php -l` pada berkas terkait; hasil `util/hasil-php-l.txt` | "No syntax errors detected" |
| Build/analisis (2.2) | `vendor/bin/phpstan analyse` (level 5), `vendor/bin/phpcs` (PSR-12) | 0 error baru |
| Eksekusi aplikasi (2.3) | `vendor/bin/phpunit`, `php tests/http/run.php` (106 pemeriksaan lintas peran), uji browser | Respons dan perubahan data sesuai harapan, tanpa galat tak tertangani; setiap kegagalan mencatat kode kesalahan |

Kode kesalahan internal yang dipakai: `E-TKT-001` (gagal buat tiket), `E-MON-001` (pembaca metrik), `E-ALT-001` (pemeriksaan alert), `E-DB-001`/`E-DB-002` (prepare/execute raw SQL v0.9), halaman galat ramah pengguna `E-ACL-403`, `E-TKT-404`, `E-ATT-404`, `E-ATT-410`, `E-LAP-400`, `E-SYS-404`.

## 3. Tabel bug nyata (elemen 3)

Seluruh bug di bawah benar-benar ditemukan saat pengembangan atau pengujian dan diperbaiki; tidak ada yang direkayasa. Commit/tag merujuk repositori `tiket-layanan` (`git show <tag>`).

| # | Bug dan gejala | Langkah reproduksi | Kode / log / debugger | Akar penyebab | Perbaikan (commit/tag) | Uji ulang |
|---|---|---|---|---|---|---|
| 1 | `spark migrate` fatal setelah scaffold | Pasang scaffold CI4 4.5 lalu framework 4.7.6 | Fatal `Kint`/`Format` pada CLI | File konfigurasi scaffold versi lama tidak cocok dengan framework 4.7 | Selaraskan file konfigurasi scaffold (`v0.8`) | `migrate --all` berhasil |
| 2 | Seeder gagal pada `CLIRequest` | `db:seed DemoSeeder` | Error pada `ActivityLogger` | `getUserAgent()` tidak ada di request CLI | IP/user-agent khusus CLI di `ActivityLogger` (`v0.8`) | Seeder 100 tiket berhasil |
| 3 | HTTP 500 saat admin mengubah prioritas setelah restore backup | Restore `baseline_demo.sql` lalu `POST /tickets/{id}/priority` | `ERROR - 2026-11-16 15:53:36 --> execute command denied to user 'tiket_app'@'localhost' for routine 'tiket_demo.fn_sla_hours'` | Dump membuat ulang routine; hak `EXECUTE` melekat pada objek lama dan hilang saat `DROP FUNCTION` | `scripts/restore_db.ps1` memasang ulang `06_grants.sql` setelah restore; dicatat di A17 (`v0.9-audit`) | `TKT-11` pada uji HTTP lulus |
| 4 | Unggah lampiran selalu crash (HTTP 500) | Pemilik tiket unggah PNG valid di `/tickets/{id}/attachments` | `CRITICAL - 2026-11-16 15:53:32 --> ErrorException: finfo_file(D:\xampp\tmp\php6052.tmp): Failed to open stream` pada `Attachments.php` baris 26 | `getMimeType()` dan `getSize()` dipanggil **setelah** `$file->move()`; berkas sementara sudah hilang | MIME/ukuran dibaca sebelum `move()`; nama asli disimpan terpisah (`v0.9-audit`) | `KOM-02b/02c/03b/04` lulus; berkas palsu berekstensi `.png` ditolak |
| 5 | Aplikasi diam-diam berjalan sebagai `production` | `php spark env` mencetak kosong; Debug Toolbar tak muncul walau `.env` memuat `development` | `od -c .env` menunjukkan byte `EF BB BF` di awal berkas | `.env` disimpan UTF-8 **dengan BOM** sehingga kunci pertama menjadi `\uFEFFCI_ENVIRONMENT` | BOM dihapus; README meminta UTF-8 tanpa BOM (dikerjakan bersama `v0.9-audit`) | `php spark env` → `development`; Toolbar muncul |
| 6 | `ab /monitor/metrics -n 1000 -c 50` tak selesai >10 menit | `ab -n 1000 -c 50 -C <cookie admin> http://localhost:8082/monitor/metrics` | `Get-Process` menunjukkan CPU 100% oleh Apache; log tak ada galat | Tiap permintaan menjalankan PowerShell + CIM (`WindowsResourceReader`) | `MonitorService::snapshot()` dengan TTL 3 detik dan kunci berkas `flock` (`v0.9-audit`) | `ab` selesai: 1000 permintaan, 0 gagal (A14) |
| 7 | Sesi pengguna yang dinonaktifkan admin tetap bisa bekerja | Login pemohon, admin menonaktifkan, pemohon `GET /tickets` | Uji `AUTH-ACT`: status 200 sebelum perbaikan | `AuthFilter` hanya memeriksa `session('user_id')`; rute admin bahkan tidak lewat `AuthFilter` | `AuthFilter` memeriksa `is_active` dan peran terbaru ke DB tiap permintaan; `RoleFilter` memanggil `AuthFilter` (`v0.9-audit`) | `AUTH-ACT` dan `AUTH-ACT2` lulus |
| 8 | Pencarian `_d` / `0%` mengembalikan hasil berbeda setelah migrasi | `TicketListQueryEquivalenceTest` skenario #7 | PHPUnit: `Failed asserting that two arrays are equal`, `Skenario #7 {"q":"_d"}` | `like()` Query Builder tidak meng-escape wildcard `%`/`_`; versi raw meng-escape | `escapeLikeString()` + `ESCAPE '!'` (`v1.0`) | 23 tes lulus, kesetaraan 14+5 skenario |
| 9 | Benchmark raw SQL melempar `Call to a member function prepare() on bool` | `php database/benchmark_list_query.php` | `Error 500 ... TicketListQuery.php line 98` | Koneksi CI4 lazy: `connID` bernilai `false` sebelum query pertama | `$this->db->initialize()` sebelum memakai `mysqli` (`v0.9`) | Benchmark berjalan |
| 10 | `GET /login` menuju Apache XAMPP 404 | Buka `http://localhost:8080/login` | Respons 404 dari Apache XAMPP | Port 8080 dipakai Apache XAMPP | Server pengembangan di 8081; instance uji di 8082 | HTTP 200 |
| 11 | Redirect memakai `/index.php/login` | Uji HTTP: `Location: /index.php/login` | Header `Location` | `Config\App::$indexPage = 'index.php'` | `indexPage = ''` (URL bersih; rewrite Apache dan router `spark serve` mendukung) | 106/106 lulus |
| 12 | PHPUnit gagal membuat tabel | `phpunit` dengan konfigurasi bawaan | Galat SQLite / prefix `db_` | `phpunit.xml.dist` bawaan memilih SQLite dan prefix | Gunakan `tiket_test` MySQLi tanpa prefix lewat `.env` | PHPUnit lulus |
| 13 | `MATCH ... AGAINST` gagal sebelum index | Benchmark sebelum migrasi index | `Can't find FULLTEXT index` | Sebelum index tidak ada FULLTEXT | Ukur `LIKE` sebelum, `MATCH` sesudah | `A08-index-benchmark.json` |
| 14 | `backup:db` gagal dengan akun aplikasi | `php spark backup:db` | `SHOW VIEW command denied` | Akun aplikasi sengaja tanpa hak `SHOW VIEW`/`TRIGGER` | Akun backup DBA terpisah (`BACKUP_DB_USER`) | Dump berhasil |

Tangkapan layar debugger/log: `docs/screenshots/` (Debug Toolbar dan halaman galat berkode, A12) dan potongan log pada kolom "Kode / log" di atas, disalin dari `writable/logs/log-2026-11-16.log`.

## 4. Merumuskan dan melakukan perbaikan (elemen 3.1, 3.2)

Setiap baris di atas mengikuti siklus: reproduksi → baca log/debugger → temukan akar penyebab → rumuskan perbaikan paling kecil → ubah kode → ulang uji yang gagal dan seluruh rangkaian uji (PHPUnit 29 tes, uji HTTP 106+ pemeriksaan, PHPStan, PHPCS) untuk memastikan tidak ada regresi.
