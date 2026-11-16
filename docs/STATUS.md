# Status implementasi terhadap SRS

Tanggal pemeriksaan: 16 November 2026. Dokumen ini mencatat hasil yang benar-benar dijalankan pada workspace lokal; dokumen SRS tetap menjadi spesifikasi.

| Area | Status | Bukti / batas |
|---|---|---|
| Instalasi CI4, migrasi DB, data demo | Berjalan | `migrate --all`, `DemoSeeder`: 10 pengguna, 6 kategori, 100 tiket, 200 komentar |
| Login password + OTP demo | Berjalan | Uji HTTP login dan OTP; PHPUnit OTP |
| Tiket, status, log trigger, komentar, lampiran | Inti berjalan | Uji HTTP buat/ambil/selesaikan; trigger tercatat; unduhan/unggahan perlu uji manual browser |
| Dashboard, grafik, slideshow | Halaman berjalan | Aset lokal tersedia; uji visual lintas browser masih diperlukan |
| Alert dan monitor | Berjalan dengan graceful failure | CLI alert berjalan; WMI/CIM ditolak lingkungan, CPU/RAM tidak tersedia |
| Ekspor Excel/PDF | Berjalan | HTTP 200, XLSX 9.683 byte, PDF 2.333 byte pada uji lokal |
| SQL/index | Berjalan | 100.000 tiket loadtest; hasil `docs/A08-index-benchmark.json` |
| Analisis statis dan unit test | Lulus | PHPStan level 5, PHPCS, PHPUnit 8 tes/24 assertion |
| Stress test 50 pengguna | Belum terbukti | `ab -n 50 -c 50` pada PHP development server: 0 gagal, p95 23.794 ms; uji 1000 permintaan dan sesi sah di Apache belum dilakukan |
| Migrasi teknologi bersejarah dan tag rilis | Belum terbukti | Tidak ada riwayat v0.1 raw SQL → v1.0 Query Builder yang autentik; jangan klaim Unit 10/17 selesai |
| Review, UX, kompatibilitas browser | Perlu bukti manual | Laporan/wireframe awal tersedia, uji pengguna dan browser belum dicatat |

Seluruh fitur berprioritas W belum dinyatakan tuntas sampai pengujian akses setiap peran, unggahan, ekspor terfilter, rollback, dan uji Apache selesai.
