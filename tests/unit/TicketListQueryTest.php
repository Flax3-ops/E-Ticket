<?php

use App\Models\TicketListQuery;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Skenario fungsi modul daftar/pencarian/ekspor tiket (Unit 10: kesetaraan fungsi).
 * Skenario yang sama dijalankan terhadap implementasi raw SQL (v0.9) dan Query Builder (v1.0).
 */
final class TicketListQueryTest extends CIUnitTestCase
{
    private int $userA;
    private int $userB;
    private int $category;
    private int $category2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect();
        $this->db->transBegin();
        $now = gmdate('Y-m-d H:i:s');
        foreach (['userA', 'userB'] as $key) {
            $this->db->table('users')->insert(['name' => 'Uji ' . $key, 'email' => uniqid($key, true) . '@local.invalid', 'password_hash' => 'x', 'role' => 'pemohon', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $this->{$key} = (int) $this->db->insertID();
        }
        foreach (['category', 'category2'] as $key) {
            $this->db->table('categories')->insert(['name' => 'Kat ' . uniqid(), 'created_at' => $now, 'updated_at' => $now]);
            $this->{$key} = (int) $this->db->insertID();
        }
        $rows = [
            ['A-1', 'printer lantai dua rusak', $this->userA, $this->category, 'Tinggi', 'Baru'],
            ['A-2', 'jaringan wifi lambat sekali', $this->userA, $this->category2, 'Rendah', 'Selesai'],
            ['B-1', 'printer ruang rapat macet', $this->userB, $this->category, 'Kritis', 'Baru'],
            ['B-2', '100% kapasitas_disk penuh', $this->userB, $this->category2, 'Sedang', 'Baru'],
        ];
        foreach ($rows as $i => [$no, $title, $user, $cat, $prio, $status]) {
            $this->db->table('tickets')->insert(['ticket_no' => 'TKT-UJI' . getmypid() . '-' . $no, 'user_id' => $user, 'category_id' => $cat, 'title' => $title, 'description' => 'deskripsi', 'priority' => $prio, 'status' => $status, 'sla_due_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        }
        // tiket yang di-soft-delete tidak boleh muncul
        $this->db->table('tickets')->insert(['ticket_no' => 'TKT-UJI' . getmypid() . '-DEL', 'user_id' => $this->userA, 'category_id' => $this->category, 'title' => 'printer dihapus', 'description' => 'x', 'priority' => 'Tinggi', 'status' => 'Baru', 'sla_due_at' => $now, 'deleted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();
        parent::tearDown();
    }

    private function ids(array $result): array
    {
        return array_map(static fn ($r) => substr($r['ticket_no'], strlen('TKT-UJI' . getmypid() . '-')), $result['rows'] ?? $result);
    }

    private function scoped(array $filters, ?int $requester = null): array
    {
        // Batasi hasil pada tiket uji milik pengujian ini agar tidak terpengaruh data lain di DB uji.
        $query = new TicketListQuery($this->db);
        $all = $query->paginate($filters, $requester, 1, 1000)['rows'];
        return array_values(array_filter($all, static fn ($r) => str_starts_with($r['ticket_no'], 'TKT-UJI' . getmypid() . '-')));
    }

    public function testSoftDeletedAndScopeByRequester(): void
    {
        $this->assertEqualsCanonicalizing(['A-1', 'A-2', 'B-1', 'B-2'], $this->ids(['rows' => $this->scoped([])]));
        $this->assertEqualsCanonicalizing(['A-1', 'A-2'], $this->ids(['rows' => $this->scoped([], $this->userA)]));
    }

    public function testFiltersCombine(): void
    {
        $this->assertEqualsCanonicalizing(['A-1', 'B-1', 'B-2'], $this->ids(['rows' => $this->scoped(['status' => 'Baru'])]));
        $this->assertSame(['B-1'], $this->ids(['rows' => $this->scoped(['status' => 'Baru', 'priority' => 'Kritis'])]));
        $this->assertSame(['A-2'], $this->ids(['rows' => $this->scoped(['category_id' => (string) $this->category2, 'status' => 'Selesai'])]));
    }

    public function testShortSearchUsesLikeAndTicketNumberUsesPrefix(): void
    {
        $this->assertSame(['A-2'], $this->ids(['rows' => $this->scoped(['q' => 'wi'])]), 'dua karakter memakai LIKE %q%');
        $prefix = 'TKT-UJI' . getmypid() . '-B';
        $this->assertEqualsCanonicalizing(['B-1', 'B-2'], $this->ids(['rows' => $this->scoped(['q' => $prefix])]), 'awalan TKT- mencari nomor');
    }

    public function testLikeWildcardsAreEscaped(): void
    {
        $this->assertSame(['B-2'], $this->ids(['rows' => $this->scoped(['q' => '0%'])]));
        $this->assertSame([], $this->scoped(['q' => '%%']), '% harfiah tidak cocok semua');
        $this->assertSame(['B-2'], $this->ids(['rows' => $this->scoped(['q' => '_d'])]), '_ harfiah cocok "_d" pada kapasitas_disk');
        $this->assertSame([], $this->scoped(['q' => '_s']), '_ harfiah, bukan wildcard satu karakter');
    }

    public function testInjectionStringIsTreatedAsData(): void
    {
        $result = (new TicketListQuery($this->db))->paginate(['q' => "x') OR 1=1 -- ", 'status' => "Baru' OR '1'='1"], null, 1, 20);
        $this->assertSame(0, $result['total']);
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) AS n FROM categories WHERE id = ?', [$this->category])->getRow()->n, 'tabel tetap utuh');
    }

    public function testPaginationAndTotal(): void
    {
        $query = new TicketListQuery($this->db);
        $first = $query->paginate(['category_id' => (string) $this->category], null, 1, 1);
        $second = $query->paginate(['category_id' => (string) $this->category], null, 2, 1);
        $this->assertSame(2, $first['total']);
        $this->assertCount(1, $first['rows']);
        $this->assertCount(1, $second['rows']);
        $this->assertNotSame($first['rows'][0]['id'], $second['rows'][0]['id']);
        $this->assertGreaterThan($second['rows'][0]['id'], $first['rows'][0]['id'], 'urut id menurun');
        $this->assertArrayHasKey('category_name', $first['rows'][0]);
        $this->assertArrayHasKey('requester_name', $first['rows'][0]);
        $this->assertArrayHasKey('officer_name', $first['rows'][0]);
    }

    public function testExportColumnsAndFilters(): void
    {
        $rows = (new TicketListQuery($this->db))->export(['priority' => 'Kritis', 'status' => 'Baru']);
        $mine = array_values(array_filter($rows, static fn ($r) => str_starts_with($r['ticket_no'], 'TKT-UJI' . getmypid() . '-')));
        $this->assertCount(1, $mine);
        $this->assertSame(['ticket_no', 'title', 'priority', 'status', 'created_at', 'category_name'], array_keys($mine[0]));
    }
}
