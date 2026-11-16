DELIMITER //
DROP FUNCTION IF EXISTS fn_sla_hours//
CREATE FUNCTION fn_sla_hours(p_priority VARCHAR(20)) RETURNS INT READS SQL DATA
BEGIN
    DECLARE hours_value INT;
    SELECT CAST(`value` AS UNSIGNED) INTO hours_value FROM settings WHERE `key` = CONCAT('sla_', p_priority) LIMIT 1;
    RETURN COALESCE(hours_value, 24);
END//
DROP FUNCTION IF EXISTS fn_resolution_minutes//
CREATE FUNCTION fn_resolution_minutes(p_ticket_id BIGINT UNSIGNED) RETURNS INT READS SQL DATA
BEGIN
    DECLARE result_value INT;
    SELECT TIMESTAMPDIFF(MINUTE, created_at, resolved_at) INTO result_value FROM tickets WHERE id = p_ticket_id;
    RETURN result_value;
END//
DELIMITER ;
SELECT fn_sla_hours('Kritis') AS kritis_hours;
