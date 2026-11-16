-- Index ini sudah dipasang oleh migrasi PerformanceIndexes. Untuk EXPLAIN:
EXPLAIN SELECT id FROM tickets WHERE status='Baru' AND priority='Kritis';
EXPLAIN SELECT id FROM tickets WHERE sla_due_at < UTC_TIMESTAMP() AND status IN ('Baru','Diproses');
EXPLAIN SELECT id FROM tickets WHERE MATCH(title) AGAINST ('printer' IN NATURAL LANGUAGE MODE);
-- Di database uji sebelum migrasi v1.1, jalankan:
-- CREATE INDEX idx_status_priority ON tickets(status, priority);
-- CREATE INDEX idx_sla_due ON tickets(sla_due_at);
-- CREATE FULLTEXT INDEX ft_title ON tickets(title);
