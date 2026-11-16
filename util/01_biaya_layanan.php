<?php

declare(strict_types=1);

/** Menghitung tarif setelah diskon bertingkat berdasarkan jumlah layanan. */
function hitungBiaya(int $jumlah, int $tarif): int
{
    $subtotal = $jumlah * $tarif;
    $diskon = $jumlah >= 10 ? 20 : ($jumlah >= 5 ? 10 : 0);
    return (int) ($subtotal * (100 - $diskon) / 100);
}

/** Membaca bilangan bulat dari keyboard dan mengulang bila tidak valid. */
function bacaBilangan(string $label): int
{
    do {
        echo $label;
        $input = trim((string) fgets(STDIN));
    } while (! ctype_digit($input));
    return (int) $input;
}

$jumlah = bacaBilangan('Jumlah layanan: ');
$tarif = bacaBilangan('Tarif per layanan: ');
echo 'Total: Rp' . number_format(hitungBiaya($jumlah, $tarif), 0, ',', '.') . PHP_EOL;
