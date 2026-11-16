# A12 — Pemrograman multimedia (Unit 12, J.620100.030.02)

Kode: `public/assets/js/slideshow.js` (modul `renderFrame()` buatan sendiri), `app/Views/dashboard/slideshow.php`, grafik Chart.js `public/assets/js/dashboard.js` dan `monitor.js`. Bukti uji: `docs/screenshots/` dan `hasil-chrome.json`, `hasil-edge.json` (dihasilkan `tests/browser/capture.mjs`).

## 1. Kebutuhan platform (elemen 1)

| Aspek | Kebutuhan |
|---|---|
| Hardware (1.1) | CPU 2 inti, RAM 4 GB, layar ≥ 360 px; monitor ruang layanan untuk mode layar penuh. Terukur: laptop Core i5-11400H/16 GB, slideshow berjalan lancar |
| Software (1.2) | Browser modern dengan JavaScript, Canvas 2D, `requestAnimationFrame`, CSS transform/transition: Chrome, Edge, Firefox terbaru. Aplikasi server: PHP 8.2 / MariaDB 10.4 / Apache (XAMPP) |
| Sistem operasi (1.3) | Windows 10/11 (diuji Windows 11 build 26200); klien browser dapat OS apa pun |
| Aplikasi dasar (1.4) | Chart.js 4.4.8 (`public/vendor/chartjs`), Bootstrap 5.3.3, tanpa koneksi internet (aset lokal) |

## 2. Perencanaan dan timeline (elemen 2)

Konsep (2.1–2.2): slideshow adalah presentasi statistik untuk monitor ruang layanan. Sebelum menulis kode ditetapkan storyboard berikut; data diambil dari `/slideshow` (variabel `window.slidesData`) sehingga tidak ada panggilan jaringan tambahan.

| Section | Frame | Konten | Durasi | Target elemen DOM |
|---|---:|---|---:|---|
| Ringkasan layanan | 1 | Total tiket (angka besar) | 4,5 dtk | `#slide-label`, `#slide-value`, `#slide-description` |
| Ringkasan layanan | 2 | Tiket terbuka | 4,5 dtk | idem |
| Ringkasan layanan | 3 | Lewat SLA | 5,0 dtk | idem |
| Grafik | 4 | Diagram batang status tiket (Chart.js) | 6,0 dtk | `#slide-chart` |
| Peringkat | 5 | Petugas teratas + jumlah tiket selesai | 5,0 dtk | `#slide-value`, `#slide-description` |

Timeline (2.3): satu siklus 25 dtk; indikator progres `#slide-progress` tumbuh 0→100% selama durasi frame (dihitung dari `performance.now()` dalam `requestAnimationFrame`); frame berganti otomatis saat progres penuh.

## 3. Sintaks khusus multimedia (elemen 3)

| Elemen | Bukti pada `slideshow.js` |
|---|---|
| 3.1 Sintaks/grammar | JavaScript ES2020: arrow function, `const/let`, template literal, optional chaining (`?.`) |
| 3.2 Tipe data | Objek frame `{label, value, description, duration, chart}`; angka (`duration`), boolean (`playing`, `chart`), string (label) |
| 3.3 Variabel | `index`, `playing`, `start`, `chart`, referensi DOM (`label`, `value`, `progress`, `chartCanvas`) dideklarasikan di awal modul |
| 3.4 Operator | Aritmetika modulo `(index + direction + n) % n`, perbandingan `elapsed >= duration`, rasio `elapsed / duration * 100`, ternary pada teks tombol |
| 3.5 Komentar | Komentar penjelas pada fungsi dan blok kode (transisi, penghitung waktu, kontrol) |

## 4. Statement per frame, target path, algoritma khusus (elemen 4)

- **Beberapa pernyataan dalam satu frame (4.1)**: `renderFrame(i)` dalam satu pemanggilan mengubah `label.textContent`, `value.style.display/opacity/transform` (transisi fade+slide 0,5 dtk), `description.textContent`, `progress.style.width = '0%'`, dan membuat/menampilkan `Chart` bila frame bergrafik — yaitu perubahan teks, grafik, dan indikator progres bersamaan.
- **Target path (4.2)**: elemen ditunjuk dengan `document.getElementById('slide-…')`; tombol `#prev`, `#pause`, `#next`, `#fullscreen`.
- **Algoritma khusus (4.3)**: penghitung frame berbasis waktu nyata (`performance.now`) agar durasi tidak bergantung laju refresh layar; navigasi melingkar dengan modulo; pembuatan grafik malas (hanya saat frame grafik pertama kali tampil).

## 5. Kompatibilitas dan uji pada platform (elemen 5)

Minimum (5.1, 5.2): 2 inti/4 GB; browser dengan Canvas dan `requestAnimationFrame` (Chrome/Edge/Firefox terbaru). Instalasi (5.3): tidak ada instalasi sisi klien; aplikasi dibuka lewat URL XAMPP (README, ≤ 10 langkah). Menjalankan (5.4) — hasil uji aktual aplikasi v1.1:

| Browser / platform | Versi | Metode | 24 halaman (12 × desktop 1366 px & mobile 360 px): galat konsol / overflow | Grafik dashboard (3 kanvas) | Tema gelap | Slideshow |
|---|---|---|---|---|---|---|
| Chrome | 157.0.8103.51 | Otomatis (CDP), klik sungguhan | 0 / 0 | 3/3 ter-render | Berubah saat klik | 5 frame unik; Berikutnya/Sebelumnya benar; Jeda menghentikan kemajuan (diuji 5,5 dtk) dan teks tombol berganti Putar/Jeda; maju otomatis saat Putar; tombol Layar penuh memanggil `requestFullscreen` (headless tidak menampilkan layar penuh) |
| Edge | 157.0.4341.60 | Otomatis (CDP) | 0 / 0 | 3/3 | Berubah | Identik dengan Chrome |
| Firefox | 151.0.4 | **Terbatas**: tangkapan layar halaman login desktop & 360 px lewat `firefox --headless --screenshot`; Firefox tidak mendukung CDP sehingga halaman terautentikasi, grafik, dan slideshow **tidak** diuji otomatis | — | — | — | **Belum terbukti**; perlu uji manual asesi saat demonstrasi |
| Mobile (emulasi 360×740) | Chrome/Edge | Emulasi viewport + `mobile` | Tidak ada overflow | Grafik responsif | — | Slideshow terlihat penuh lebar |

Perangkat nyata (ponsel fisik) tidak diuji. Tangkapan layar: `chrome-desktop-*.png`, `chrome-mobile360-*.png`, `edge-*.png`, `firefox-*-login.png`, `*-slideshow-frame-petugas.png`.

Perbaikan hasil uji ini: halaman Monitor semula menampilkan CPU/RAM "Tidak tersedia" dan sumbu waktu bercampur UTC/WIB; keduanya diperbaiki (A11 bug 15–16) dan tangkapan layar diambil ulang.
