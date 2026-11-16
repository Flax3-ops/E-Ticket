<?php

namespace App\Libraries\Resource;

class LinuxResourceReader implements ResourceReaderInterface
{
    public function read(): array
    {
        $mem = @file_get_contents('/proc/meminfo');
        if ($mem === false || ! preg_match('/MemTotal:\s+(\d+)/', $mem, $total) || ! preg_match('/MemAvailable:\s+(\d+)/', $mem, $available)) {
            throw new \RuntimeException('Metrik Linux tidak tersedia.');
        }
        $diskTotal = disk_total_space(ROOTPATH);
        $diskFree = disk_free_space(ROOTPATH);
        $load = sys_getloadavg();
        return [
            'cpu_pct' => $load === false ? null : min(100.0, round($load[0] / max(1, (int) shell_exec('nproc')) * 100, 2)),
            'ram_used_mb' => (int) (($total[1] - $available[1]) / 1024),
            'ram_total_mb' => (int) ($total[1] / 1024),
            'disk_used_gb' => $diskTotal === false || $diskFree === false ? null : round(($diskTotal - $diskFree) / 1073741824, 2),
            'disk_total_gb' => $diskTotal === false ? null : round($diskTotal / 1073741824, 2),
        ];
    }
}
