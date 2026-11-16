# Tiket Layanan

Aplikasi helpdesk internal berbasis CodeIgniter 4, MariaDB, Bootstrap, dan Chart.js. Dibangun untuk berjalan lokal di XAMPP tanpa layanan pesan atau cloud. Salinan SRS ada di [`docs/SRS.md`](docs/SRS.md).

## Kebutuhan

- Windows 10/11, XAMPP dengan PHP 8.2 dan MariaDB 10.4+, Composer.
- Ekstensi PHP `mysqli`, `mbstring`, `intl`, `zip`, `xml`, `gd` untuk aplikasi dan ekspor.
- CI4 4.7.6 dipakai karena Composer menolak versi 4.5.x yang disebut draf SRS akibat advisori keamanan pada saat instalasi. Paket ekspor: PhpSpreadsheet 5.11 dan Dompdf 3.1.6.

## Instalasi baru

1. Dari folder `tiket-layanan`, jalankan `composer install`.
2. Buat database `tiket_demo` (`CREATE DATABASE tiket_demo CHARACTER SET utf8mb4;`).
3. Salin `.env.example` ke `.env`; sesuaikan URL, host, port, pengguna, dan password database. Pakai akun DBA hanya saat instalasi/migrasi.
4. Jalankan `php spark migrate --all`.
5. Jalankan `php spark db:seed DemoSeeder` untuk 10 pengguna, 6 kategori, 100 tiket, dan 200 komentar.
6. Buat akun `tiket_app` dengan hak terbatas menurut `database/sql/06_grants.sql`, ganti placeholder password, lalu ubah kredensial `.env` ke akun tersebut. Akun aplikasi tidak diberi hak CREATE/ALTER/DROP.
7. Jalankan `php spark serve --port 8081`, buka `http://localhost:8081/login`. Jika memakai Apache XAMPP, arahkan DocumentRoot atau virtual host ke folder `public/` dan sesuaikan `app.baseURL`.

Akun demo: `admin@tiket.local`, `petugas@tiket.local`, `pemohon@tiket.local`; password masing-masing `Demo12345!`. OTP tampil hanya pada mode `CI_ENVIRONMENT=development` dan `DEMO_OTP=true`. Ganti password demo sebelum memakai data di luar laptop lokal.

## Operasional

- `php spark alert:check`: cek alert, pengingat, dan pemulihan; jadwalkan setiap menit dengan Windows Task Scheduler.
- `php spark monitor:collect`: simpan metrik; jadwalkan setiap menit.
- `scripts/register_tasks.ps1` menyiapkan kedua tugas Windows Task Scheduler bila dijalankan secara manual dengan hak yang sesuai.
- `php spark backup:db`: dump database ke `writable/backups/`. Gunakan akun DBA/backup terpisah melalui `BACKUP_DB_USER` dan `BACKUP_DB_PASSWORD` di `.env`; akun aplikasi tidak memerlukan hak `SHOW VIEW`/`TRIGGER`. Routine dan trigger ikut diekspor.
- `php spark migrate:rollback`: rollback migrasi terbaru; jalankan hanya sesudah backup dan dengan akun DBA.

Di lingkungan ini pembacaan WMI/CIM ditolak sistem, sehingga CPU/RAM ditampilkan `Tidak tersedia`; disk tetap dibaca melalui PHP. Aplikasi tiket tetap berjalan dan alert pembaca metrik tercatat. Pada Windows dengan izin CIM, adapter akan menampilkan CPU/RAM.

## Pengujian dan artefak

- `vendor/bin/phpunit --no-coverage`: uji OTP, transaksi/status tiket, alert, algoritma. Database `tiket_test` harus dimigrasi terlebih dahulu dan dikonfigurasi pada `.env`.
- `vendor/bin/phpstan analyse --no-progress`: analisis statis level 5.
- `vendor/bin/phpcs`: pemeriksaan PSR-12; panjang baris dikecualikan karena PSR-12 menetapkannya sebagai batas lunak.
- `php algoritma/benchmark.php`: perbandingan sorting dan searching.
- `php poc/ekspor.php` dan `poc/grafik.html`: PoC library.
- `LOADTEST_COUNT=100000 php spark db:seed LoadTestSeeder` hanya untuk database bernama `*loadtest*`; jangan dijalankan pada `tiket_demo`. Jalankan dengan akun DBA setelah migrasi. `php database/benchmark_indexes.php` menulis hasil `EXPLAIN` dan median ke `docs/A08-index-benchmark.json`.

Portofolio unit ada di `docs/`. Status bukti di `docs/STATUS.md` membedakan fitur yang berjalan dari bukti asesmen yang masih diperlukan.
