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

    /**
     * Snapshot untuk endpoint /monitor/metrics: hasil dipakai ulang selama $ttl detik.
     * Pembacaan OS di Windows memanggil PowerShell/CIM (mahal); tanpa cache, 50 sesi yang
     * me-refresh grafik tiap 5 detik atau uji `ab -c 50` menjenuhkan CPU (lihat A14/A16).
     * Kunci berkas (flock) membuat hanya satu permintaan yang membaca OS; yang lain memakai hasilnya.
     */
    public function snapshot(int $ttl = 3): array
    {
        $dir = WRITEPATH . 'cache';
        $file = $dir . DIRECTORY_SEPARATOR . 'monitor_snapshot.json';
        $lock = fopen($dir . DIRECTORY_SEPARATOR . 'monitor_snapshot.lock', 'c');
        if ($lock === false) {
            return $this->collect();
        }
        flock($lock, LOCK_EX);
        try {
            if (is_file($file) && time() - (int) filemtime($file) < $ttl) {
                $cached = json_decode((string) file_get_contents($file), true);
                if (is_array($cached)) {
                    return $cached;
                }
            }
            $values = $this->collect();
            file_put_contents($file, json_encode($values));
            return $values;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
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
