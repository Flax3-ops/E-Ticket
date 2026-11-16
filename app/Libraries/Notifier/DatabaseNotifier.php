<?php

namespace App\Libraries\Notifier;

use CodeIgniter\Database\BaseConnection;

class DatabaseNotifier implements NotifierInterface
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function notify(int $userId, string $type, string $message, ?string $link = null): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('notifications')->insert([
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'link' => $link,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
