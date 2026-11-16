-- Ganti password contoh sebelum digunakan. Jalankan sebagai DBA, bukan akun aplikasi.
CREATE USER IF NOT EXISTS 'tiket_app'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_INI';
-- Hak per tabel menjaga akun aplikasi tanpa CREATE/ALTER/DROP.
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.users TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.otp_codes TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.categories TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.settings TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.tickets TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.ticket_sequences TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.ticket_logs TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.comments TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.attachments TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.notifications TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.alerts TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.resource_metrics TO 'tiket_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON tiket_demo.activity_logs TO 'tiket_app'@'localhost';
GRANT SELECT ON tiket_demo.v_ticket_summary TO 'tiket_app'@'localhost';
GRANT SELECT ON tiket_demo.v_ticket_sla TO 'tiket_app'@'localhost';
GRANT SELECT ON tiket_demo.v_ticket_sla_summary TO 'tiket_app'@'localhost';
GRANT SELECT ON tiket_demo.v_ticket_by_category TO 'tiket_app'@'localhost';
GRANT SELECT ON tiket_demo.v_ticket_by_day TO 'tiket_app'@'localhost';
GRANT SELECT ON tiket_demo.v_dashboard TO 'tiket_app'@'localhost';
GRANT EXECUTE ON FUNCTION tiket_demo.fn_sla_hours TO 'tiket_app'@'localhost';
GRANT EXECUTE ON FUNCTION tiket_demo.fn_resolution_minutes TO 'tiket_app'@'localhost';
GRANT EXECUTE ON PROCEDURE tiket_demo.sp_close_ticket TO 'tiket_app'@'localhost';
GRANT EXECUTE ON PROCEDURE tiket_demo.sp_officer_ranking TO 'tiket_app'@'localhost';
SHOW GRANTS FOR 'tiket_app'@'localhost';
-- Contoh REVOKE pada database uji: berikan dulu hak sementara, lalu cabut kembali.
-- GRANT CREATE ON tiket_loadtest.* TO 'tiket_app'@'localhost';
-- REVOKE CREATE ON tiket_loadtest.* FROM 'tiket_app'@'localhost';
