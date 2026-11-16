<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class TicketSequences extends Migration
{
    public function up()
    {
        $this->db->query('CREATE TABLE ticket_sequences (ticket_date DATE PRIMARY KEY, last_no INT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS ticket_sequences');
    }
}
