<?php

declare(strict_types=1);

/** Mengubah prioritas menjadi durasi jam kalender. */
function jamSla(string $prioritas): int
{
    return ['Kritis' => 4, 'Tinggi' => 8, 'Sedang' => 24, 'Rendah' => 72][$prioritas] ?? 24;
}

echo 'Prioritas (Kritis/Tinggi/Sedang/Rendah): ';
$prioritas = ucfirst(strtolower(trim((string) fgets(STDIN))));
echo 'Tanggal mulai UTC (YYYY-MM-DD HH:MM:SS): ';
$mulai = trim((string) fgets(STDIN));
$waktu = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $mulai, new DateTimeZone('UTC'));
if (! $waktu) {
    fwrite(STDERR, "Tanggal tidak valid.\n");
    exit(1);
}
echo 'Tenggat: ' . $waktu->modify('+' . jamSla($prioritas) . ' hours')->format('Y-m-d H:i:s') . " UTC\n";
