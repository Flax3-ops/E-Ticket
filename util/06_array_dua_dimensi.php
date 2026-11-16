<?php

declare(strict_types=1);

/**
 * Array dua dimensi: jumlah tiket per kategori (baris) x prioritas (kolom).
 * Dimensi : 2 (matriks 3 baris x 4 kolom).
 * Tipe    : seluruh elemen bertipe int; label baris/kolom bertipe string.
 * Panjang : ditentukan dari count() dan dicatat pada keluaran.
 */
$kolom = ['Rendah', 'Sedang', 'Tinggi', 'Kritis'];
$matriks = [
    'Jaringan' => [3, 5, 2, 1],
    'Perangkat keras' => [7, 4, 6, 0],
    'Akun dan akses' => [2, 9, 1, 3],
];

/** Menjumlahkan satu baris matriks. */
function jumlahBaris(array $baris): int
{
    return array_sum($baris);
}

/** Mengurutkan kategori dari jumlah tiket terbanyak (pengurutan array dua dimensi). */
function urutkanKategori(array $matriks): array
{
    uasort($matriks, static fn (array $a, array $b): int => jumlahBaris($b) <=> jumlahBaris($a));
    return $matriks;
}

printf("Dimensi: 2, baris: %d, kolom: %d\n", count($matriks), count($kolom));
printf("%-18s%s%s\n", 'Kategori', implode('', array_map(static fn (string $k): string => str_pad($k, 9), $kolom)), 'Jumlah');
foreach (urutkanKategori($matriks) as $kategori => $baris) {
    printf("%-18s%s%d\n", $kategori, implode('', array_map(static fn (int $n): string => str_pad((string) $n, 9), $baris)), jumlahBaris($baris));
}
