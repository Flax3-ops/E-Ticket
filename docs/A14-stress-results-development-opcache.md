# Hasil uji beban terautentikasi dan ApacheBench — mode development + OPcache aktif (v1.1)

Dijalankan: 2026-11-16 17:32 UTC pada instance Apache XAMPP (`scripts/apache-tiket.conf`), PHP 8.2.12, database `tiket_demo` (100 tiket).

## Lingkungan

Windows NT FURQON973-TUF 10.0 build 26200 (Windows 11) AMD64

## 1. 50 sesi sah serentak (NF-03)

| Fase | Sesi | Permintaan | Berhasil | Error | p50 ms | p95 ms | maks ms | Catatan |
|---|---:|---:|---:|---:|---:|---:|---:|---|
| Login password+OTP (4 langkah, serentak) | 50 | 200 | 50 login | 0 | 790 | 1399 | 1424 | durasi 4.7 s |
| Alur kerja (6 halaman x 5 putaran, 50 sesi serentak) | 50 | 1500 | 1500 status 200 | 0 | 620 | 795 | 1691 | 56.5 req/s, durasi 26.6 s |

Setiap sesi memakai cookie sesi sendiri hasil login nyata (bukan satu cookie yang diulang). Halaman: `/`, `/tickets`, `/tickets?q=Permintaan`, `/api/dashboard/summary`, `/notifications`, `/api/notifications/unread`.

## 2. ApacheBench `-n 1000 -c 50` (uji HTTP dasar, bukan bukti 50 pengguna)

| Rute | Selesai | Gagal | Non-2xx | req/s | rata-rata ms | p50 ms | p95 ms | p99 ms | maks ms |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| `/login` | 1000 | 0 | 0 | 72.56 | 689.106 | 666 | 936 | 1041 | 1232 |
| `/tickets` | 1000 | 0 | 0 | 19.72 | 2535.946 | 2396 | 3194 | 3225 | 3246 |
| `/monitor/metrics` | 1000 | 0 | 0 | 17.10 | 2924.574 | 3138 | 3896 | 4044 | 4062 |

## 3. Sumber daya (Get-Counter, sampel per detik)

| Kondisi | CPU / RAM |
|---|---|
| Sebelum uji (kondisi awal) | CPU rata-rata 25.6% (maks 27.7%), RAM bebas minimum 7929 MB (2 sampel) |
| Login 50 sesi serentak | CPU rata-rata 89.6% (maks 100.0%), RAM bebas minimum 7864 MB (4 sampel) |
| Alur kerja 50 sesi | CPU rata-rata 93.1% (maks 99.3%), RAM bebas minimum 7406 MB (25 sampel) |
| ab `/login` | CPU rata-rata 96.3% (maks 100.0%), RAM bebas minimum 7275 MB (12 sampel) |
| ab `/tickets` | CPU rata-rata 25.4% (maks 66.4%), RAM bebas minimum 6919 MB (50 sampel) |
| ab `/monitor/metrics` | CPU rata-rata 21.3% (maks 44.5%), RAM bebas minimum 6796 MB (58 sampel) |

Catatan: pengukuran berasal dari satu laptop yang juga menjalankan klien uji, XAMPP, dan proses lain; hasil bukan jaminan untuk perangkat atau konfigurasi lain.
