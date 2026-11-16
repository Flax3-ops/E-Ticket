# A03 — Library, komponen, dan framework (Unit 3, J.620100.003.01)

Acuan: SRS §3.3, `poc/`. Semua angka berasal dari eksekusi lokal pada 16–17 November 2026 (PHP 8.2.12, Windows 11).

## 1. Ruang lingkup kebutuhan (elemen 1.1)

| Kebutuhan fungsional | Lingkungan | Komponen yang dibutuhkan |
|---|---|---|
| MVC, routing, filter, migrasi, CLI | PHP 8.2 / XAMPP | Framework PHP |
| Grafik dashboard (donut, line, bar) dan slideshow | Browser, tanpa build tool | Pustaka grafik JS |
| Ekspor tiket ke Excel | Server PHP | Pustaka spreadsheet |
| Laporan bulanan PDF | Server PHP | Pustaka HTML→PDF |
| Notifikasi dalam web, slideshow, sorting/searching | Browser dan server | Komponen sederhana |

## 2. Pakai library atau buat sendiri (elemen 1.2)

| Komponen | Library (keuntungan) | Buat sendiri (kerugian) | Keputusan |
|---|---|---|---|
| Framework web | CI4 4.7.6: routing, validasi, migrasi, spark, uji; teruji komunitas | Native PHP: tanpa struktur, keamanan harus ditulis sendiri | **Library** |
| Grafik | Chart.js 4.4.8 (207 KB): animasi, responsif, tooltip bawaan | Canvas manual: waktu besar, aksesibilitas rendah | **Library** |
| Slideshow statistik | Carousel library: transisi siap pakai | `renderFrame()` sendiri (±60 baris): kendali penuh frame, timeline, dan progres sesuai Unit 12 | **Buat sendiri** |
| Ekspor Excel | PhpSpreadsheet 5.11.0: .xlsx berformat, banyak lembar | Menulis XML OOXML sendiri: rawan salah, lama | **Library** |
| PDF | Dompdf 3.1.6: dari HTML view | Menyusun PDF biner sendiri: tidak realistis | **Library** |
| Notifikasi toast/lonceng | Library toast: komponen siap pakai | Lonceng + badge sendiri (±10 baris JS, polling 30 dtk) | **Buat sendiri** (sederhana, tanpa dependensi) |
| Sorting/searching produksi | `ORDER BY`/`FULLTEXT` di SQL | Algoritma sendiri hanya untuk Unit 9 (`algoritma/`) | **Keduanya** dengan batas jelas |

## 3. Proof of concept (elemen 2.1 dan 2.2)

### 3.1 Chart.js vs ApexCharts (`poc/grafik.html`)

Fitur sederhana yang dijalankan: diagram batang dengan data identik (Baru 12, Diproses 8, Selesai 15) di dua pustaka.

| Aspek | Chart.js 4.4.8 | ApexCharts 3.54.1 |
|---|---:|---:|
| Ukuran berkas | 206.553 byte | 539.664 byte (±2,6× lebih besar) |
| Kode untuk grafik batang | 1 konstruktor, 1 dataset | 1 konstruktor, `series` + `xaxis` |
| Integrasi di aplikasi | Dipakai pada `/` dan `/slideshow` | Hanya PoC |

Manfaat teramati: grafik tampil dengan animasi bawaan dan responsif tanpa kode tambahan; ukuran lebih kecil mempercepat muat halaman lokal dan cocok untuk KT-01. Langkah demonstrasi: buka `poc/grafik.html` (tanpa server), bandingkan dua grafik berdampingan. Chart.js dipilih.

### 3.2 PhpSpreadsheet vs CSV manual (`php poc/ekspor.php`, tiga kali jalan)

| Format | Waktu (3 baris) | Ukuran | Kemampuan |
|---|---:|---:|---|
| CSV manual (`fputcsv`) | 0,36–0,54 ms | 42 byte | Teks polos; tanpa format sel |
| XLSX (PhpSpreadsheet) | 63,6–64,9 ms | 6.182 byte | Judul tebal, lebar kolom otomatis, beberapa lembar |

Manfaat terukur: XLSX ±120× lebih lambat dan ±150× lebih besar untuk data kecil, tetapi menghasilkan berkas Excel siap pakai oleh pengguna non-teknis (judul tebal, kolom pas). Ekspor 100 baris demo selesai jauh di bawah 1 detik (uji `LAP-01` pada `A14-http-results.md`). CSV tetap alternatif untuk data sangat besar.

### 3.3 Chart.js di aplikasi nyata

Dashboard memanggil `Chart.getChart(canvas)` pada tiga kanvas; hasil uji browser (A12) memeriksa bahwa ketiganya ter-render.

## 4. Rencana integrasi (elemen 3.1)

| Komponen | Cara integrasi | Berkas |
|---|---|---|
| CI4 | Composer, namespace `App\`, filter `auth`/`role` | `composer.json`, `app/Config/Filters.php` |
| Chart.js | Salinan lokal, dimuat di section `scripts` view | `public/vendor/chartjs/`, `app/Views/dashboard/*.php`, `public/assets/js/dashboard.js` |
| PhpSpreadsheet | Composer; dipanggil di `Reports::tickets()` dengan data dari `TicketListQuery::export()` | `app/Controllers/Reports.php` |
| Dompdf | Composer; HTML ringkasan bulanan di `Reports::monthly()` | `app/Controllers/Reports.php` |
| Slideshow sendiri | `renderFrame()` di `slideshow.js`, data lewat `window.slidesData` | `public/assets/js/slideshow.js` |

## 5. Limitasi (elemen 3.2)

| Komponen | Limitasi | Dampak / penanganan |
|---|---|---|
| CI4 | `like()` Query Builder tidak meng-escape wildcard `%`/`_`; Model `find()` bertipe `array|object|null` | Ditemukan saat migrasi Unit 10; ditangani `escapeLikeString()` dan `TicketModel::findTicket()` |
| Chart.js / ApexCharts | Membutuhkan JavaScript dan elemen canvas/SVG | Aplikasi tetap menampilkan angka kartu tanpa grafik |
| PhpSpreadsheet | Membutuhkan ekstensi `zip`, `xml`, `gd`; memuat seluruh sheet ke memori | Ekspor data sangat besar harus dibatasi/dialihkan ke CSV |
| Dompdf | CSS terbatas (CSS 2.1), tidak menjalankan JavaScript | Laporan memakai tabel sederhana |
| Slideshow sendiri | Layar penuh bergantung izin browser; tanpa dukungan sentuhan geser | Tombol manual tersedia |
| Aset lokal | Pembaruan versi pustaka manual | Versi dicatat di A01 dan commit |
