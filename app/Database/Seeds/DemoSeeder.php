<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run()
    {
        $now = gmdate('Y-m-d H:i:s');
        foreach (['Perangkat keras', 'Perangkat lunak', 'Jaringan', 'Akun dan akses', 'Fasilitas', 'Lainnya'] as $name) {
            if (! $this->db->table('categories')->where('name', $name)->countAllResults()) {
                $this->db->table('categories')->insert(['name' => $name, 'description' => 'Layanan ' . strtolower($name), 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $people = [['Administrator', 'admin@tiket.local', 'admin'], ['Petugas Satu', 'petugas@tiket.local', 'petugas'], ['Petugas Dua', 'petugas2@tiket.local', 'petugas'], ['Petugas Tiga', 'petugas3@tiket.local', 'petugas']];
        foreach (range(1, 6) as $number) {
            $people[] = ['Pemohon ' . $number, $number === 1 ? 'pemohon@tiket.local' : 'pemohon' . $number . '@tiket.local', 'pemohon'];
        }
        foreach ($people as [$name, $email, $role]) {
            if (! $this->db->table('users')->where('email', $email)->countAllResults()) {
                $this->db->table('users')->insert(['name' => $name, 'email' => $email, 'role' => $role, 'password_hash' => password_hash('Demo12345!', PASSWORD_BCRYPT), 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $count = $this->db->table('tickets')->countAllResults();
        $service = new \App\Services\TicketService($this->db);
        $categories = array_column($this->db->table('categories')->get()->getResultArray(), 'id');
        $requesters = array_column($this->db->table('users')->select('id')->where('role', 'pemohon')->get()->getResultArray(), 'id');
        $officers = array_column($this->db->table('users')->select('id')->where('role', 'petugas')->get()->getResultArray(), 'id');
        for ($i = $count + 1; $i <= 100; $i++) {
            $requesterId = (int) $requesters[$i % count($requesters)];
            $officerId = (int) $officers[$i % count($officers)];
            $id = $service->create($requesterId, ['title' => 'Permintaan layanan contoh ' . $i, 'description' => 'Data demonstrasi untuk menampilkan alur aplikasi helpdesk dan proses tindak lanjut.', 'category_id' => $categories[$i % count($categories)], 'priority' => ['Rendah', 'Sedang', 'Tinggi', 'Kritis'][$i % 4]]);
            if ($i % 3 === 0) {
                $service->take($id, $officerId);
                if ($i % 6 === 0) {
                    $service->changeStatus($id, $officerId, 'petugas', 'Selesai', 'Permintaan telah ditangani pada data demonstrasi.');
                }
            }
        }
        foreach ($this->db->table('tickets')->select('id,user_id,assigned_to')->get()->getResultArray() as $ticket) {
            $existing = $this->db->table('comments')->where('ticket_id', $ticket['id'])->countAllResults();
            for ($j = $existing; $j < 2; $j++) {
                $this->db->table('comments')->insert(['ticket_id' => $ticket['id'], 'user_id' => $j === 0 ? $ticket['user_id'] : ($ticket['assigned_to'] ?: $ticket['user_id']), 'body' => $j === 0 ? 'Mohon bantuan untuk permintaan ini.' : 'Permintaan sudah diterima dan akan ditindaklanjuti.', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $overdue = $this->db->table('tickets')->where('status', 'Baru')->orderBy('id')->limit(3)->get()->getResultArray();
        foreach ($overdue as $i => $ticket) {
            $past = gmdate('Y-m-d H:i:s', time() - (10 + $i) * 86400);
            $date = gmdate('Ymd', strtotime($past . ' UTC'));
            $hours = (int) $this->db->query('SELECT fn_sla_hours(?) AS h', [$ticket['priority']])->getRow('h');
            $this->db->table('tickets')->where('id', $ticket['id'])->update(['ticket_no' => 'TKT-' . $date . '-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'created_at' => $past, 'sla_due_at' => gmdate('Y-m-d H:i:s', strtotime($past . ' UTC') + $hours * 3600)]);
        }
    }
}
