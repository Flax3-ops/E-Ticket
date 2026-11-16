# A09 — Algoritma

`algoritma/algorithms.php` berisi insertion sort O(n²) waktu/O(1) memori tambahan, merge sort O(n log n)/O(n), linear search O(n)/O(1), binary search O(log n)/O(1). Invarian dan varian loop dijelaskan dalam komentar; flowchart dan pseudocode alur status ada pada `algoritma/README.md`.

Hasil `php algoritma/benchmark.php` pada n=10.000: insertion sort 2.772,904 ms; merge sort 44,661 ms; linear search 0,848 ms; binary search 0,048 ms. Pengukuran memori alokasi PHP untuk merge sort tercatat 2.048 KB, sementara metode lain nol karena granularitas allocator. Uji PHPUnit memeriksa hasil pengurutan dan pencarian.
