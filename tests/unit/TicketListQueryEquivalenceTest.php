<?php

use App\Models\TicketListQuery;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\Legacy\RawTicketListQuery;

/**
 * Unit 10: bukti kesetaraan fungsi. Implementasi lama (raw SQL mysqli, tag v0.9) dan baru
 * (Query Builder, v1.0) harus menghasilkan baris dan total yang identik untuk skenario yang sama.
 */
final class TicketListQueryEquivalenceTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect();
        $this->db->transBegin();
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('users')->insert(['name' => 'Eq A', 'email' => uniqid('eq', true) . '@local.invalid', 'password_hash' => 'x', 'role' => 'pemohon', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $user = (int) $this->db->insertID();
        $this->db->table('categories')->insert(['name' => 'EqKat ' . uniqid(), 'created_at' => $now, 'updated_at' => $now]);
        $cat = (int) $this->db->insertID();
        $titles = ['printer lantai dua rusak', 'jaringan wifi lambat sekali', 'printer ruang rapat macet', '100% kapasitas_disk penuh', "tanda kutip ' dan \" ganda", 'aplikasi absensi error login'];
        $priorities = ['Rendah', 'Sedang', 'Tinggi', 'Kritis'];
        $statuses = ['Baru', 'Diproses', 'Selesai', 'Ditutup'];
        foreach ($titles as $i => $title) {
            $this->db->table('tickets')->insert(['ticket_no' => 'TKT-EQ' . getmypid() . '-' . $i, 'user_id' => $user, 'category_id' => $cat, 'title' => $title, 'description' => 'd', 'priority' => $priorities[$i % 4], 'status' => $statuses[$i % 4], 'sla_due_at' => $now, 'deleted_at' => $i === 5 ? $now : null, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();
        parent::tearDown();
    }

    public function testPaginateEqualForAllScenarios(): void
    {
        $new = new TicketListQuery($this->db);
        $old = new RawTicketListQuery($this->db);
        $requester = (int) $this->db->query('SELECT user_id FROM tickets ORDER BY id DESC LIMIT 1')->getRow()->user_id;
        $scenarios = [
            [[], null, 1, 20], [[], $requester, 1, 3], [[], $requester, 2, 3], [['status' => 'Baru'], null, 1, 20],
            [['priority' => 'Kritis', 'status' => 'Baru'], null, 1, 20], [['q' => 'wi'], null, 1, 20], [['q' => '0%'], null, 1, 20],
            [['q' => '_d'], null, 1, 20], [['q' => 'printer'], null, 1, 20], [['q' => 'TKT-EQ' . getmypid()], null, 1, 20],
            [['q' => "x') OR 1=1 -- "], null, 1, 20], [['q' => "'"], null, 1, 20], [['category_id' => '999999'], null, 1, 20], [[], null, 9999, 20],
        ];
        foreach ($scenarios as $i => [$filters, $req, $page, $per]) {
            $this->assertEquals($old->paginate($filters, $req, $page, $per), $new->paginate($filters, $req, $page, $per), 'Skenario #' . $i . ' ' . json_encode($filters));
        }
    }

    public function testExportEqual(): void
    {
        $new = new TicketListQuery($this->db);
        $old = new RawTicketListQuery($this->db);
        foreach ([[], ['status' => 'Baru'], ['priority' => 'Tinggi', 'status' => 'Selesai'], ['q' => 'printer'], ['q' => 'ap']] as $filters) {
            $this->assertEquals($old->export($filters), $new->export($filters), json_encode($filters));
        }
    }
}
