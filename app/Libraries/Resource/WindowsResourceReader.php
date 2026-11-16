<?php

namespace App\Libraries\Resource;

class WindowsResourceReader implements ResourceReaderInterface
{
    public function read(): array
    {
        $drive = strtoupper(substr(ROOTPATH, 0, 1));
        if (! ctype_alpha($drive)) {
            throw new \RuntimeException('Drive aplikasi tidak diketahui.');
        }
        $script = '$o=Get-CimInstance Win32_OperatingSystem;' .
            '$c=Get-CimInstance Win32_Processor | Measure-Object LoadPercentage -Average;' .
            '$d=Get-CimInstance Win32_LogicalDisk -Filter "DeviceID=\'' . $drive . ':\'";' .
            '[pscustomobject]@{cpu=$c.Average;ram_total=$o.TotalVisibleMemorySize;ram_free=$o.FreePhysicalMemory;disk_total=$d.Size;disk_free=$d.FreeSpace}|ConvertTo-Json -Compress';
        // -EncodedCommand (Base64 UTF-16LE): escapeshellarg() di Windows membuang tanda kutip ganda dan merusak skrip
        // (A11 bug 15: CPU/RAM selalu "Tidak tersedia" walau CIM berfungsi).
        $encoded = base64_encode((string) mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
        $output = shell_exec('powershell -NoProfile -NonInteractive -EncodedCommand ' . $encoded . ' 2>NUL');
        $data = json_decode((string) $output, true);
        if (! is_array($data) || ! is_numeric($data['ram_total'] ?? null) || ! is_numeric($data['disk_total'] ?? null)) {
            throw new \RuntimeException('Metrik Windows tidak tersedia.');
        }
        return [
            'cpu_pct' => isset($data['cpu']) ? (float) $data['cpu'] : null,
            'ram_used_mb' => (int) (($data['ram_total'] - $data['ram_free']) / 1024),
            'ram_total_mb' => (int) ($data['ram_total'] / 1024),
            'disk_used_gb' => round(($data['disk_total'] - $data['disk_free']) / 1073741824, 2),
            'disk_total_gb' => round($data['disk_total'] / 1073741824, 2),
        ];
    }
}
