-- Skrip uji objek SQL (Unit 7 dan 8) — dijalankan pada DATABASE UJI `tiket_test`, bukan tiket_demo.
--   mysql -uroot -t --force --show-warnings < database/sql/08_uji_objek.sql > docs/A07-hasil-eksekusi.txt
-- `--force` melanjutkan skrip setelah galat yang MEMANG DIHARAPKAN (ditandai "HARAPAN: GAGAL"); galat itu
-- tercatat pada keluaran sebagai bukti trigger/procedure menolak data tidak sah.
-- Data uji berprefiks 'sqltest-' dan dibersihkan di akhir.

USE tiket_test;
SET @now = UTC_TIMESTAMP();
DELETE FROM ticket_logs WHERE ticket_id IN (SELECT id FROM tickets WHERE ticket_no LIKE 'sqltest-%');
DELETE FROM tickets WHERE ticket_no LIKE 'sqltest-%';
DELETE FROM users WHERE email LIKE 'sqltest-%';
DELETE FROM categories WHERE name LIKE 'sqltest-%';
UPDATE settings SET `value` = '4' WHERE `key` = 'sla_Kritis';

SELECT '== 1. DML dasar: INSERT / UPDATE / DELETE dengan data sebelum dan sesudah ==' AS langkah;
INSERT INTO users (name, email, password_hash, role, is_active, created_at, updated_at) VALUES
  ('Uji Pemohon', 'sqltest-pemohon@local.invalid', 'x', 'pemohon', 1, @now, @now),
  ('Uji Petugas', 'sqltest-petugas@local.invalid', 'x', 'petugas', 1, @now, @now),
  ('Uji Admin',   'sqltest-admin@local.invalid',   'x', 'admin',   1, @now, @now);
INSERT INTO categories (name, description, created_at, updated_at) VALUES ('sqltest-Jaringan', 'kategori uji', @now, @now);
SET @pemohon = (SELECT id FROM users WHERE email = 'sqltest-pemohon@local.invalid');
SET @petugas = (SELECT id FROM users WHERE email = 'sqltest-petugas@local.invalid');
SET @admin   = (SELECT id FROM users WHERE email = 'sqltest-admin@local.invalid');
SET @kat     = (SELECT id FROM categories WHERE name = 'sqltest-Jaringan');
SELECT 'INSERT users/categories' AS aksi, (SELECT COUNT(*) FROM users WHERE email LIKE 'sqltest-%') AS users_uji, (SELECT COUNT(*) FROM categories WHERE name LIKE 'sqltest-%') AS kategori_uji;
UPDATE categories SET description = 'kategori uji diubah' WHERE id = @kat;
SELECT 'UPDATE categories' AS aksi, description FROM categories WHERE id = @kat;

SELECT '== 2. Trigger trg_ticket_before_insert: sla_due_at dihitung dari created_at + fn_sla_hours ==' AS langkah;
INSERT INTO tickets (ticket_no, user_id, category_id, title, description, priority, status, sla_due_at, created_at, updated_at)
VALUES ('sqltest-0001', @pemohon, @kat, 'Uji trigger SLA kritis', 'deskripsi', 'Kritis', 'Baru', @now, @now, @now),
       ('sqltest-0002', @pemohon, @kat, 'Uji trigger SLA rendah', 'deskripsi', 'Rendah', 'Baru', @now, @now, @now);
SELECT ticket_no, priority, created_at, sla_due_at, TIMESTAMPDIFF(HOUR, created_at, sla_due_at) AS jam_sla FROM tickets WHERE ticket_no LIKE 'sqltest-%' ORDER BY ticket_no;

SELECT '== 3. Function fn_sla_hours membaca settings; ubah nilai -> tiket berikutnya mengikuti ==' AS langkah;
SELECT fn_sla_hours('Kritis') AS kritis, fn_sla_hours('Tinggi') AS tinggi, fn_sla_hours('Sedang') AS sedang, fn_sla_hours('Rendah') AS rendah, fn_sla_hours('TidakAda') AS prioritas_tak_dikenal_default;
UPDATE settings SET `value` = '6' WHERE `key` = 'sla_Kritis';
SELECT fn_sla_hours('Kritis') AS kritis_setelah_diubah_admin;
INSERT INTO tickets (ticket_no, user_id, category_id, title, description, priority, status, sla_due_at, created_at, updated_at)
VALUES ('sqltest-0003', @pemohon, @kat, 'Uji SLA setelah pengaturan diubah', 'deskripsi', 'Kritis', 'Baru', @now, @now, @now);
SELECT ticket_no, TIMESTAMPDIFF(HOUR, created_at, sla_due_at) AS jam_sla_tiket_baru FROM tickets WHERE ticket_no = 'sqltest-0003';
UPDATE settings SET `value` = '4' WHERE `key` = 'sla_Kritis';

SELECT '== 4. Trigger trg_ticket_status_log: tanpa pelaku DITOLAK (HARAPAN: GAGAL) ==' AS langkah;
UPDATE tickets SET status = 'Diproses', assigned_to = @petugas WHERE ticket_no = 'sqltest-0001';
SELECT ticket_no, status FROM tickets WHERE ticket_no = 'sqltest-0001';

