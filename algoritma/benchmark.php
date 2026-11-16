<?php

declare(strict_types=1);

require __DIR__ . '/algorithms.php';

echo "n,algoritma,waktu_ms,memori_kb\n";
foreach ([100, 1000, 10000] as $n) {
    $input = range(1, $n);
    shuffle($input);
    foreach (['insertionSort', 'mergeSort'] as $algorithm) {
        $before = memory_get_usage(true);
        $start = hrtime(true);
        $sorted = $algorithm($input);
        $time = (hrtime(true) - $start) / 1e6;
        $memory = (memory_get_usage(true) - $before) / 1024;
        if ($sorted !== range(1, $n)) {
            throw new RuntimeException($algorithm . ' menghasilkan urutan salah.');
        }
        echo implode(',', [$n, $algorithm, round($time, 3), $memory]) . "\n";
    }
    foreach (['linearSearch', 'binarySearch'] as $algorithm) {
        $start = hrtime(true);
        $index = $algorithm(range(1, $n), $n);
        if ($index !== $n - 1) {
            throw new RuntimeException($algorithm . ' gagal menemukan target.');
        }
        echo implode(',', [$n, $algorithm, round((hrtime(true) - $start) / 1e6, 3), 0]) . "\n";
    }
}
