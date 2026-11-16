# A14 — Pengujian

Pelaksana: asesi; peninjau: asesor saat demo. Lingkungan: PHP 8.2.12, MariaDB 10.4.32, CI4 4.7.6, Windows lokal. Data: `tiket_demo` 100 tiket, `tiket_test` terpisah, `tiket_loadtest` 100.000 tiket.

| Uji | Harapan | Aktual | Status |
|---|---|---|---|
| `vendor/bin/phpunit --no-coverage` | Semua lulus | 8 tes, 24 assertion | Lulus |
| `vendor/bin/phpstan analyse` level 5 | 0 error | 0 error | Lulus |
| `vendor/bin/phpcs` PSR-12 | 0 error | 0 error | Lulus |
| HTTP login/OTP, buat/ambil/selesaikan tiket | Respons 200, DB berubah, trigger log | Sesuai | Lulus |
| HTTP dashboard, admin, monitor, Excel, PDF | Respons 200 | Sesuai | Lulus awal |
| `ab -n 50 -c 50 /login` pada PHP development server | Catat hasil | 50 selesai, 0 gagal, 1,97 req/dtk, p95 23.794 ms | Pengukuran awal |
| `ab -n 1000 -c 50` | Selesai penuh | Dihentikan setelah 100 permintaan karena server pengembangan sangat lambat | Belum lulus |

Pengujian 50 **sesi terautentikasi** di Apache XAMPP belum dilakukan; angka `ab` bukan pengganti uji itu. CPU/RAM saat stress test tidak tersedia karena akses WMI/CIM ditolak. PHPUnit dijalankan tanpa coverage karena driver coverage tidak terpasang. Driver/stub: `AlertServiceTest` menyuntikkan implementasi `ResourceReaderInterface` terkontrol untuk kondisi CPU tinggi dan pulih.
