# A07 — Menggunakan SQL (Unit 7, J.620100.020.02)

Skrip: `database/sql/01_views.sql` … `08_uji_objek.sql`. Hasil eksekusi nyata: `docs/A07-hasil-eksekusi.txt`, dijalankan 17 November 2026 pada database uji `tiket_test` (bukan `tiket_demo`) dengan perintah:

```
mysql -uroot -t --force --show-warnings < database/sql/08_uji_objek.sql > docs/A07-hasil-eksekusi.txt
```

`--force` melanjutkan skrip setelah galat yang memang diharapkan. Tiga galat itu tercatat pada keluaran (garis `ERROR 1644/1451`) dan ditandai `HARAPAN: GAGAL` pada judul langkah; karena `stderr` tidak berurutan dengan `stdout`, baris galat muncul di dekat awal berkas, sedangkan pesan `Error (Code …)` per langkah muncul di bawah judulnya. Skrip membersihkan data ujinya sendiri (langkah 12).

## 1. Memasang dan menjalankan perangkat lunak SQL (elemen 1)

| Elemen | Bukti |
|---|---|
| 1.1 Memasang | MariaDB 10.4.32 dari paket XAMPP 8.2.12; versi diperiksa `SELECT VERSION()` → `10.4.32-MariaDB` |
| 1.2 Menjalankan | Layanan `mysqld` aktif; klien `D:\xampp\mysql\bin\mysql.exe`; skema dibuat oleh `php spark migrate --all` (5 migrasi, masing-masing berpasangan `up()`/`down()`) |

Skema (InnoDB, `utf8mb4`, PK `BIGINT UNSIGNED AUTO_INCREMENT`, FK antar tabel) didefinisikan di `app/Database/Migrations/*InitialSchema.php`; objek SQL di `*DatabaseObjects.php` dan disalin ke `database/sql/` agar dapat dicetak.

## 2. Fitur DML (elemen 2)

| Elemen | Bukti pada `A07-hasil-eksekusi.txt` |
|---|---|
| 2.1 Mengidentifikasi fitur DML | `INSERT`, `UPDATE`, `DELETE`, `SELECT` dengan `JOIN`, `CALL`, transaksi |
| 2.2 Mengeksekusi sesuai kebutuhan | Langkah 1: `INSERT` 3 pengguna + 1 kategori (hitungan 3 dan 1 sesudah), `UPDATE categories` (deskripsi berubah); langkah 10: `DELETE` pemohon ditolak FK karena masih punya tiket (`ERROR 1451 … tickets_ibfk_1`); langkah 12: `DELETE` pembersihan (sisa 0) |

## 3. Mengisi tabel, index, view (elemen 3)

| Elemen | Bukti |
|---|---|
| 3.1 Mengisi tabel dengan DML | Langkah 1–2: baris pengguna, kategori, tiket; `DemoSeeder` mengisi 10 pengguna/6 kategori/100 tiket/200 komentar |
| 3.2 Membangkitkan index | `idx_status_priority`, `idx_sla_due`, `ft_title` (migrasi `PerformanceIndexes`), `idx_active_list` (migrasi v1.1). Langkah 11: `EXPLAIN` memakai `idx_status_priority` (`ref`), `idx_sla_due` (`range`), `ft_title` (`fulltext`) |
| 3.3 View | `v_ticket_summary`, `v_ticket_sla`, `v_ticket_sla_summary`, `v_ticket_by_category`, `v_ticket_by_day`, `v_dashboard` (`01_views.sql`). Langkah 6: `v_dashboard` tepat satu baris per status (`2 baris, 2 status unik → OK tanpa duplikasi`) |

## 4. Operasi relasional (elemen 4)

