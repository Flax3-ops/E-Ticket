# Hasil uji beban terautentikasi dan ApacheBench — mode production (Debug Toolbar nonaktif)

Dijalankan: 2026-11-16 16:22 UTC pada instance Apache XAMPP (`scripts/apache-tiket.conf`), PHP 8.2.12, database `tiket_demo` (100 tiket).

## Lingkungan

Windows NT FURQON973-TUF 10.0 build 26200 (Windows 11) AMD64

## 1. 50 sesi sah serentak (NF-03)

| Fase | Sesi | Permintaan | Berhasil | Error | p50 ms | p95 ms | maks ms | Catatan |
|---|---:|---:|---:|---:|---:|---:|---:|---|
| Login password+OTP (4 langkah, serentak) | 50 | 200 | 50 login | 0 | 1225 | 2023 | 2156 | durasi 7.2 s |
| Alur kerja (6 halaman x 5 putaran, 50 sesi serentak) | 50 | 1500 | 1500 status 200 | 0 | 1205 | 1420 | 1493 | 34.1 req/s, durasi 44.0 s |

Setiap sesi memakai cookie sesi sendiri hasil login nyata (bukan satu cookie yang diulang). Halaman: `/`, `/tickets`, `/tickets?q=Permintaan`, `/api/dashboard/summary`, `/notifications`, `/api/notifications/unread`.

## 2. ApacheBench `-n 1000 -c 50` (uji HTTP dasar, bukan bukti 50 pengguna)

| Rute | Selesai | Gagal | Non-2xx | req/s | rata-rata ms | p50 ms | p95 ms | p99 ms | maks ms |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| `/login` | 1000 | 0 | 0 | 41.55 | 1203.258 | 1116 | 1895 | 2105 | 2657 |
| `/tickets` | 1000 | 0 | 0 | 24.31 | 2056.973 | 1945 | 2768 | 2822 | 2861 |
| `/monitor/metrics` | 1000 | 0 | 0 | 15.64 | 3197.675 | 3397 | 4365 | 6246 | 6277 |

## 3. Sumber daya (Get-Counter, sampel per detik)

| Kondisi | CPU / RAM |
|---|---|
| Sebelum uji (kondisi awal) | CPU rata-rata 22.1% (maks 24.4%), RAM bebas minimum 2736 MB (2 sampel) |
| Login 50 sesi serentak | CPU rata-rata 93.9% (maks 100.0%), RAM bebas minimum 2694 MB (6 sampel) |
| Alur kerja 50 sesi | CPU rata-rata 96.5% (maks 100.0%), RAM bebas minimum 2646 MB (38 sampel) |
| ab `/login` | CPU rata-rata 98.4% (maks 100.0%), RAM bebas minimum 2493 MB (21 sampel) |
| ab `/tickets` | CPU rata-rata 62.3% (maks 90.5%), RAM bebas minimum 2508 MB (41 sampel) |
| ab `/monitor/metrics` | CPU rata-rata 47.8% (maks 91.8%), RAM bebas minimum 2624 MB (63 sampel) |

Catatan: pengukuran berasal dari satu laptop yang juga menjalankan klien uji, XAMPP, dan proses lain; hasil bukan jaminan untuk perangkat atau konfigurasi lain.
