# PoC Unit 3

- Buka `grafik.html` secara lokal untuk membandingkan dua grafik dengan data yang sama. Chart.js dipilih karena API sederhana dan telah terintegrasi di dashboard; ApexCharts menjadi alternatif bila kebutuhan interaksi grafik berkembang.
- Jalankan `php ekspor.php` untuk membandingkan CSV manual dengan PhpSpreadsheet. CSV lebih kecil dan cepat tetapi tidak menyimpan format sel; XLSX dapat memberi judul tebal dan beberapa lembar kerja.
- Integrasi aplikasi memakai aset Chart.js lokal di `public/vendor/chartjs`, dan PhpSpreadsheet melalui Composer pada `Reports::tickets()`.
- Batasan: kedua grafik membutuhkan JavaScript; file XLSX lebih besar dan memerlukan ekstensi PHP `zip`, `xml`, dan `gd`.
