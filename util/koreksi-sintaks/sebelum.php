<?php

// LATIHAN KOREKSI SINTAKS (Unit 5 elemen 6, Unit 6 elemen 5).
// Berkas ini SENGAJA berisi dua kesalahan sintaks yang umum terjadi, dibuat oleh penulis sebagai
// latihan; bukan kesalahan yang muncul tanpa sengaja. Hasil `php -l` ada di hasil-php-l.txt.
declare(strict_types=1);

function hitungDiskon(int $jumlah): int
{
    if ($jumlah >= 10) {
        return 20
    }
    return 0;
}

echo 'Diskon: ' . hitungDiskon(12) . "%\n"
