# A02 — Skalabilitas

Lingkup: satu instansi lokal, 50 sesi serentak sebagai target uji, database demo 100 tiket dan database loadtest 100.000 tiket. Lingkungan: Windows, PHP 8.2, MariaDB 10.4, PHP development server untuk uji awal. Titik sempit: pencarian judul tanpa index FULLTEXT, polling monitor tiap 5 detik, pembuatan XLSX seluruh hasil filter.

`LoadTestSeeder` menghasilkan 100.000 tiket dan 200 pengguna. Pengukuran di `A08-index-benchmark.json`: pencarian judul median 310,089 ms sebelum index (LIKE) dan 0,839 ms sesudah FULLTEXT (MATCH), `EXPLAIN` berubah dari `ALL` sekitar 98.955 baris menjadi `fulltext` sekitar 1 baris. Filter status/prioritas pada data ini 0,900 → 0,817 ms; query SLA 0,773 → 1,084 ms, sehingga index tidak selalu mempercepat setiap query. Estimasi 1 juta baris harus diuji ulang, karena ekstrapolasi linear tidak memperhitungkan cache, ukuran index, atau disk.

Kebutuhan hardware minimum perlu diukur pada perangkat asesmen. Uji `ab` terhadap PHP development server tidak membuktikan kapasitas Apache/50 pengguna terautentikasi; lihat A14. Catat CPU, RAM, disk, dan konfigurasi Apache ketika uji akhir dijalankan.
