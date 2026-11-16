# A02 — Analisis skalabilitas (Unit 2, J.620100.002.01)

Seluruh angka berasal dari pengukuran 16–17 November 2026; berkas sumber ada di `docs/`.

## 1. Lingkup dan lingkungan (elemen 1)

| Aspek | Nilai |
|---|---|
| Lingkup sistem (1.1) | Helpdesk internal satu instansi: pemohon membuat tiket, petugas memproses, admin memantau; dashboard, alert, monitor, ekspor. Tanpa cloud, email, atau multi-tenant (SRS §1.2) |
| Pengguna/data awal | 10 pengguna, 100 tiket, ±200 komentar (demo). Target uji: 50 sesi serentak, 100.000 tiket (`tiket_loadtest`) |
| Proyeksi | 10× = 1.000 tiket/500 pengguna dan 1 juta tiket pada 10×; 100× = 10 juta tiket (di luar lingkup tanpa partisi/arsip) |
| Lingkungan operasi (1.2) | Windows 11 (build 26200), XAMPP: Apache 2.4.58, PHP 8.2.12 (ZTS, modul Apache), MariaDB 10.4.32 |
| Perangkat uji | Intel Core i5-11400H (6 inti/12 thread, 2,7 GHz), RAM 16 GB (±3–8 GB bebas saat uji), disk D: SSD 246 GB (±82% terpakai); satu laptop yang juga menjalankan klien uji |
| Konfigurasi kritis | `innodb_buffer_pool_size` = 16 MB (bawaan XAMPP); `max_connections` = 151; PHP **OPcache nonaktif** pada `php.ini` XAMPP; session handler berkas |

## 2. Masalah skalabilitas dan titik sempit (elemen 2.1)

| # | Titik sempit | Bukti | Penanganan |
|---|---|---|---|
| 1 | Daftar tiket: `COUNT(*)` tanpa index mencakup memindai seluruh baris; optimizer memulai JOIN dari tabel kecil dan menyortir ratusan ribu baris | Halaman pertama 100.000 tiket p50 **1.966 ms**; filter status+prioritas 631 ms; halaman 5000 2.258 ms (`A10-benchmark-sesi-sama-qb.json`) | v1.1: index `idx_active_list` + deferred join → **20,4 / 9,8 / 51,7 ms** (A10 §4) |
| 2 | Pencarian judul tanpa FULLTEXT | Median **310,089 ms** (`LIKE`, `EXPLAIN` `ALL` ±98.955 baris) → **0,839 ms** (`fulltext`) (`A08-index-benchmark.json`) | `ft_title`; p95 30 pengulangan 0,69–0,93 ms (NF-02 < 100 ms) |
| 3 | PHP tanpa OPcache: tiap permintaan mengompilasi ulang file framework | Mode development, 50 sesi serentak: p95 alur kerja **1.993 ms** (24,4 req/s) → **795 ms** (56,5 req/s) dengan OPcache; login serentak p95 2.550 → 1.399 ms (`A14-stress-results-development*.md`) | Aktifkan `zend_extension=opcache` (kini disediakan `scripts/php-ini-opcache/opcache.ini` untuk instance uji; `php.ini` XAMPP tidak diubah) |
| 4 | Login bcrypt + OTP bcrypt bersifat CPU-bound | 50 login serentak: CPU 90–100%, p95 1,4–2,5 s; `ab /login` 29–73 req/s | Wajar (biaya hash disengaja); batasi percobaan login, kapasitas = jumlah inti |
| 5 | Pembacaan OS tiap permintaan `/monitor/metrics` (PowerShell + CIM) | `ab` tak selesai >10 menit sebelum perbaikan | Snapshot 3 detik dengan `flock` (v0.9-audit); polling grafik 5 detik per admin → ≤ 1 pembacaan OS/3 detik |
| 6 | Kunci sesi berkas: permintaan paralel dengan sesi yang sama antre | `ab -c 50` dengan satu cookie: `/tickets` hanya 10–25 req/s dengan CPU 25–60% (bukan CPU-bound), padahal 50 sesi berbeda mencapai 34–56 req/s | Bukan masalah pengguna nyata (satu pengguna jarang memicu 50 permintaan paralel); inilah alasan `ab -c 50` ≠ 50 pengguna (NF-03). Opsi: `DatabaseHandler` bila perlu |
| 7 | Polling: notifikasi tiap 30 detik per pengguna, monitor tiap 5 detik per admin | 50 pengguna → ±1,7 req/s polling dasar | Murah (query berindex `idx_user_read`) |
| 8 | Ekspor XLSX memuat seluruh hasil ke memori | 100 tiket < 1 detik; PhpSpreadsheet ±64 ms untuk 3 baris (A03) | Batasi ekspor besar / beralih CSV pada >50.000 baris |

