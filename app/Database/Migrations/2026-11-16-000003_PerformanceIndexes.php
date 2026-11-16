<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class PerformanceIndexes extends Migration
{
    public function up()
    {
        $this->db->query('CREATE INDEX idx_status_priority ON tickets (status, priority)');
        $this->db->query('CREATE INDEX idx_sla_due ON tickets (sla_due_at)');
        $this->db->query('CREATE FULLTEXT INDEX ft_title ON tickets (title)');
    }

    public function down()
    {
        $this->db->query('DROP INDEX ft_title ON tickets');
        $this->db->query('DROP INDEX idx_sla_due ON tickets');
        $this->db->query('DROP INDEX idx_status_priority ON tickets');
    }
}
