<?php

declare(strict_types=1);

$sumber = $argv[1] ?? __DIR__ . '/contoh-tiket.csv';
$tujuan = $argv[2] ?? __DIR__ . '/ringkasan.txt';
$file = fopen($sumber, 'rb');
if ($file === false) {
    fwrite(STDERR, "CSV tidak dapat dibaca.\n");
    exit(1);
}
$header = fgetcsv($file);
$jumlah = [];
while (($row = fgetcsv($file)) !== false) {
    $prioritas = $row[2] ?? 'Tidak diketahui';
    $jumlah[$prioritas] = ($jumlah[$prioritas] ?? 0) + 1;
}
fclose($file);
ksort($jumlah);
$lines = ["Ringkasan prioritas tiket"];
foreach ($jumlah as $prioritas => $total) {
    $lines[] = $prioritas . ': ' . $total;
}
file_put_contents($tujuan, implode(PHP_EOL, $lines) . PHP_EOL);
echo 'Tersimpan ke ' . $tujuan . PHP_EOL;