## 3. Kompleksitas aplikasi (elemen 2.2)

| Proses | Kompleksitas | Catatan |
|---|---|---|
| Daftar tiket halaman k | O(log n + perPage) dengan `idx_active_list` (v1.1); sebelumnya O(n) | Offset besar masih O(offset) pada index (hal. 5000 = 52 ms); keyset pagination bila perlu |
| Pembuatan tiket | O(1): satu baris + satu baris sequence + notifikasi O(jumlah staf) | Notifikasi per staf tumbuh linier dengan jumlah petugas |
| Pengambilan tiket | O(1) satu `UPDATE` bersyarat | Aman terhadap persaingan |
| `alert:check` tiap menit | O(tiket Kritis belum diambil + tiket terlambat + alert aktif); N+1 dihapus di v1.1 | Query SLA memakai `v_ticket_sla` |
| Dashboard | O(n) agregat view per kategori/hari/status | Perlu tabel ringkasan bila > ±1 juta tiket |

## 4. Kebutuhan perangkat keras (elemen 2.3)

| Skala | CPU | RAM | Disk | Dasar |
|---|---|---|---|---|
| Demo/asesmen (≤ 50 sesi, ≤ 100.000 tiket) | 4 inti | 8 GB (aplikasi memakai <1 GB; sisanya OS/browser) | ±1 GB (diukur: DB 100.000 tiket = 42,7 MB termasuk index; 100 tiket demo = 0,8 MB) | Uji: CPU laptop 6 inti jenuh 90–100% saat 50 login serentak; RAM bebas minimum tetap ≥ 2,4 GB |
| 10× (≈ 500 pengguna, 1 juta tiket) — estimasi | 8 inti, OPcache wajib | 16 GB, `innodb_buffer_pool_size` ≥ 1 GB | ±0,5 GB DB (ekstrapolasi linear dari 42,7 MB) | Ekstrapolasi: index B-tree tumbuh ~ log n, tetapi buffer pool 16 MB sudah terbukti menjadi pembatas pada 100.000 baris |
| 100× | Tidak direkomendasikan pada arsitektur ini; butuh arsip/partisi, sesi di DB/Redis, pembaruan hitungan dashboard terjadwal | — | — | Di luar data ukur |

## 5. Hasil ukur ringkas dan batas estimasi (elemen 2.4)

| Uji | Hasil | Berkas |
|---|---|---|
| 50 sesi sah serentak (production, Apache) | Login p95 2.023 ms, alur kerja p95 1.420 ms, 0 error dari 1.700 permintaan | `A14-stress-results-production.md` |
| Idem, development + OPcache (v1.1) | Login p95 1.399 ms, alur kerja p95 795 ms, 0 error | `A14-stress-results-development-opcache.md` |
| Index/daftar tiket 100.000 baris | Lihat §2 baris 1–2 | `A10-*.json`, `A08-index-benchmark.json` |

Batas estimasi: satu perangkat yang juga menjalankan klien uji (CPU klien ikut menjenuhkan), data sintetik berdistribusi merata (`LoadTestSeeder`), buffer pool 16 MB, tanpa jaringan nyata. Ekstrapolasi 1 juta/10 juta baris **tidak diukur** dan tidak boleh dianggap jaminan. Hasil sesi serentak memakai 50 pengguna berbeda (bukan satu cookie).
