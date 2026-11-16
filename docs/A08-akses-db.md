# A08 — Akses basis data (Unit 8, J.620100.021.02)

Bukti: `docs/A08-show-grants.txt` (SHOW GRANTS dan uji hak), `docs/A07-hasil-eksekusi.txt`, `docs/A08-index-benchmark.json` (index awal), `docs/A10-benchmark-*.json` (daftar tiket, 100.000 baris), `tests/unit/*`, `tests/http/run.php`.

## 1. Operasi terhadap basis data (elemen 1)

| Elemen | Operasi nyata dan lokasi kode |
|---|---|
| 1.1 Menyimpan/mengubah data | `TicketService::create()` (nomor tiket lewat `ticket_sequences` dengan `INSERT … ON DUPLICATE KEY UPDATE` + `SELECT … FOR UPDATE`, insert tiket, notifikasi, log aktivitas dalam satu transaksi); `take()` (UPDATE bersyarat atomik); `changeStatus()`, `changePriority()`, `assign()`; komentar, lampiran, notifikasi, pengaturan, kategori, pengguna |
| 1.2 Query untuk informasi | `TicketListQuery::paginate()/export()` (JOIN 4 tabel, filter, pagination), view agregat `v_dashboard`, `v_ticket_by_category`, `v_ticket_by_day`, `v_ticket_sla`; `fn_sla_hours()`; `sp_officer_ranking()` |
| 1.3 Index untuk mempercepat | Lihat §4.3: FULLTEXT judul 310,089 → 0,839 ms (median 5 kali); `idx_active_list` untuk hitungan dan halaman daftar; pembaruan atas `v1.1` pada A10 |

## 2. Prosedur akses (elemen 2)

| Elemen | Pilihan dan alasan |
|---|---|
| 2.1 Library akses | Driver `MySQLi` melalui CodeIgniter Query Builder. Binding parameter otomatis mencegah injeksi; transaksi lewat `transBegin/transCommit/transRollback`. Versi raw `mysqli` (`v0.9`) hanya sebagai baseline migrasi (A10) |
| 2.2 Perintah yang relevan | Query Builder untuk CRUD biasa; SQL eksplisit hanya bila Query Builder tidak punya padanan: `INSERT … ON DUPLICATE KEY UPDATE` dan `SELECT … FOR UPDATE` (nomor tiket), `MATCH … AGAINST` (FULLTEXT), `fn_sla_hours()` (function), `CALL` procedure, `SHOW STATUS` (monitor) |

## 3. Koneksi dan keamanan (elemen 3)

| Elemen | Keputusan |
|---|---|
| 3.1 Teknologi koneksi | MySQLi lokal TCP `127.0.0.1:3306`, kredensial dari `.env` (tidak dikomit; `.gitignore`) |
| 3.2 Keamanan koneksi | Akun aplikasi `tiket_app` dengan password acak 48 karakter heksadesimal; hanya `localhost`; tanpa hak DDL; tanpa hak administrasi (`GRANT`, `mysql.*` ditolak). Skema/migrasi dan backup memakai akun DBA terpisah. Catatan jujur: pada laptop demo akun DBA adalah `root` tanpa password (bawaan XAMPP); untuk lingkungan lain akun DBA harus berpassword dan hanya dipakai saat migrasi/backup |
| 3.3 Hak setiap pengguna | `tiket_app`: `SELECT, INSERT, UPDATE, DELETE` per tabel, `SELECT` per view, `EXECUTE` per routine (`database/sql/06_grants.sql`). Hak diberikan per objek, bukan per database, karena `GRANT` tingkat database pada XAMPP lokal ini menyentuh tabel sistem `mysql.db` yang melaporkan index rusak |

Uji hak nyata (`docs/A08-show-grants.txt`), 17 November 2026, sebagai `tiket_app`:

| Pernyataan | Hasil |
|---|---|
| `CREATE TABLE tiket_demo.x` | `ERROR 1142 CREATE command denied` |
| `DROP TABLE tiket_demo.users` | `ERROR 1142 DROP command denied` |
| `ALTER TABLE tiket_demo.tickets …` | `ERROR 1142 ALTER command denied` |
| `GRANT ALL ON *.* …` | `ERROR 1045 Access denied` |
| `SELECT … FROM mysql.user` | `ERROR 1142 SELECT command denied` |
| `SELECT COUNT(*) FROM tiket_demo.tickets` | berhasil |
| `SELECT fn_sla_hours('Kritis')` | berhasil (4) |

