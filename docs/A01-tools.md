# A01 — Analisis tools (Unit 1, J.620100.001.01)

Acuan: SRS §2.4–2.6, §3.1–3.4 (KT-01 s.d. KT-08). Dokumen ini mencatat apa yang **benar-benar dijalankan** pada laptop pengembangan (Windows 11, Intel Core i5-11400H 6 inti/12 thread, RAM 16 GB).

## 1. Inventaris kebutuhan tools dari dokumen (elemen 1.1)

| ID | Kebutuhan (sumber SRS) | Tools yang dibutuhkan |
|---|---|---|
| KT-01 | Berjalan lokal tanpa internet saat demo | Web server + DB lokal, aset front-end disimpan lokal (`public/vendor`) |
| KT-02 | Dapat dipelajari dan dibangun cepat | Framework yang sudah dikuasai |
| KT-03 | MVC, migrasi DB, seeder, CLI | Framework dengan migrasi dan CLI (`spark`) |
| KT-04 | Uji unit | PHPUnit |
| KT-05 | Grafik interaktif dan animasi | Pustaka grafik JS |
| KT-06 | Responsif tanpa build tool | CSS framework siap pakai |
| KT-07 | Ekspor Excel dan PDF | PhpSpreadsheet, Dompdf |
| KT-08 | Sudah dikuasai pengembang | Pengalaman PKL E-Kinerja (CodeIgniter) |
| (Unit 14) | Analisis statis dan uji beban | PHPStan, PHP_CodeSniffer, ApacheBench |
| (Unit 17) | Versi dan pembaruan | Git, Composer, `mysqldump` |

## 2. Kemungkinan penggunaan tools yang tersedia (elemen 1.2)

| Tools yang sudah ada di mesin | Dipakai untuk | Catatan |
|---|---|---|
| XAMPP 8.2.12 (Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32, `ab.exe`, `mysqldump`) | Server, DB, stress test, backup | Tanpa virtualisasi; Docker ditolak karena butuh WSL2 dan berat |
| Composer 2.9.3 | Dependensi dan autoload | `composer.lock` dikomit |
| Git 2.49 | Riwayat dan tag rilis | Tag `v0.8`, `v0.9-audit`, `v0.9`, `v1.0`, `v1.1` |
| Node.js 24 | Hanya untuk skrip uji browser `tests/browser/capture.mjs` (tanpa dependensi npm) | Bukan bagian aplikasi |
| Chrome, Edge, Firefox | Uji kompatibilitas (A12) | Chrome dan Edge dikendalikan lewat CDP; Firefox terbatas |

## 3. Pemilihan tools (elemen 2.1)

Perbandingan alternatif lengkap ada pada SRS §3.2. Keputusan akhir dan alasannya:

| Area | Dipilih | Alasan terukur terhadap kebutuhan |
|---|---|---|
| Server | XAMPP | KT-01, KT-02, KT-08; sudah terpasang dan membawa `ab` |
| Framework | CodeIgniter 4.7.6 | KT-02, KT-03, KT-04, KT-08. Versi 4.5.x dari rancangan awal ditolak Composer karena advisori keamanan, sehingga dipasang 4.7.6 (dicatat pada SRS v0.3) |
| DB | MariaDB 10.4.32 | Mendukung view, function, procedure, trigger, GRANT (Unit 7–8) |
| Front-end | Bootstrap 5.3.3, Chart.js | KT-05, KT-06, salinan lokal di `public/vendor` (KT-01) |
| Ekspor | PhpSpreadsheet 5.11.0, Dompdf 3.1.6 | KT-07 |
| Kualitas | PHPUnit 10.5.67, PHPStan 2.3.6 (level 5), PHP_CodeSniffer 4.0.4 (PSR-12) | KT-04, NF-10 |

## 4. Uji coba penggunaan tools (elemen 2.2)

