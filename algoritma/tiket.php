<?php

declare(strict_types=1);

require_once __DIR__ . '/algorithms.php';

/** Bobot prioritas: nilai lebih kecil = lebih mendesak (dipakai sebagai kunci urut). */
const BOBOT_PRIORITAS = ['Kritis' => 0, 'Tinggi' => 1, 'Sedang' => 2, 'Rendah' => 3];

/**
 * Bubble sort. Invarian: setelah putaran luar ke-k, k elemen terbesar sudah berada di ujung kanan
 * pada posisi akhirnya. Varian: batas dalam (n-1-k) turun tiap putaran. Berhenti lebih awal bila
 * satu putaran tanpa pertukaran. Waktu O(n²) (terbaik O(n)), memori tambahan O(1).
 *
 * @param list<int> $items
 * @return list<int>
 */
function bubbleSort(array $items): array
{
    for ($k = 0, $n = count($items); $k < $n - 1; $k++) {
        $swapped = false;
        for ($i = 0; $i < $n - 1 - $k; $i++) {
            if ($items[$i] > $items[$i + 1]) {
                [$items[$i], $items[$i + 1]] = [$items[$i + 1], $items[$i]];
                $swapped = true;
            }
        }
        if (! $swapped) {
            break;
        }
    }
    return $items;
}

/**
 * Mengurutkan tiket berdasarkan prioritas (Kritis dulu), lalu nomor tiket, memakai merge sort
 * atas kunci gabungan [bobot, nomor]. Stabil terhadap input; waktu O(n log n), memori O(n).
 *
 * @param list<array{ticket_no: string, priority: string}> $tickets
 * @return list<array{ticket_no: string, priority: string}>
 */
function urutkanTiketPerPrioritas(array $tickets): array
{
    if (count($tickets) < 2) {
        return $tickets;
    }
    $mid = intdiv(count($tickets), 2);
    $left = urutkanTiketPerPrioritas(array_slice($tickets, 0, $mid));
    $right = urutkanTiketPerPrioritas(array_slice($tickets, $mid));
    $merged = [];
    $i = $j = 0;
    while ($i < count($left) && $j < count($right)) {
        $merged[] = kunciTiket($left[$i]) <= kunciTiket($right[$j]) ? $left[$i++] : $right[$j++];
    }
    return array_merge($merged, array_slice($left, $i), array_slice($right, $j));
}

/** @param array{ticket_no: string, priority: string} $ticket */
function kunciTiket(array $ticket): string
{
    return BOBOT_PRIORITAS[$ticket['priority']] . '|' . $ticket['ticket_no'];
}

/**
 * Pencarian linear nomor tiket pada daftar belum terurut. Waktu O(n), memori O(1).
 *
 * @param list<array{ticket_no: string}> $tickets
 */
function cariNomorLinear(array $tickets, string $nomor): int
{
    foreach ($tickets as $index => $ticket) {
        if ($ticket['ticket_no'] === $nomor) {
            return $index;
        }
    }
    return -1;
}

/**
 * Pencarian biner nomor tiket pada daftar yang terurut menaik menurut ticket_no.
 * Invarian: bila ada, nomor berada di [low, high]. Varian: high-low+1 mengecil. Waktu O(log n).
 *
 * @param list<array{ticket_no: string}> $sorted
 */
function cariNomorBiner(array $sorted, string $nomor): int
{
    $low = 0;
    $high = count($sorted) - 1;
    while ($low <= $high) {
        $mid = intdiv($low + $high, 2);
        $cmp = strcmp($sorted[$mid]['ticket_no'], $nomor);
        if ($cmp === 0) {
            return $mid;
        }
        if ($cmp < 0) {
            $low = $mid + 1;
        } else {
            $high = $mid - 1;
        }
    }
    return -1;
}