| Elemen | Bukti |
|---|---|
| 4.1 Fitur DML | JOIN, LEFT JOIN, agregasi `COUNT/SUM`, subquery |
| 4.2 Antar tabel | Langkah 5: `tickets ⨝ categories ⨝ users ⨝ users(p)` menampilkan kategori, pemohon, dan petugas (`NULL` bila belum ditugaskan) |
| 4.3 Antar view | Langkah 5: `v_ticket_summary a JOIN v_ticket_sla_summary b ON b.status = a.status`; `v_dashboard` sendiri adalah gabungan dua view |
| 4.4 Efisien | Agregat dilakukan di view (bukan di PHP); pencarian judul memakai FULLTEXT; filter status+prioritas memakai index gabungan; hasil `EXPLAIN` pada langkah 11; benchmark 100.000 tiket di A02/A08/A10 |

## 5. Stored procedure (elemen 5)

| Elemen | Bukti |
|---|---|
| 5.1 Membuat | `sp_close_ticket(p_ticket_id, p_user_id)` — `START TRANSACTION`, `UPDATE … WHERE status='Selesai'`, `SIGNAL` bila baris ≠ 1, `COMMIT`; `DECLARE EXIT HANDLER FOR SQLEXCEPTION … ROLLBACK; RESIGNAL`. `sp_officer_ranking(p_start, p_end)` |
| 5.2 Menguji input/output | Langkah 7: `CALL sp_close_ticket(<tiket Selesai>, admin)` → status `Ditutup`. Langkah 7b: panggilan pada tiket `Baru` → `Error 1644: Ticket must be Selesai`, status tetap `Baru` (handler melakukan rollback). Langkah 8: `CALL sp_officer_ranking('2026-01-01','2027-01-01')` → `Uji Petugas | 1` |

## 6. Function (elemen 6)

| Elemen | Bukti |
|---|---|
| 6.1 Membuat | `fn_sla_hours(p_priority)` membaca `settings` (`sla_<Prioritas>`), `fn_resolution_minutes(p_ticket_id)` |
| 6.2 Efisien | Satu `SELECT … LIMIT 1` pada kolom ber-unique (`settings.key`); `COALESCE(..., 24)` sebagai nilai bawaan. Langkah 3: `4/8/24/72` jam dan `24` untuk prioritas tak dikenal; setelah admin mengubah `sla_Kritis` ke 6, fungsi mengembalikan 6 dan tiket **baru** berprioritas Kritis mendapat SLA 6 jam (tiket lama tidak berubah) |

## 7. Trigger (elemen 7)

| Elemen | Bukti |
|---|---|
| 7.1 Mendefinisikan | `trg_ticket_before_insert` (mengisi `sla_due_at = created_at + fn_sla_hours`) dan `trg_ticket_status_log` (mencatat ke `ticket_logs`; menolak perubahan status tanpa `status_changed_by`) di `04_triggers.sql` |
| 7.2 Menguji kesesuaian | Langkah 2: Kritis → 4 jam, Rendah → 72 jam. Langkah 4: `UPDATE status` tanpa pelaku → `Error 1644: status_changed_by required`, status tetap `Baru`. Langkah 4b: dengan pelaku → dua baris `ticket_logs` (`Baru→Diproses`, `Diproses→Selesai`) beratribut petugas |

## 8. COMMIT dan ROLLBACK (elemen 8)

| Elemen | Bukti (langkah 9 dan 9b) |
|---|---|
| 8.1 COMMIT | `START TRANSACTION; UPDATE …; COMMIT;` → status `Diproses`, `jumlah_log = 1` setelah commit |
| 8.2 ROLLBACK | `START TRANSACTION; UPDATE …;` terlihat `Diproses` dan `jumlah_log = 1` **di dalam** transaksi; setelah `ROLLBACK` kembali `Baru` dan `jumlah_log = 0` (trigger ikut dibatalkan) |

Di sisi aplikasi, perubahan status, penulisan log trigger, dan pembuatan notifikasi berada dalam satu transaksi (`TicketService`, NF-09).

## 9. Batas klaim

Skrip dijalankan pada `tiket_test` dengan akun DBA. Akun aplikasi `tiket_app` tidak diberi DDL; pengujian hak ada di A08. `fn_resolution_minutes` bernilai 0 pada data uji karena tiket dibuat dan diselesaikan dalam menit yang sama; uji dengan selisih waktu nyata dilakukan lewat `TicketService` dan data demo.