| Tools | Perintah / langkah | Hasil aktual | Kendala dan keputusan |
|---|---|---|---|
| PHP | `php -v` | 8.2.12 (ZTS) | — |
| Composer | `composer --version` | 2.9.3 | — |
| CI4 | `composer create-project` lalu `composer require` | Scaffold 4.5.x ditolak (advisori keamanan); 4.7.6 terpasang | Konfigurasi scaffold diselaraskan ke 4.7 (A11 bug 1) |
| Migrasi | `php spark migrate --all` | 5 migrasi berhasil pada `tiket_demo` (migrasi ke-5 di v1.1) | Memakai akun DBA; `tiket_app` sengaja tanpa DDL |
| Seeder | `php spark db:seed DemoSeeder` | 10 pengguna, 6 kategori, 100 tiket, 200 komentar | Seeder memakai `TicketService` agar nomor tiket dan trigger ikut teruji |
| Apache | `httpd.exe -f scripts/apache-tiket.conf` (instance terpisah, port 8082) | Aplikasi berjalan lewat Apache + PHP modul | Port 8080 dipakai Apache XAMPP, 8081 dipakai server pengembangan `spark serve`; uji beban memakai 8082 tanpa mengubah `httpd.conf` XAMPP |
| ApacheBench | `ab -n 1000 -c 50 -l -s 60 <url>` | Berjalan; hasil di `A14-stress-results-production.md` | Cookie sesi tunggal membuat permintaan antre pada kunci sesi (lihat A14) |
| PHPUnit | `vendor/bin/phpunit --no-coverage` | 26 tes lulus (lihat A14) | Driver coverage tidak terpasang |
| PHPStan | `vendor/bin/phpstan analyse` | 0 error level 5 | Perlu `scanFiles` untuk helper CI4 |
| PHPCS | `vendor/bin/phpcs` | 0 error PSR-12 | Panjang baris dikecualikan (batas lunak PSR-12) |
| Git | `git tag`, `git diff v0.9 v1.0` | Tag dan diff tersedia | `safe.directory` perlu diatur per-repo karena folder dibuat pengguna Windows lain |
| Chrome/Edge | `tests/browser/capture.mjs` lewat CDP | Screenshot, interaksi, hitung aksi (A04, A12) | Firefox tidak mendukung CDP |

## 5. Risiko pengembangan akibat tools (elemen 2.3)

| Risiko | Dampak | Kemungkinan | Mitigasi | Terjadi nyata? |
|---|---|---|---|---|
| Port 80/3306/8080/8081 bentrok | Aplikasi tak terjangkau | Sedang | Port dicatat di README; instance uji di 8082 | Ya — 8080 dipakai Apache, 8081 dipakai `spark serve` |
| `.env` berawalan BOM UTF-8 | Baris pertama (`CI_ENVIRONMENT`) tidak terbaca; aplikasi diam-diam berjalan sebagai `production` | Sedang | BOM dihapus; README meminta simpan `.env` sebagai UTF-8 tanpa BOM | **Ya** — ditemukan saat uji beban (A11 bug 5) |
| Akses WMI/CIM ditolak pada sandbox | CPU/RAM `Tidak tersedia` | Sedang | Graceful failure; pada laptop pengguna CIM bekerja | Ya, hanya di sandbox |
| Pembacaan CIM mahal (memanggil PowerShell) | `/monitor/metrics` menjenuhkan CPU saat banyak permintaan | Tinggi | Snapshot ber-TTL 3 detik dengan kunci berkas (v0.9-audit) | **Ya** — `ab` terhenti 10+ menit sebelum perbaikan (A11 bug 6) |
| `mysqldump` + restore menghapus hak `EXECUTE` routine | `fn_sla_hours` gagal untuk `tiket_app` | Sedang | Jalankan ulang `database/sql/06_grants.sql` setelah restore | **Ya** (A11 bug 3, A17) |
| Versi framework berubah antar-mayor | Konfigurasi scaffold tidak cocok | Rendah | `composer.lock` dikomit, versi dicatat | Ya (A11 bug 1) |
| CDN tidak terjangkau | Tampilan rusak | Sedang | Aset di `public/vendor` | Tidak |
| `.git` dimiliki akun Windows lain | Git menolak (dubious ownership) | Sedang | `safe.directory` per-repo (bukan `*`) | Ya |
