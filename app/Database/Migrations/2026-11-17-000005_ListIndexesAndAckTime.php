<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Patch v1.1 (Unit 17).
 *
 * 1. idx_active_list (deleted_at, status, priority): hasil benchmark 100.000 tiket (A10/A02) menunjukkan
 *    COUNT(*) halaman daftar tiket membaca seluruh baris tabel (halaman pertama p50 ±2 detik). Indeks
 *    ini melayani hitungan tiket aktif berfilter hanya dari indeks (covering).
 * 2. alerts.acknowledged_at: menyimpan waktu acknowledge oleh admin (F-NTF-10).
 */
class ListIndexesAndAckTime extends Migration
{
    public function up()
    {
        $this->db->query('CREATE INDEX idx_active_list ON tickets (deleted_at, status, priority)');
        $this->db->query('ALTER TABLE alerts ADD COLUMN acknowledged_at DATETIME NULL AFTER acknowledged_by');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE alerts DROP COLUMN acknowledged_at');
        $this->db->query('DROP INDEX idx_active_list ON tickets');
    }
}
