-- Jalankan setelah migrasi tabel. Definisi operasional ada di migrasi DatabaseObjects.
CREATE OR REPLACE VIEW v_ticket_summary AS
SELECT status, COUNT(*) AS total FROM tickets WHERE deleted_at IS NULL GROUP BY status;

CREATE OR REPLACE VIEW v_ticket_sla AS
SELECT id, ticket_no, status, sla_due_at,
       TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), sla_due_at) AS minutes_remaining,
       CASE WHEN status IN ('Baru','Diproses') AND sla_due_at < UTC_TIMESTAMP() THEN 1 ELSE 0 END AS is_overdue
FROM tickets WHERE deleted_at IS NULL;

CREATE OR REPLACE VIEW v_ticket_sla_summary AS
SELECT t.status, SUM(CASE WHEN s.is_overdue = 1 THEN 1 ELSE 0 END) AS overdue_total
FROM tickets t JOIN v_ticket_sla s ON s.id = t.id WHERE t.deleted_at IS NULL GROUP BY t.status;

CREATE OR REPLACE VIEW v_ticket_by_category AS
SELECT c.id AS category_id, c.name AS category_name, COUNT(t.id) AS total
FROM categories c LEFT JOIN tickets t ON t.category_id = c.id AND t.deleted_at IS NULL
GROUP BY c.id, c.name;

CREATE OR REPLACE VIEW v_ticket_by_day AS
SELECT DATE(created_at) AS ticket_date, COUNT(*) AS total
FROM tickets WHERE deleted_at IS NULL GROUP BY DATE(created_at);

CREATE OR REPLACE VIEW v_dashboard AS
SELECT a.status, a.total, COALESCE(b.overdue_total, 0) AS overdue_total
FROM v_ticket_summary a LEFT JOIN v_ticket_sla_summary b ON b.status = a.status;

-- DML/relasional antar tabel dan antar view:
SELECT t.ticket_no, c.name FROM tickets t JOIN categories c ON c.id = t.category_id LIMIT 10;
SELECT a.status, a.total, b.overdue_total FROM v_ticket_summary a JOIN v_ticket_sla_summary b ON b.status = a.status;
