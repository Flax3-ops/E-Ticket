<?php

declare(strict_types=1);

/**
 * Benchmark modul App\Models\TicketListQuery pada database tiket_loadtest (100.000 tiket).
 * Dipakai untuk membandingkan implementasi raw SQL mysqli (v0.9) dan Query Builder (v1.0) — A10, NF-02.
 *
 * php database/benchmark_list_query.php <label> [--out docs/A10-benchmark-<label>.json]
 * Pemanasan 5 kali, lalu 30 pengulangan per skenario; dicatat p50, p95, maks (ms).
 */

// Bootstrap CI4 (konstanta path, autoload) memakai bootstrap uji bawaan framework; koneksi DB dibuat manual di bawah.
define('HOMEPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('CONFIGPATH', HOMEPATH . 'app/Config/');
define('PUBLICPATH', HOMEPATH . 'public' . DIRECTORY_SEPARATOR);
chdir(HOMEPATH);
require HOMEPATH . 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

$label = $argv[1] ?? 'tanpa-label';
$out = null;
foreach ($argv as $i => $a) {
    if ($a === '--out') {
        $out = $argv[$i + 1] ?? null;
    }
}
$db = \Config\Database::connect([
    'DBDriver' => 'MySQLi', 'hostname' => '127.0.0.1', 'username' => 'root', 'password' => '', 'database' => 'tiket_loadtest',
    'port' => 3306, 'charset' => 'utf8mb4', 'DBCollat' => 'utf8mb4_general_ci', 'DBDebug' => true,
]);
$query = new \App\Models\TicketListQuery($db);
$requesterId = (int) $db->query('SELECT user_id FROM tickets WHERE id = 500')->getRow()->user_id;

$scenarios = [
    'halaman_pertama' => static fn () => $query->paginate([], null, 1, 20),
    'filter_status_prioritas' => static fn () => $query->paginate(['status' => 'Baru', 'priority' => 'Rendah'], null, 1, 20),
    'cari_judul_fulltext' => static fn () => $query->paginate(['q' => '99999'], null, 1, 20),
    'cari_nomor_tiket' => static fn () => $query->paginate(['q' => 'TKT-20260101-05'], null, 1, 20),
    'halaman_dalam_(5000)' => static fn () => $query->paginate([], null, 5000, 20),
    'pemohon_sendiri' => static fn () => $query->paginate([], $requesterId, 1, 20),
    'ekspor_terfilter' => static fn () => $query->export(['status' => 'Selesai', 'priority' => 'Kritis']),
];
$report = ['label' => $label, 'php' => PHP_VERSION, 'diukur' => gmdate('c'), 'catatan' => 'pemanasan 5x, 30 pengulangan, database tiket_loadtest 100.000 tiket', 'skenario' => []];
foreach ($scenarios as $name => $run) {
    for ($i = 0; $i < 5; $i++) {
        $run();
    }
    $times = [];
    for ($i = 0; $i < 30; $i++) {
        $start = hrtime(true);
        $result = $run();
        $times[] = (hrtime(true) - $start) / 1e6;
    }
    sort($times);
    $count = isset($result['rows']) ? count($result['rows']) . ' baris/hal, total ' . $result['total'] : count($result) . ' baris';
    $report['skenario'][$name] = ['p50_ms' => round($times[14], 3), 'p95_ms' => round($times[28], 3), 'maks_ms' => round(end($times), 3), 'hasil' => $count];
    printf("%-26s p50 %8.3f  p95 %8.3f  maks %8.3f  (%s)\n", $name, $times[14], $times[28], end($times), $count);
}
if ($out) {
    file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}
