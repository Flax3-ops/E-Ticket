# A03 — Library dan PoC

`poc/grafik.html` menampilkan data yang sama dengan Chart.js dan ApexCharts. Chart.js dipakai pada dashboard karena integrasi sederhana dan aset lokal kecil. `php poc/ekspor.php` mengukur contoh 3 baris: CSV 1,61 ms/42 byte; XLSX 222,39 ms/6.182 byte pada pengukuran lokal. XLSX dipilih untuk format sel dan kemungkinan beberapa lembar kerja. CSV tetap menjadi alternatif untuk data besar dan tanpa format. Integrasi melalui `public/vendor/chartjs` dan Composer; limitasi ada di `poc/README.md`. Uji manfaat visual dua grafik perlu direkam di browser asesmen.
