<?php

declare(strict_types=1);

// Array satu dimensi, tepat lima elemen string prioritas.
$prioritas = ['Tinggi', 'Rendah', 'Kritis', 'Sedang', 'Tinggi'];
$bobot = ['Rendah' => 1, 'Sedang' => 2, 'Tinggi' => 3, 'Kritis' => 4];
usort($prioritas, static fn (string $a, string $b): int => $bobot[$b] <=> $bobot[$a]);
echo 'Urutan prioritas: ' . implode(', ', $prioritas) . PHP_EOL;
echo 'Jumlah elemen: ' . count($prioritas) . PHP_EOL;