REVOKE: contoh pada database uji ada di `06_grants.sql` (komentar `GRANT CREATE ON tiket_loadtest.*` lalu `REVOKE`). Catatan operasional: setelah `mysqldump` dipulihkan, hak `EXECUTE` routine hilang; `scripts/restore_db.ps1` memasangnya kembali (A11 bug 3).

## 4. Pengujian program basis data (elemen 4)

### 4.1 Skenario uji

| Skenario | Alat | Berkas bukti |
|---|---|---|
| Statement CRUD, view, function, procedure, trigger, COMMIT/ROLLBACK | Skrip SQL | `database/sql/08_uji_objek.sql` → `A07-hasil-eksekusi.txt` |
| Perilaku akses data lewat aplikasi (pembuatan tiket, pengambilan serentak, transisi status, lampiran, ekspor) | PHPUnit + uji HTTP | `tests/unit/*`, `tests/http/run.php` → `A14-http-results.md` |
| Kesetaraan dua teknologi akses (raw vs Query Builder) | PHPUnit | `TicketListQueryEquivalenceTest` |
| Performa dengan 100.000 tiket | Skrip benchmark | `database/benchmark_indexes.php`, `database/benchmark_list_query.php` |

### 4.2 Telusur statement dan logikanya (elemen 4.2)

| Statement (alur) | Logika dan implikasi kinerja |
|---|---|
| Pengambilan tiket: `UPDATE tickets SET assigned_to=?, status='Diproses', status_changed_by=? WHERE id=? AND status='Baru' AND assigned_to IS NULL AND deleted_at IS NULL` | Satu UPDATE bersyarat + `affectedRows()==1` menjamin tepat satu petugas berhasil tanpa lock aplikasi. Uji `TKT-05`: dua petugas serentak → tepat satu log `Diproses` |
| Nomor tiket: `INSERT … ON DUPLICATE KEY UPDATE last_no = last_no + 1` lalu `SELECT … FOR UPDATE` | Kunci baris per hari; tidak memakai `MAX(id)+1` yang rawan tabrakan. Batas 9.999 tiket/hari ditegakkan di kode |
| Daftar tiket: hitung total tanpa JOIN + halaman via subquery id (v1.1) | Mengurangi baris yang di-JOIN dari ratusan ribu menjadi 20 (A10) |
| Pencarian judul ≥3 huruf: `MATCH(title) AGAINST(?)` | Memakai `ft_title` (tipe `fulltext`), bukan `LIKE '%…%'` yang memindai seluruh tabel |

### 4.3 Performa

| Uji (100.000 tiket, `tiket_loadtest`) | Sebelum | Sesudah | Sumber |
|---|---:|---:|---|
| Pencarian judul, median 5 kali: `LIKE` → FULLTEXT | 310,089 ms (`EXPLAIN` `ALL`, ±98.955 baris) | 0,839 ms (`fulltext`, ±1 baris) | `A08-index-benchmark.json` |
| Pencarian judul, p95 30 pengulangan (NF-02), `TicketListQuery` | — | p95 0,69–0,93 ms (target <100 ms) | `A10-benchmark-*.json` |
| Filter status+prioritas (query sederhana) | 0,900 ms | 0,817 ms | `A08-index-benchmark.json` |
| Query SLA | 0,773 ms | 1,084 ms | `A08-index-benchmark.json` |
| Halaman daftar tiket (JOIN) | lihat A10 | lihat A10 | `A10-benchmark-*.json` |

Catatan jujur: index tidak selalu mempercepat — query SLA justru sedikit melambat (0,773 → 1,084 ms) karena optimizer memakai `idx_status_priority` pada data merata; dan index `idx_active_list` saja memperlambat halaman filter hingga ±3,5× sebelum query diubah (deferred join). Rinciannya di A10. Pengukuran berlaku untuk perangkat, data, dan query tersebut, bukan jaminan umum.

### 4.4 Menguji statement akses data (elemen 4.4)

Keluaran dan perubahan data diperiksa: `A07-hasil-eksekusi.txt` (SQL), `A14-http-results.md` (106 pemeriksaan lintas peran, termasuk `TKT-05` pengambilan serentak, `TKT-08` log trigger berpelaku, `LAP-01b` ekspor terfilter), PHPUnit `TicketServiceTest` (transisi dan log), `TicketListQueryTest` (filter, escape wildcard, injeksi sebagai data, pagination).
