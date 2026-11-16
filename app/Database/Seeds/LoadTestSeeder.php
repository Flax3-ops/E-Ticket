<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class LoadTestSeeder extends Seeder
{
    public function run()
    {
        $dbName = (string) $this->db->database;
        if (! str_contains(strtolower($dbName), 'loadtest')) {
            throw new RuntimeException('LoadTestSeeder hanya boleh berjalan pada database bernama *loadtest*.');
        }
        if ($this->db->table('tickets')->countAllResults()) {
            throw new RuntimeException('Database uji harus belum memiliki tiket.');
        }
        $count = max(1, min(1000000, (int) (getenv('LOADTEST_COUNT') ?: 100000)));
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('categories')->insert(['name' => 'Uji Beban', 'description' => 'Kategori data sintetis', 'created_at' => $now, 'updated_at' => $now]);
        $categoryId = (int) $this->db->insertID();
        $users = [];
        $officers = [];
        for ($i = 1; $i <= 200; $i++) {
            $role = $i <= 10 ? 'petugas' : 'pemohon';
            $this->db->table('users')->insert(['name' => 'Pengguna Uji ' . $i, 'email' => 'loadtest' . $i . '@tiket.local', 'password_hash' => password_hash('LoadTest123!', PASSWORD_BCRYPT), 'role' => $role, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            if ($role === 'petugas') {
                $officers[] = (int) $this->db->insertID();
            } else {
                $users[] = (int) $this->db->insertID();
            }
        }
        $rows = [];
        $statuses = ['Baru', 'Diproses', 'Selesai', 'Ditutup'];
        $priorities = ['Rendah', 'Sedang', 'Tinggi', 'Kritis'];
        for ($i = 0; $i < $count; $i++) {
            $day = intdiv($i, 900);
            $seq = $i % 900 + 1;
            $created = gmdate('Y-m-d H:i:s', strtotime('2026-01-01 UTC') + $day * 86400 + ($i % 86400));
            $status = $statuses[$i % 4];
            $priority = $priorities[$i % 4];
            $rows[] = [
                'ticket_no' => 'TKT-' . substr(str_replace('-', '', $created), 0, 8) . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'user_id' => $users[$i % count($users)],
                'category_id' => $categoryId,
                'assigned_to' => $status === 'Baru' ? null : $officers[$i % count($officers)],
                'title' => 'Uji beban tiket nomor ' . $i,
                'description' => 'Data sintetis untuk pengukuran index dan pencarian.',
                'priority' => $priority,
                'status' => $status,
                'sla_due_at' => $created,
                'resolved_at' => in_array($status, ['Selesai', 'Ditutup'], true) ? gmdate('Y-m-d H:i:s', strtotime($created . ' UTC') + 3600) : null,
                'created_at' => $created,
                'updated_at' => $created,
            ];
            if (count($rows) === 1000) {
                $this->db->table('tickets')->insertBatch($rows);
                $rows = [];
            }
        }
        if ($rows) {
            $this->db->table('tickets')->insertBatch($rows);
        }
    }
}
