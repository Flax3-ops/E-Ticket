<?php

namespace App\Services;

use App\Libraries\Resource\LinuxResourceReader;
use App\Libraries\Resource\ResourceReaderInterface;
use App\Libraries\Resource\WindowsResourceReader;
use CodeIgniter\Database\BaseConnection;

class MonitorService
{
    public function __construct(private readonly BaseConnection $db, private readonly ?ResourceReaderInterface $reader = null)
    {
    }

    public function collect(bool $save = false): array
    {
        $reader = $this->reader ?? (PHP_OS_FAMILY === 'Windows' ? new WindowsResourceReader() : new LinuxResourceReader());
        try {
            $os = $reader->read();
            $status = 'ok';
        } catch (\Throwable $e) {
            log_message('error', 'E-MON-001 {message}', ['message' => $e->getMessage()]);
            $diskTotal = disk_total_space(ROOTPATH);
            $diskFree = disk_free_space(ROOTPATH);
            $os = ['cpu_pct' => null, 'ram_used_mb' => null, 'ram_total_mb' => null, 'disk_used_gb' => $diskTotal !== false && $diskFree !== false ? round(($diskTotal - $diskFree) / 1073741824, 2) : null, 'disk_total_gb' => $diskTotal !== false ? round($diskTotal / 1073741824, 2) : null];
            $status = 'unavailable';
        }
        $start = microtime(true);
        $this->db->query('SELECT 1');
        $queryMs = round((microtime(true) - $start) * 1000, 2);
        $connections = (int) $this->db->query("SHOW STATUS LIKE 'Threads_connected'")->getRowArray()['Value'];
        $max = (int) $this->db->query("SHOW VARIABLES LIKE 'max_connections'")->getRowArray()['Value'];
        $values = $os + [
            'db_connections' => $connections,
            'db_max_connections' => $max,
            'query_ms' => $queryMs,
            'php_memory_mb' => round(memory_get_usage(true) / 1048576, 2),
        ];
        $values['status'] = $status;
        if ($save) {
            $now = gmdate('Y-m-d H:i:s');
            $row = $values;
            unset($row['status']);
            $this->db->table('resource_metrics')->insert($row + ['created_at' => $now, 'updated_at' => $now]);
        }
        return $values;
    }
}
