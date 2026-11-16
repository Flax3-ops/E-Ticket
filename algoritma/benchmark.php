<?php

declare(strict_types=1);

/**
 * Benchmark waktu dan memori untuk n = 100, 1.000, 10.000 (Unit 9).
 * php algoritma/benchmark.php > docs/A09-benchmark.csv
 * Waktu: median 3 pengulangan (hrtime). Memori: puncak tambahan (memory_get_peak_usage) selama pemanggilan.
 */

require __DIR__ . '/tiket.php';

/** @return array{0: float, 1: float, 2: mixed} waktu ms (median), memori KB (puncak tambahan), hasil */
function ukur(callable $fn): array
{
    $times = [];
    $result = null;
    $memory = 0.0;
    for ($i = 0; $i < 3; $i++) {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $base = memory_get_usage();
        $start = hrtime(true);
        $result = $fn();
        $times[] = (hrtime(true) - $start) / 1e6;
        $memory = max($memory, (memory_get_peak_usage() - $base) / 1024);
    }
    sort($times);
    return [$times[1], $memory, $result];
}

echo "n,algoritma,waktu_ms,memori_kb\n";
foreach ([100, 1000, 10000] as $n) {
    $input = range(1, $n);
    mt_srand(42 + $n);
    shuffle($input);
    $sortedInput = range(1, $n);
    foreach (['bubbleSort', 'insertionSort', 'mergeSort'] as $algorithm) {
        [$time, $memory, $sorted] = ukur(static fn () => $algorithm($input));
        if ($sorted !== $sortedInput) {
            throw new RuntimeException($algorithm . ' menghasilkan urutan salah.');
        }
        echo implode(',', [$n, $algorithm, round($time, 3), round($memory, 1)]) . "\n";
    }
    $tickets = [];
    foreach ($input as $i) {
        $tickets[] = ['ticket_no' => sprintf('TKT-20260101-%05d', $i), 'priority' => array_keys(BOBOT_PRIORITAS)[$i % 4]];
    }
    [$time, $memory, $byPriority] = ukur(static fn () => urutkanTiketPerPrioritas($tickets));
    if ($byPriority[0]['priority'] !== 'Kritis' || $byPriority[$n - 1]['priority'] !== 'Rendah') {
        throw new RuntimeException('urutkanTiketPerPrioritas salah.');
    }
    echo implode(',', [$n, 'urutkanTiketPerPrioritas(mergeSort)', round($time, 3), round($memory, 1)]) . "\n";

    $target = sprintf('TKT-20260101-%05d', $n);
    $sortedTickets = $tickets;
    usort($sortedTickets, static fn ($a, $b) => strcmp($a['ticket_no'], $b['ticket_no']));
    // Pencarian sangat cepat: ulangi 1.000 kali agar terukur, lalu bagi.
    foreach (['cariNomorLinear' => $tickets, 'cariNomorBiner' => $sortedTickets] as $fn => $list) {
        [$time, , $index] = ukur(static function () use ($fn, $list, $target) {
            $found = -1;
            for ($r = 0; $r < 1000; $r++) {
                $found = $fn($list, $target);
            }
            return $found;
        });
        if ($index < 0 || $list[$index]['ticket_no'] !== $target) {
            throw new RuntimeException($fn . ' gagal menemukan target.');
        }
        echo implode(',', [$n, $fn, round($time / 1000, 5), 0]) . "\n";
    }
}
