# Hasil uji beban terautentikasi dan ApacheBench — mode development (Debug Toolbar aktif)

Dijalankan: 2026-11-16 16:28 UTC pada instance Apache XAMPP (`scripts/apache-tiket.conf`), PHP 8.2.12, database `tiket_demo` (100 tiket).

## Lingkungan

Windows NT FURQON973-TUF 10.0 build 26200 (Windows 11) AMD64

## 1. 50 sesi sah serentak (NF-03)

| Fase | Sesi | Permintaan | Berhasil | Error | p50 ms | p95 ms | maks ms | Catatan |
|---|---:|---:|---:|---:|---:|---:|---:|---|
| Login password+OTP (4 langkah, serentak) | 50 | 200 | 50 login | 0 | 1734 | 2550 | 2611 | durasi 9.1 s |
| Alur kerja (6 halaman x 5 putaran, 50 sesi serentak) | 50 | 1500 | 1500 status 200 | 0 | 1584 | 1993 | 2976 | 24.4 req/s, durasi 61.4 s |

Setiap sesi memakai cookie sesi sendiri hasil login nyata (bukan satu cookie yang diulang). Halaman: `/`, `/tickets`, `/tickets?q=Permintaan`, `/api/dashboard/summary`, `/notifications`, `/api/notifications/unread`.

## 2. ApacheBench `-n 1000 -c 50` (uji HTTP dasar, bukan bukti 50 pengguna)

| Rute | Selesai | Gagal | Non-2xx | req/s | rata-rata ms | p50 ms | p95 ms | p99 ms | maks ms |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| `/login` | 1000 | 0 | 0 | 29.47 | 1696.390 | 1617 | 2350 | 2701 | 3116 |
| `/tickets` | 1000 | 0 | 0 | 10.04 | 4977.826 | 4893 | 5325 | 5756 | 6575 |
| `/monitor/metrics` | 1000 | 0 | 0 | 9.73 | 5140.827 | 4635 | 6279 | 6369 | 6395 |

## 3. Sumber daya (Get-Counter, sampel per detik)

| Kondisi | CPU / RAM |
|---|---|
| Sebelum uji (kondisi awal) | CPU rata-rata 12.2% (maks 16.2%), RAM bebas minimum 2937 MB (2 sampel) |
| Login 50 sesi serentak | CPU rata-rata 93.0% (maks 100.0%), RAM bebas minimum 2741 MB (8 sampel) |
| Alur kerja 50 sesi | CPU rata-rata 96.4% (maks 100.0%), RAM bebas minimum 2478 MB (53 sampel) |
| ab `/login` | CPU rata-rata 98.0% (maks 100.0%), RAM bebas minimum 2596 MB (31 sampel) |
| ab `/tickets` | CPU rata-rata 37.9% (maks 86.5%), RAM bebas minimum 2628 MB (99 sampel) |
| ab `/monitor/metrics` | CPU rata-rata 34.7% (maks 91.9%), RAM bebas minimum 2623 MB (102 sampel) |

Catatan: pengukuran berasal dari satu laptop yang juga menjalankan klien uji, XAMPP, dan proses lain; hasil bukan jaminan untuk perangkat atau konfigurasi lain.
