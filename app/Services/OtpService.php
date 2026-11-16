<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class OtpService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function issue(int $userId): string
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('otp_codes')->where('user_id', $userId)->where('used_at', null)->update(['used_at' => $now, 'updated_at' => $now]);
        $code = (string) random_int(100000, 999999);
        $this->db->table('otp_codes')->insert([
            'user_id' => $userId,
            'code_hash' => password_hash($code, PASSWORD_BCRYPT),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 300),
            'attempts' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $code;
    }

    public function verify(int $userId, string $code): bool
    {
        $row = $this->db->table('otp_codes')->where('user_id', $userId)->where('used_at', null)->orderBy('id', 'DESC')->get(1)->getRowArray();
        if (! $row || $row['expires_at'] < gmdate('Y-m-d H:i:s') || (int) $row['attempts'] >= 3) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');
        if (password_verify($code, $row['code_hash'])) {
            $this->db->table('otp_codes')->where('id', $row['id'])->update(['used_at' => $now, 'updated_at' => $now]);
            return true;
        }
        $attempts = (int) $row['attempts'] + 1;
        $this->db->table('otp_codes')->where('id', $row['id'])->update(['attempts' => $attempts, 'updated_at' => $now]);
        if ($attempts >= 3) {
            $this->db->table('users')->where('id', $userId)->update(['locked_until' => gmdate('Y-m-d H:i:s', time() + 600), 'updated_at' => $now]);
        }
        return false;
    }
}
