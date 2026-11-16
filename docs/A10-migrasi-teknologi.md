# A10 — Migrasi ke teknologi baru (Unit 10, J.620100.024.02)

## 0. Kejujuran riwayat

Aplikasi awal (tag `v0.8`, commit `5874d8d`) sudah memakai Query Builder sejak awal; tidak ada versi raw SQL yang pernah dipakai sebelumnya. Agar Unit 10 punya dua keadaan kode yang **benar-benar dijalankan**, modul daftar/pencarian/pagination/ekspor tiket (`App\Models\TicketListQuery`) ditulis dulu dengan raw SQL `mysqli` (tag `v0.9`, 16–17 Nov 2026), diukur dan diuji, lalu dimigrasi ke Query Builder (tag `v1.0`). Ini modul pembanding yang dibuat belakangan, bukan sisa pengembangan lama. Tag dapat diperiksa: `git diff v0.9 v1.0 -- app/Models`.

## 1. Evaluasi teknologi (elemen 1)

| Elemen | Isi |
|---|---|
| 1.1 Teknologi baru sesuai kebutuhan | Query Builder CI4 4.7.6 menggantikan `mysqli` mentah pada satu modul. Kebutuhan: kode lebih mudah dirawat, bebas injeksi tanpa disiplin manual, dapat dikomposisi untuk filter berbeda (daftar dan ekspor memakai kondisi yang sama) |
| Versi lama → baru | PHP `mysqli` (prepared statement manual, `bind_param`) pada PHP 8.2.12 / MariaDB 10.4.32 → CI4 Query Builder 4.7.6 (driver MySQLi di bawahnya) pada PHP dan DB yang sama |

### 1.2 Perbandingan keunggulan (berdasarkan kebutuhan aplikasi)

