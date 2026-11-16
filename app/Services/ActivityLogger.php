<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class ActivityLogger
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function write(?int $userId, string $action, ?string $entity = null, ?int $entityId = null): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->db->table('activity_logs')->insert([
            'user_id' => $userId,
            'action' => $action,
            'entity' => $entity,
            'entity_id' => $entityId,
            'ip' => is_cli() ? '127.0.0.1' : service('request')->getIPAddress(),
            'user_agent' => is_cli() ? 'CLI' : substr((string) service('request')->getUserAgent(), 0, 255),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
