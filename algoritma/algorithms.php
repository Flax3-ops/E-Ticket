<?php

declare(strict_types=1);

/**
 * Insertion sort menaik berdasarkan bobot prioritas.
 * Invarian: sebelum iterasi i, elemen indeks 0..i-1 sudah terurut.
 * Varian: n-i berkurang setiap iterasi luar sampai nol.
 * Waktu O(n²), memori tambahan O(1).
 *
 * @param list<int> $items
 * @return list<int>
 */
function insertionSort(array $items): array
{
    for ($i = 1, $n = count($items); $i < $n; $i++) {
        $value = $items[$i];
        $j = $i - 1;
        while ($j >= 0 && $items[$j] > $value) {
            $items[$j + 1] = $items[$j];
            $j--;
        }
        $items[$j + 1] = $value;
    }
    return $items;
}

/**
 * Merge sort. Invarian merge: output selalu terurut dan berisi elemen
 * yang sudah diambil dari kedua bagian. Waktu O(n log n), memori O(n).
 *
 * @param list<int> $items
 * @return list<int>
 */
function mergeSort(array $items): array
{
    if (count($items) < 2) {
        return $items;
    }
    $mid = intdiv(count($items), 2);
    $left = mergeSort(array_slice($items, 0, $mid));
    $right = mergeSort(array_slice($items, $mid));
    $merged = [];
    $i = 0;
    $j = 0;
    while ($i < count($left) && $j < count($right)) {
        $merged[] = $left[$i] <= $right[$j] ? $left[$i++] : $right[$j++];
    }
    return array_merge($merged, array_slice($left, $i), array_slice($right, $j));
}

/** @param list<int> $items Waktu O(n), memori O(1). */
function linearSearch(array $items, int $target): int
{
    foreach ($items as $index => $value) {
        if ($value === $target) {
            return $index;
        }
    }
    return -1;
}

/**
 * Array harus terurut. Invarian: jika target ada, posisinya di [low, high].
 * Varian: high-low+1 mengecil tiap iterasi. Waktu O(log n), memori O(1).
 *
 * @param list<int> $items
 */
function binarySearch(array $items, int $target): int
{
    $low = 0;
    $high = count($items) - 1;
    while ($low <= $high) {
        $mid = intdiv($low + $high, 2);
        if ($items[$mid] === $target) {
            return $mid;
        }
        if ($items[$mid] < $target) {
            $low = $mid + 1;
        } else {
            $high = $mid - 1;
        }
    }
    return -1;
}
