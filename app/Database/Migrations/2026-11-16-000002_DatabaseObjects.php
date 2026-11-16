<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class DatabaseObjects extends Migration
{
    public function up()
    {
        $this->db->query("CREATE FUNCTION fn_sla_hours(p_priority VARCHAR(20)) RETURNS INT READS SQL DATA BEGIN DECLARE hours_value INT; SELECT CAST(`value` AS UNSIGNED) INTO hours_value FROM settings WHERE `key` = CONCAT('sla_', p_priority) LIMIT 1; RETURN COALESCE(hours_value, 24); END");
        $this->db->query("CREATE FUNCTION fn_resolution_minutes(p_ticket_id BIGINT UNSIGNED) RETURNS INT READS SQL DATA BEGIN DECLARE result_value INT; SELECT TIMESTAMPDIFF(MINUTE, created_at, resolved_at) INTO result_value FROM tickets WHERE id = p_ticket_id; RETURN result_value; END");
        $this->db->query("CREATE TRIGGER trg_ticket_before_insert BEFORE INSERT ON tickets FOR EACH ROW BEGIN SET NEW.sla_due_at = DATE_ADD(NEW.created_at, INTERVAL fn_sla_hours(NEW.priority) HOUR); END");
        $this->db->query("CREATE TRIGGER trg_ticket_status_log AFTER UPDATE ON tickets FOR EACH ROW BEGIN IF OLD.status <> NEW.status THEN IF NEW.status_changed_by IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'status_changed_by required'; END IF; INSERT INTO ticket_logs (ticket_id, old_status, new_status, changed_by, changed_at, created_at, updated_at) VALUES (NEW.id, OLD.status, NEW.status, NEW.status_changed_by, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP()); END IF; END");
        $this->db->query("CREATE VIEW v_ticket_summary AS SELECT status, COUNT(*) AS total FROM tickets WHERE deleted_at IS NULL GROUP BY status");
        $this->db->query("CREATE VIEW v_ticket_sla AS SELECT id, ticket_no, status, sla_due_at, TIMESTAMPDIFF(MINUTE, UTC_TIMESTAMP(), sla_due_at) AS minutes_remaining, CASE WHEN status IN ('Baru','Diproses') AND sla_due_at < UTC_TIMESTAMP() THEN 1 ELSE 0 END AS is_overdue FROM tickets WHERE deleted_at IS NULL");
        $this->db->query("CREATE VIEW v_ticket_sla_summary AS SELECT t.status, SUM(CASE WHEN s.is_overdue = 1 THEN 1 ELSE 0 END) AS overdue_total FROM tickets t JOIN v_ticket_sla s ON s.id = t.id WHERE t.deleted_at IS NULL GROUP BY t.status");
        $this->db->query("CREATE VIEW v_ticket_by_category AS SELECT c.id AS category_id, c.name AS category_name, COUNT(t.id) AS total FROM categories c LEFT JOIN tickets t ON t.category_id = c.id AND t.deleted_at IS NULL GROUP BY c.id, c.name");
        $this->db->query("CREATE VIEW v_ticket_by_day AS SELECT DATE(created_at) AS ticket_date, COUNT(*) AS total FROM tickets WHERE deleted_at IS NULL GROUP BY DATE(created_at)");
        $this->db->query("CREATE VIEW v_dashboard AS SELECT a.status, a.total, COALESCE(b.overdue_total, 0) AS overdue_total FROM v_ticket_summary a LEFT JOIN v_ticket_sla_summary b ON b.status = a.status");
        $this->db->query("CREATE PROCEDURE sp_close_ticket(IN p_ticket_id BIGINT UNSIGNED, IN p_user_id BIGINT UNSIGNED) BEGIN DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END; START TRANSACTION; UPDATE tickets SET status = 'Ditutup', status_changed_by = p_user_id, updated_at = UTC_TIMESTAMP() WHERE id = p_ticket_id AND status = 'Selesai'; IF ROW_COUNT() <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ticket must be Selesai'; END IF; COMMIT; END");
        $this->db->query("CREATE PROCEDURE sp_officer_ranking(IN p_start DATETIME, IN p_end DATETIME) BEGIN SELECT u.id, u.name, COUNT(t.id) AS total FROM users u LEFT JOIN tickets t ON t.assigned_to = u.id AND t.status IN ('Selesai','Ditutup') AND t.resolved_at BETWEEN p_start AND p_end AND t.deleted_at IS NULL WHERE u.role = 'petugas' GROUP BY u.id, u.name ORDER BY total DESC; END");
    }

    public function down()
    {
        foreach (['v_dashboard', 'v_ticket_by_day', 'v_ticket_by_category', 'v_ticket_sla_summary', 'v_ticket_sla', 'v_ticket_summary'] as $view) {
            $this->db->query('DROP VIEW IF EXISTS ' . $view);
        }
        foreach (['sp_officer_ranking', 'sp_close_ticket'] as $procedure) {
            $this->db->query('DROP PROCEDURE IF EXISTS ' . $procedure);
        }
        foreach (['trg_ticket_status_log', 'trg_ticket_before_insert'] as $trigger) {
            $this->db->query('DROP TRIGGER IF EXISTS ' . $trigger);
        }
        foreach (['fn_resolution_minutes', 'fn_sla_hours'] as $function) {
            $this->db->query('DROP FUNCTION IF EXISTS ' . $function);
        }
    }
}