SELECT '== 4b. Dengan status_changed_by -> baris ticket_logs tercatat ==' AS langkah;
UPDATE tickets SET status = 'Diproses', assigned_to = @petugas, status_changed_by = @petugas WHERE ticket_no = 'sqltest-0001';
UPDATE tickets SET status = 'Selesai', status_changed_by = @petugas, resolved_at = UTC_TIMESTAMP() WHERE ticket_no = 'sqltest-0001';
SELECT t.ticket_no, l.old_status, l.new_status, l.changed_by = @petugas AS oleh_petugas FROM ticket_logs l JOIN tickets t ON t.id = l.ticket_id WHERE t.ticket_no = 'sqltest-0001' ORDER BY l.id;

SELECT '== 5. JOIN antar tabel dan JOIN antar dua view ==' AS langkah;
SELECT t.ticket_no, c.name AS kategori, u.name AS pemohon, p.name AS petugas FROM tickets t JOIN categories c ON c.id = t.category_id JOIN users u ON u.id = t.user_id LEFT JOIN users p ON p.id = t.assigned_to WHERE t.ticket_no LIKE 'sqltest-%' ORDER BY t.ticket_no;
SELECT a.status, a.total, b.overdue_total FROM v_ticket_summary a JOIN v_ticket_sla_summary b ON b.status = a.status ORDER BY a.status;

SELECT '== 6. View gabungan v_dashboard: tepat satu baris per status ==' AS langkah;
SELECT status, total, overdue_total FROM v_dashboard ORDER BY status;
SELECT COUNT(*) AS baris, COUNT(DISTINCT status) AS status_unik, IF(COUNT(*) = COUNT(DISTINCT status), 'OK tanpa duplikasi', 'DUPLIKAT') AS hasil FROM v_dashboard;

SELECT '== 7. Procedure sp_close_ticket: sukses (Selesai -> Ditutup) ==' AS langkah;
CALL sp_close_ticket((SELECT id FROM tickets WHERE ticket_no = 'sqltest-0001'), @admin);
SELECT ticket_no, status FROM tickets WHERE ticket_no = 'sqltest-0001';
SELECT '== 7b. sp_close_ticket pada tiket yang BUKAN Selesai: ROLLBACK oleh EXIT HANDLER (HARAPAN: GAGAL) ==' AS langkah;
CALL sp_close_ticket((SELECT id FROM tickets WHERE ticket_no = 'sqltest-0002'), @admin);
SELECT ticket_no, status AS status_tetap FROM tickets WHERE ticket_no = 'sqltest-0002';

SELECT '== 8. Procedure sp_officer_ranking(start, end) ==' AS langkah;
CALL sp_officer_ranking('2026-01-01', '2027-01-01');
SELECT fn_resolution_minutes((SELECT id FROM tickets WHERE ticket_no = 'sqltest-0001')) AS menit_penyelesaian;

SELECT '== 9. Transaksi: ROLLBACK membatalkan perubahan ==' AS langkah;
SELECT ticket_no, status AS sebelum FROM tickets WHERE ticket_no = 'sqltest-0002';
START TRANSACTION;
UPDATE tickets SET status = 'Diproses', assigned_to = @petugas, status_changed_by = @petugas WHERE ticket_no = 'sqltest-0002';
SELECT ticket_no, status AS di_dalam_transaksi, (SELECT COUNT(*) FROM ticket_logs WHERE ticket_id = tickets.id) AS jumlah_log FROM tickets WHERE ticket_no = 'sqltest-0002';
ROLLBACK;
SELECT ticket_no, status AS sesudah_rollback, (SELECT COUNT(*) FROM ticket_logs WHERE ticket_id = tickets.id) AS jumlah_log FROM tickets WHERE ticket_no = 'sqltest-0002';

SELECT '== 9b. Transaksi: COMMIT menyimpan perubahan ==' AS langkah;
START TRANSACTION;
UPDATE tickets SET status = 'Diproses', assigned_to = @petugas, status_changed_by = @petugas WHERE ticket_no = 'sqltest-0002';
COMMIT;
SELECT ticket_no, status AS sesudah_commit, (SELECT COUNT(*) FROM ticket_logs WHERE ticket_id = tickets.id) AS jumlah_log FROM tickets WHERE ticket_no = 'sqltest-0002';

SELECT '== 10. DELETE data uji (dan bukti FK menahan penghapusan induk) ==' AS langkah;
DELETE FROM users WHERE email = 'sqltest-pemohon@local.invalid';
SELECT COUNT(*) AS pemohon_masih_ada_karena_fk FROM users WHERE email = 'sqltest-pemohon@local.invalid';

SELECT '== 11. EXPLAIN query utama (index v1.1) ==' AS langkah;
EXPLAIN SELECT id FROM tickets WHERE status = 'Baru' AND priority = 'Kritis';
EXPLAIN SELECT id FROM tickets WHERE sla_due_at < UTC_TIMESTAMP() AND status IN ('Baru', 'Diproses');
EXPLAIN SELECT id FROM tickets WHERE MATCH(title) AGAINST ('printer' IN NATURAL LANGUAGE MODE);

SELECT '== 12. Pembersihan data uji ==' AS langkah;
DELETE FROM ticket_logs WHERE ticket_id IN (SELECT id FROM tickets WHERE ticket_no LIKE 'sqltest-%');
DELETE FROM tickets WHERE ticket_no LIKE 'sqltest-%';
DELETE FROM users WHERE email LIKE 'sqltest-%';
DELETE FROM categories WHERE name LIKE 'sqltest-%';
SELECT (SELECT COUNT(*) FROM tickets WHERE ticket_no LIKE 'sqltest-%') AS tiket_uji_tersisa, (SELECT COUNT(*) FROM users WHERE email LIKE 'sqltest-%') AS user_uji_tersisa;
