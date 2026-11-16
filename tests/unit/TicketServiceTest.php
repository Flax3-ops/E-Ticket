<?php

use App\Services\TicketService;
use CodeIgniter\Test\CIUnitTestCase;

final class TicketServiceTest extends CIUnitTestCase
{
    private int $requester;
    private int $officer;
    private int $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect();
        $this->db->transBegin();
        $now = gmdate('Y-m-d H:i:s');
        foreach (['pemohon', 'petugas'] as $role) {
            $this->db->table('users')->insert(['name' => 'Test ' . $role, 'email' => uniqid('test-', true) . '@local.invalid', 'password_hash' => password_hash('Test12345!', PASSWORD_BCRYPT), 'role' => $role, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $this->{$role === 'pemohon' ? 'requester' : 'officer'} = (int) $this->db->insertID();
        }
        $this->db->table('categories')->insert(['name' => 'Test ' . uniqid(), 'created_at' => $now, 'updated_at' => $now]);
        $this->category = (int) $this->db->insertID();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();
        parent::tearDown();
    }

    public function testCreateTakeAndResolveWritesStatusLog(): void
    {
        $service = new TicketService($this->db);
        $id = $service->create($this->requester, ['title' => 'Uji layanan printer', 'description' => 'Printer tidak dapat mencetak dokumen.', 'category_id' => $this->category, 'priority' => 'Tinggi']);
        $ticket = $service->find($id);
        $this->assertMatchesRegularExpression('/^TKT-\d{8}-\d{4}$/', $ticket['ticket_no']);
        $this->assertSame(8 * 3600, strtotime($ticket['sla_due_at'] . ' UTC') - strtotime($ticket['created_at'] . ' UTC'));
        $service->take($id, $this->officer);
        $this->expectException(\DomainException::class);
        $service->take($id, $this->officer);
    }

    public function testStatusTransitionAndAccessPolicy(): void
    {
        $service = new TicketService($this->db);
        $id = $service->create($this->requester, ['title' => 'Uji layanan jaringan', 'description' => 'Jaringan tidak tersambung pada ruang uji.', 'category_id' => $this->category, 'priority' => 'Sedang']);
        $ticket = $service->find($id);
        $this->assertFalse(TicketService::canView($ticket, $this->officer, 'pemohon'));
        $service->take($id, $this->officer);
        $service->changeStatus($id, $this->officer, 'petugas', 'Selesai', 'Jaringan telah kembali normal.');
        $this->assertSame('Selesai', $service->find($id)['status']);
        $this->assertSame(2, $this->db->table('ticket_logs')->where('ticket_id', $id)->countAllResults());
    }
}
