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

    public function testReminderIsThrottledToOncePerFifteenMinutes(): void
    {
        $reader = new class implements ResourceReaderInterface {
            public function read(): array
            {
                return ['cpu_pct' => 99, 'ram_used_mb' => 100, 'ram_total_mb' => 1000, 'disk_used_gb' => 1, 'disk_total_gb' => 10];
            }
        };
        $service = new AlertService($this->db, new MonitorService($this->db, $reader));
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('users')->insert(['name' => 'Admin Uji', 'email' => uniqid('adm-', true) . '@local.invalid', 'password_hash' => 'x', 'role' => 'admin', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $admin = (int) $this->db->insertID();
        $count = fn (): int => $this->db->table('notifications')->where('user_id', $admin)->like('message', 'CPU melewati')->countAllResults();
        $before = $count();
        $service->check();
        $service->check();
        $service->check();
        $this->assertSame($before + 1, $count(), 'Tiga pemeriksaan berturut-turut hanya boleh mengirim satu notifikasi.');
        $this->db->table('alerts')->where('active_key', 'resource:cpu')->update(['last_notified_at' => gmdate('Y-m-d H:i:s', time() - 901)]);
        $service->check();
        $this->assertSame($before + 2, $count(), 'Setelah 15 menit pengingat boleh dikirim lagi.');
        $this->assertSame(1, $this->db->table('alerts')->where('rule_code', 'resource')->where('object_key', 'cpu')->where('resolved_at', null)->countAllResults(), 'Hanya satu alert aktif per rule+objek.');
    }

    public function testUnavailableReaderDoesNotUseSimulatedValuesAndRecovers(): void
    {
        $reader = new class implements ResourceReaderInterface {
            public bool $fail = true;

            public function read(): array
            {
                if ($this->fail) {
                    throw new \RuntimeException('simulasi gagal baca');
                }
                return ['cpu_pct' => 10, 'ram_used_mb' => 100, 'ram_total_mb' => 1000, 'disk_used_gb' => 1, 'disk_total_gb' => 10];
            }
        };
        $service = new AlertService($this->db, new MonitorService($this->db, $reader));
        $service->check();
        $this->assertNotNull($this->db->table('alerts')->where('active_key', 'reader:os')->get()->getRowArray());
        $this->assertNull($this->db->table('alerts')->where('active_key', 'resource:cpu')->get()->getRowArray(), 'Metrik tidak tersedia tidak boleh dievaluasi.');
        $reader->fail = false;
        $service->check();
        $service->check();
        $this->assertNull($this->db->table('alerts')->where('active_key', 'reader:os')->get()->getRowArray(), 'Alert pembaca pulih setelah dua pemeriksaan normal.');
    }
}
