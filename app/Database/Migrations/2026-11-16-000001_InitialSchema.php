<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class InitialSchema extends Migration
{
    public function up()
    {
        $sql = [
            "CREATE TABLE users (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role ENUM('pemohon','petugas','admin') NOT NULL DEFAULT 'pemohon', is_active TINYINT(1) NOT NULL DEFAULT 1, locked_until DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_role (role)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE otp_codes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, code_hash VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL, attempts TINYINT UNSIGNED NOT NULL DEFAULT 0, used_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id), INDEX idx_otp_user (user_id, used_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE categories (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE, description TEXT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE settings (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, `key` VARCHAR(80) NOT NULL UNIQUE, `value` VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE tickets (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_no VARCHAR(32) NOT NULL UNIQUE, user_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL, assigned_to BIGINT UNSIGNED NULL, status_changed_by BIGINT UNSIGNED NULL, title VARCHAR(200) NOT NULL, description TEXT NOT NULL, priority ENUM('Rendah','Sedang','Tinggi','Kritis') NOT NULL, status ENUM('Baru','Diproses','Selesai','Ditutup') NOT NULL DEFAULT 'Baru', sla_due_at DATETIME NOT NULL, resolved_at DATETIME NULL, deleted_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (category_id) REFERENCES categories(id), FOREIGN KEY (assigned_to) REFERENCES users(id), FOREIGN KEY (status_changed_by) REFERENCES users(id), INDEX idx_user (user_id), INDEX idx_assigned (assigned_to)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE ticket_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, old_status VARCHAR(20) NOT NULL, new_status VARCHAR(20) NOT NULL, changed_by BIGINT UNSIGNED NOT NULL, changed_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (ticket_id) REFERENCES tickets(id), FOREIGN KEY (changed_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE comments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, body TEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (ticket_id) REFERENCES tickets(id), FOREIGN KEY (user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE attachments (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, ticket_id BIGINT UNSIGNED NOT NULL, original_name VARCHAR(255) NOT NULL, stored_name VARCHAR(255) NOT NULL, mime VARCHAR(100) NOT NULL, size INT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (ticket_id) REFERENCES tickets(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE notifications (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, type VARCHAR(50) NOT NULL, message VARCHAR(255) NOT NULL, link VARCHAR(255) NULL, read_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id), INDEX idx_user_read (user_id, read_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE alerts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, rule_code VARCHAR(50) NOT NULL, object_key VARCHAR(100) NOT NULL, active_key VARCHAR(180) NULL UNIQUE, level VARCHAR(20) NOT NULL DEFAULT 'critical', message VARCHAR(255) NOT NULL, occurrences INT UNSIGNED NOT NULL DEFAULT 1, first_seen_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, last_notified_at DATETIME NULL, normal_checks TINYINT UNSIGNED NOT NULL DEFAULT 0, resolved_at DATETIME NULL, acknowledged_by BIGINT UNSIGNED NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (acknowledged_by) REFERENCES users(id), INDEX idx_alert_active (resolved_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE resource_metrics (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, cpu_pct DECIMAL(5,2) NULL, ram_used_mb BIGINT NULL, ram_total_mb BIGINT NULL, disk_used_gb DECIMAL(12,2) NULL, disk_total_gb DECIMAL(12,2) NULL, db_connections INT NULL, db_max_connections INT NULL, query_ms DECIMAL(10,2) NULL, php_memory_mb DECIMAL(10,2) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_created_at (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE activity_logs (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL, action VARCHAR(80) NOT NULL, entity VARCHAR(80) NULL, entity_id BIGINT UNSIGNED NULL, ip VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id), INDEX idx_created_at (created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($sql as $query) {
            $this->db->query($query);
        }
        $now = gmdate('Y-m-d H:i:s');
        foreach (['Kritis' => 4, 'Tinggi' => 8, 'Sedang' => 24, 'Rendah' => 72] as $key => $value) {
            $this->db->table('settings')->insert(['key' => 'sla_' . $key, 'value' => (string) $value, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (['cpu_limit' => 85, 'ram_limit' => 85, 'disk_limit' => 90, 'db_limit' => 80] as $key => $value) {
            $this->db->table('settings')->insert(['key' => $key, 'value' => (string) $value, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down()
    {
        foreach (['activity_logs', 'resource_metrics', 'alerts', 'notifications', 'attachments', 'comments', 'ticket_logs', 'tickets', 'settings', 'categories', 'otp_codes', 'users'] as $table) {
            $this->db->query('DROP TABLE IF EXISTS ' . $table);
        }
    }
}
