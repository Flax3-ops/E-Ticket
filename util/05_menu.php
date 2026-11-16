<?php

declare(strict_types=1);

/** Menampilkan menu sampai pengguna memilih keluar. */
function tampilMenu(): void
{
    do {
        echo "1. Salam\n2. Hitung contoh SLA\n0. Keluar\nPilihan: ";
        $pilihan = trim((string) fgets(STDIN));
        switch ($pilihan) {
            case '1':
                echo "Selamat datang di Tiket Layanan.\n";
                break;
            case '2':
                echo "Prioritas Tinggi: " . jamContoh('Tinggi') . " jam.\n";
                break;
            case '0':
                break;
            default:
                echo "Pilihan tidak dikenal.\n";
        }
    } while ($pilihan !== '0');
}

/** Memberi durasi contoh berdasarkan prioritas. */
function jamContoh(string $prioritas): int
{
    return $prioritas === 'Tinggi' ? 8 : 24;
}

tampilMenu();
