-- Jalankan pada database uji. Set @ticket_id ke ID tiket Selesai yang ada.
SET @ticket_id = (SELECT id FROM tickets WHERE status='Selesai' LIMIT 1);
SET @actor_id = (SELECT id FROM users WHERE role='admin' LIMIT 1);
SELECT id,status FROM tickets WHERE id=@ticket_id;
START TRANSACTION;
UPDATE tickets SET status='Ditutup',status_changed_by=@actor_id WHERE id=@ticket_id;
SELECT id,status FROM tickets WHERE id=@ticket_id;
ROLLBACK;
SELECT id,status FROM tickets WHERE id=@ticket_id;
-- Ulangi perubahan yang sama dengan COMMIT setelah memastikan data uji dapat diubah.
