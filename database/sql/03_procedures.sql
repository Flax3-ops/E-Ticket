DELIMITER //
DROP PROCEDURE IF EXISTS sp_close_ticket//
CREATE PROCEDURE sp_close_ticket(IN p_ticket_id BIGINT UNSIGNED, IN p_user_id BIGINT UNSIGNED)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
    START TRANSACTION;
    UPDATE tickets SET status='Ditutup', status_changed_by=p_user_id, updated_at=UTC_TIMESTAMP()
    WHERE id=p_ticket_id AND status='Selesai';
    IF ROW_COUNT() <> 1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ticket must be Selesai'; END IF;
    COMMIT;
END//
DROP PROCEDURE IF EXISTS sp_officer_ranking//
CREATE PROCEDURE sp_officer_ranking(IN p_start DATETIME, IN p_end DATETIME)
BEGIN
    SELECT u.id, u.name, COUNT(t.id) AS total FROM users u
    LEFT JOIN tickets t ON t.assigned_to=u.id AND t.status IN ('Selesai','Ditutup')
      AND t.resolved_at BETWEEN p_start AND p_end AND t.deleted_at IS NULL
    WHERE u.role='petugas' GROUP BY u.id,u.name ORDER BY total DESC;
END//
DELIMITER ;
CALL sp_officer_ranking('2026-01-01', '2027-01-01');
