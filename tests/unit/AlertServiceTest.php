<?php

use App\Libraries\Resource\ResourceReaderInterface;
use App\Services\AlertService;
use App\Services\MonitorService;
use CodeIgniter\Test\CIUnitTestCase;

final class AlertServiceTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect();
        $this->db->transBegin();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();
        parent::tearDown();
    }

    public function testRepeatedAlertAndTwoNormalChecksResolveIt(): void
    {
        $reader = new class implements ResourceReaderInterface {
            public float $cpu = 95;

            public function read(): array
            {
                return ['cpu_pct' => $this->cpu, 'ram_used_mb' => 100, 'ram_total_mb' => 1000, 'disk_used_gb' => 1, 'disk_total_gb' => 10];
            }
        };
        $service = new AlertService($this->db, new MonitorService($this->db, $reader));
        $service->check();
        $service->check();
        $active = $this->db->table('alerts')->where('active_key', 'resource:cpu')->get()->getRowArray();
        $this->assertNotNull($active);
        $this->assertSame(2, (int) $active['occurrences']);
        $reader->cpu = 20;
        $service->check();
        $this->assertNull($this->db->table('alerts')->where('id', $active['id'])->get()->getRow('resolved_at'));
        $service->check();
        $resolved = $this->db->table('alerts')->where('id', $active['id'])->get()->getRowArray();
        $this->assertNotNull($resolved['resolved_at']);
        $this->assertNull($resolved['active_key']);
    }
}
