# A05 — Pemrograman terstruktur (Unit 5, J.620100.017.02)

Kode: `util/`. Hasil eksekusi nyata: `util/hasil-eksekusi.txt`. Hasil `php -l`: `util/hasil-php-l.txt`. Semua skrip memakai `declare(strict_types=1)`.

| Skrip | Elemen yang dibuktikan | Isi singkat | Hasil eksekusi (ringkas) |
|---|---|---|---|
| `01_biaya_layanan.php` | 1.1, 1.2, 1.3, 2.1, 2.2, 3.2, 3.4 | Input keyboard (`fgets(STDIN)`), validasi `ctype_digit` dengan perulangan `do…while`, diskon bertingkat (percabangan bersarang: ≥10 → 20%, ≥5 → 10%), fungsi `hitungBiaya()` dan `bacaBilangan()` | 12 × Rp150.000 → `Rp1.440.000`; input `abc` ditolak lalu diulang |
| `02_hitung_sla.php` | 2.1 (variasi I/O: baca dua baris, keluaran ke STDERR saat galat), 3.2 | Menghitung tenggat dari prioritas dan waktu UTC (`DateTimeImmutable`); fungsi `jamSla()` | `kritis` + `2026-11-16 08:00:00` → `2026-11-16 12:00:00 UTC` |
| `03_csv_ringkasan.php` | 5.1, 5.5, 2.2 | Membaca CSV (`fgetcsv`), menghitung per prioritas dengan array asosiatif, menulis ringkasan (`file_put_contents`) | `ringkasan.txt` berisi Kritis 1, Sedang 1, Tinggi 1 |
| `04_array_prioritas.php` | 4.1, 4.2, 4.3, 4.4 | Array satu dimensi lima string, `usort` menurut bobot, `count()` | `Kritis, Tinggi, Tinggi, Sedang, Rendah`; 5 elemen |
| `05_menu.php` | 3.1, 3.3, 2.2 | Prosedur `tampilMenu()` (tanpa nilai kembali) dan fungsi `jamContoh()` dipakai bersamaan; `switch` dan `do…while` | Menu 1, 2, pilihan tak dikenal, keluar 0 |
| `06_array_dua_dimensi.php` | 4.1 (dimensi 2), 4.2 (elemen `int`), 4.3 (panjang tercatat), 4.4 (`uasort`) | Matriks kategori × prioritas 3 × 4; keluaran tabel terformat `printf` | Urut menurun jumlah: Perangkat keras 17, Akun dan akses 15, Jaringan 11 |

## Keterangan prosedur dan fungsi (elemen 3.4)

Setiap fungsi/prosedur memiliki komentar `/** ... */` yang menjelaskan tujuan, parameter, dan nilai kembali. Contoh: `hitungBiaya(int $jumlah, int $tarif): int` — "Menghitung tarif setelah diskon bertingkat berdasarkan jumlah layanan".

## Mengompilasi dan memperbaiki kesalahan (elemen 6.1, 6.2)

`util/koreksi-sintaks/` berisi **latihan** koreksi sintaks: `sebelum.php` sengaja memuat titik koma yang hilang (dibuat penulis sebagai latihan, bukan kesalahan spontan; hal ini dicatat di berkas itu sendiri), `sesudah.php` adalah versi benar.

| Langkah | Perintah | Hasil |
|---|---|---|
| Periksa versi salah | `php -l util/koreksi-sintaks/sebelum.php` | `Parse error: syntax error, unexpected token "}", expecting ";" ... on line 12`, kode keluar 255 |
| Koreksi | Tambah `;` pada `return 20;` dan pada `echo …;` | — |
| Periksa versi benar | `php -l util/koreksi-sintaks/sesudah.php` | `No syntax errors detected` |
| Jalankan | `php util/koreksi-sintaks/sesudah.php` | `Diskon: 20%` |

Catatan jujur: bukti "sebelum/sesudah" ini adalah demonstrasi terencana. Kesalahan sintaks yang muncul tidak sengaja selama pengembangan aplikasi (jika ada) dicatat pada A11, dan asesor dapat meminta asesi mengulangi koreksi pada kode yang disodorkan saat demonstrasi.

Seluruh berkas `util/0*.php` lulus `php -l` (lihat `util/hasil-php-l.txt`).