| Aspek | Raw `mysqli` (v0.9) | Query Builder (v1.0) |
|---|---|---|
| Panjang kode modul | 115 baris (`wc -l` pada tag v0.9) | 83 baris (tag v1.0; `git diff --stat`: 38 tambah, 70 hapus) |
| Binding parameter | Manual: string tipe `'iss…'` dan urutan `$params` harus sinkron dengan `?` | Otomatis |
| Perakitan WHERE | String `implode(' AND ')` + array tipe paralel | `where()` berantai, satu fungsi `applyConditions()` dipakai tiga kali (daftar, hitung, ekspor) |
| Koneksi | Harus memanggil `$db->initialize()` (koneksi lazy; bug A11 #9) | Ditangani framework |
| Transaksi dan log query | Manual | Toolbar menampilkan query; transaksi `transBegin/Commit` |
| Pitfall | Escape wildcard LIKE harus ditulis sendiri | `like()` **tidak** meng-escape `%`/`_`; harus ditambah `escapeLikeString()` (ditemukan lewat uji kesetaraan, A11 #8) |
| Kinerja (100.000 tiket, sesi sama) | lihat tabel §3 | setara dalam batas derau |

Keputusan (1.3): migrasi dilakukan karena kode Query Builder lebih ringkas dan dapat dipakai bersama; kinerja tidak menjadi pembeda. Hasil analisis dan keputusan didasarkan pada pengukuran §3, bukan asumsi.

## 2. Fitur khusus teknologi baru (elemen 2)

| Fitur (2.1 dianalisis) | Dipakai di (2.2 diimplementasikan) |
|---|---|
| Binding parameter otomatis | `where('t.user_id', $id)`, `where('t.status', $value)` |
| Builder kondisi yang dapat di-compose | `applyConditions(BaseBuilder $b, …)` dipakai ulang oleh `paginate()` (tiga kali: halaman, hitung, subquery id) dan `export()` |
| Subquery builder | v1.1: `fromSubquery($ids, 'page')` untuk deferred join (A10 §4) |
| Transaksi | `transBegin/transCommit/transRollback` di `TicketService` |
| Helper escape | `escape()`, `escapeLikeString()` |

## 3. Kesetaraan fungsi dan pengukuran (bukti perbandingan yang sama)

**Uji fungsi.** Skenario yang sama berjalan pada kedua implementasi: `TicketListQueryTest` (7 skenario, dijalankan pada v0.9 dan v1.0) dan `TicketListQueryEquivalenceTest` (v1.0 membandingkan langsung `App\Models\TicketListQuery` dengan salinan lama `tests/_support/Legacy/RawTicketListQuery.php` untuk 14 skenario `paginate` + 5 skenario `export`: filter, kombinasi, pencarian pendek/nomor/FULLTEXT, wildcard, injeksi, halaman di luar rentang). Hasil: identik, setelah satu perbedaan nyata diperbaiki (wildcard LIKE). Regresi HTTP: 106/106 pada v0.9 dan 106/106 pada v1.0.

**Kinerja** (`php database/benchmark_list_query.php`, `tiket_loadtest` 100.000 tiket, 5× pemanasan + 30 pengulangan; raw dan Query Builder diukur berurutan pada sesi yang sama, `docs/A10-benchmark-sesi-sama-raw.json` dan `…-qb.json`):

| Skenario | Raw p50 / p95 ms | Query Builder p50 / p95 ms |
|---|---:|---:|
| Halaman pertama | 2248 / 4865 | 1966 / 2365 |
| Filter status+prioritas | 648 / 789 | 631 / 791 |
| Cari judul FULLTEXT | 0,62 / 0,93 | 0,57 / 0,69 |
| Cari nomor tiket | 4,4 / 5,5 | 5,8 / 6,6 |
| Halaman dalam (hal. 5000) | 2098 / 2474 | 2258 / 2829 |
| Pemohon sendiri | 8,9 / 11,3 | 7,6 / 14,9 |
| Ekspor terfilter | 0,24 / 0,35 | 0,31 / 0,60 |

Kesimpulan: kinerja setara dalam batas derau (selisih p50 ≤ ±12%, arah tidak konsisten); satu pengukuran terpisah saat `v0.9` (`A10-benchmark-raw-sql-v0.9.json`) memberi angka serupa. Pengukuran berlaku untuk perangkat dan data ini.

## 4. Temuan kinerja → patch v1.1 (menghubungkan Unit 2, 10, 17)

Benchmark yang sama memperlihatkan **titik sempit pada kedua implementasi**: halaman pertama ±2 detik pada 100.000 tiket karena `EXPLAIN` memilih `categories` (2 baris) sebagai tabel awal, memakai *temporary + filesort*, dan menghitung total dengan memindai seluruh baris (`innodb_buffer_pool_size` XAMPP hanya 16 MB). Perbaikan bertahap, masing-masing diukur bersih (tanpa proses lain):

| Skenario (p50 ms) | v1.0 tanpa index baru | + `idx_active_list` saja | v1.1 (index + deferred join) |
|---|---:|---:|---:|
| Halaman pertama | 1966 | 524 | **20,4** |
| Filter status+prioritas | 631 | **1683 (lebih buruk)** | **9,8** |
| Halaman dalam (hal. 5000) | 2258 | 540 | **51,7** |
| Cari judul FULLTEXT | 0,57 | 0,55 | 3,5 (p95 4,8; target NF-02 < 100) |
| Pemohon sendiri | 7,6 | 6,7 | 4,3 |

Pelajaran: index saja mempercepat hitungan tetapi memperburuk filter ±2,7× karena optimizer memilih rencana join yang salah; yang menyelesaikan masalah adalah *deferred join* (subquery memilih 20 `id` dari index, baru JOIN). Satu kemunduran kecil diterima dan dicatat: pencarian FULLTEXT naik dari 0,57 ke 3,5 ms (tetap jauh di bawah target 100 ms). Berkas: `A10-benchmark-index-saja.json`, `A10-benchmark-v1.1.json`. Kesetaraan hasil v1.1 dijaga oleh `TicketListQueryEquivalenceTest`.

## 5. Risiko dan rencana rollback

| Risiko | Mitigasi |
|---|---|
| Perilaku berbeda halus (contoh nyata: wildcard LIKE) | Uji kesetaraan berskenario banyak; salinan lama dipertahankan di `tests/_support/Legacy/` |
| Regresi kinerja akibat migrasi | Benchmark sebelum/sesudah pada data 100.000 tiket |
| Kegagalan produksi setelah migrasi | Rollback kode: `git checkout v0.9` (kontrak publik `paginate/export` sama sehingga controller tidak berubah); tidak ada perubahan skema pada migrasi v0.9→v1.0 |
| Patch v1.1 | Rollback terdemonstrasi di A17 (`migrate:rollback` + `git checkout v1.0`) |
