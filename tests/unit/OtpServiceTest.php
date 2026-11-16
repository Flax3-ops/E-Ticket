<?php

use App\Services\OtpService;
use CodeIgniter\Test\CIUnitTestCase;

final class OtpServiceTest extends CIUnitTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = db_connect();
        $this->db->transBegin();
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('users')->insert(['name' => 'OTP Test', 'email' => uniqid('otp-', true) . '@local.invalid', 'password_hash' => password_hash('Test12345!', PASSWORD_BCRYPT), 'role' => 'pemohon', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        $this->userId = (int) $this->db->insertID();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();
        parent::tearDown();
    }

    public function testOneTimeCodeExpiresAndInvalidatesOldCode(): void
    {
        $service = new OtpService($this->db);
        $old = $service->issue($this->userId);
        $new = $service->issue($this->userId);
        $this->assertFalse($service->verify($this->userId, $old));
        $this->assertTrue($service->verify($this->userId, $new));
        $this->assertFalse($service->verify($this->userId, $new));
        $expired = $service->issue($this->userId);
        $this->db->table('otp_codes')->where('user_id', $this->userId)->where('used_at', null)->update(['expires_at' => gmdate('Y-m-d H:i:s', time() - 1)]);
        $this->assertFalse($service->verify($this->userId, $expired));
    }
}
