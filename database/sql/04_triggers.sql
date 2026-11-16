DELIMITER //
DROP TRIGGER IF EXISTS trg_ticket_before_insert//
CREATE TRIGGER trg_ticket_before_insert BEFORE INSERT ON tickets FOR EACH ROW
BEGIN
    SET NEW.sla_due_at=DATE_ADD(NEW.created_at, INTERVAL fn_sla_hours(NEW.priority) HOUR);
END//
DROP TRIGGER IF EXISTS trg_ticket_status_log//
CREATE TRIGGER trg_ticket_status_log AFTER UPDATE ON tickets FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        IF NEW.status_changed_by IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='status_changed_by required'; END IF;
        INSERT INTO ticket_logs(ticket_id,old_status,new_status,changed_by,changed_at,created_at,updated_at)
        VALUES(NEW.id,OLD.status,NEW.status,NEW.status_changed_by,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP());
    END IF;
END//
DELIMITER ;
