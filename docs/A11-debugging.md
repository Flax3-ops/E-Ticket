# A11 — Debugging

Kriteria lulus: `php -l` tanpa error sintaks, PHPStan level 5/PHPCS tanpa error, halaman inti HTTP 200, dan hasil DB sesuai skenario. Bug yang benar-benar ditemukan selama pembangunan:

| Masalah | Gejala | Perbaikan | Uji ulang |
|---|---|---|---|
| Scaffold CI4 4.5 dengan framework 4.7 | Kint/Format fatal saat `spark migrate` | Selaraskan file konfigurasi scaffold 4.7 | Migrasi berhasil |
| Logger CLI memakai `getUserAgent()` | Seeder gagal pada `CLIRequest` | Isi IP/user agent khusus CLI | Seeder 100 tiket berhasil |
| Port 8080 dipakai Apache | GET `/login` menuju Apache 404 | Pakai port 8081 | HTTP 200 |
| PHPUnit memilih SQLite / prefix `db_` | Uji DB gagal | Gunakan `tiket_test` MySQLi tanpa prefix | PHPUnit 8 tes lulus |
| Benchmark FULLTEXT tanpa index | `MATCH` gagal sebelum index | Sebelum index ukur `LIKE`, sesudah index `MATCH` | Benchmark JSON dihasilkan |
| Backup dengan akun aplikasi | `SHOW VIEW` ditolak | Pisahkan akun DBA backup | `backup:db` berhasil |

Log aplikasi memakai kode `E-TKT-001`, `E-MON-001`, `E-ALT-001`. Tangkapan layar debugger dan log build untuk asesmen perlu diambil pada demonstrasi.
