<?php

// Versi setelah koreksi: titik koma yang hilang ditambahkan pada `return 20;` dan pada `echo ...;`.
declare(strict_types=1);

function hitungDiskon(int $jumlah): int
{
    if ($jumlah >= 10) {
        return 20;
    }
    return 0;
}

echo 'Diskon: ' . hitungDiskon(12) . "%\n";
