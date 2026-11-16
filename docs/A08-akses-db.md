# A08 — Akses basis data

Aplikasi memakai MySQLi melalui CodeIgniter Query Builder. Akun `tiket_app` lokal diberi `SELECT, INSERT, UPDATE, DELETE` per tabel dan `EXECUTE` per routine; tidak diberi DDL. `SHOW GRANTS` diuji. Karena tabel sistem `mysql.db` pada XAMPP lokal melaporkan index rusak saat GRANT tingkat database, hak diberikan per tabel/view/routine tanpa mengubah tabel sistem tersebut.

Skenario statement: pembuatan tiket memasukkan nomor unik dan tenggat SLA; pengambilan memakai UPDATE bersyarat dan transaksi; perubahan status memicu trigger; filter daftar memakai Query Builder; pencarian judul memakai FULLTEXT. Hasil `EXPLAIN` dan pengukuran sebelum/sesudah index tersedia pada `A08-index-benchmark.json`. Verifikasi hasil aktual dan rollback ada di uji PHPUnit/HTTP serta SQL demo.
