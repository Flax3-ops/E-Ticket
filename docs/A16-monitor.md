# A16 — Pemantauan resource (Unit 16, J.620100.045.01)

Kode: `app/Services/MonitorService.php`, `app/Libraries/Resource/*`, `app/Controllers/Monitor.php`, `app/Views/monitor/index.php`, `public/assets/js/monitor.js`, `app/Commands/MonitorCollect.php`. Bukti: `docs/screenshots/*-monitor.png`, `docs/A15-hasil-cli.txt`, `docs/A14-stress-results-*.md`.

## 1. Resource kritis dan batas atas (elemen 1)

| Resource (1.1) | Sumber dan satuan | Batas atas sebelum failure (1.2) | Alasan |
|---|---|---|---|
| CPU | `Win32_Processor.LoadPercentage` (rata-rata), %; Linux `/proc` | **85%** | Uji beban: CPU jenuh 90–100% menaikkan p95 login ke 1,4–2,5 s (A14); di atas 85% kapasitas cadangan menipis |
| RAM | `Win32_OperatingSystem` (terpakai/total), MB → % | **85%** | Di atas itu OS mulai paging; diukur RAM bebas minimum 2,4 GB dari 16 GB saat uji |
| Disk | Partisi tempat aplikasi (`disk_total_space`), GB → % | **90%** | Log, unggahan, dan backup tumbuh; disk laptop uji 82% terpakai |
| Koneksi DB | `Threads_connected / max_connections` (151), % | **80%** | Setelah itu koneksi baru ditolak (`Too many connections`) |
| Waktu respons query uji | `SELECT 1`, ms | (informasi, tanpa ambang) | Indikasi beban DB |
| Memori PHP | `memory_get_usage(true)`, MB | (informasi) | Deteksi kebocoran |

Ambang disimpan di `settings` (`cpu_limit`, `ram_limit`, `disk_limit`, `db_limit`) dan dapat diubah admin.

## 2. Modul visualisasi (elemen 2)

| Elemen | Implementasi |
|---|---|
| 2.1 Visualisasi per resource | Empat kartu angka (CPU, RAM, Disk, Koneksi DB) dan grafik garis Chart.js dengan satu seri per resource (0–100%); nilai tak terbaca ditampilkan `Tidak tersedia` (bukan nol) |
| 2.2 Modul dan batas atas | `/monitor` memperbarui tiap 5 detik lewat `GET /monitor/metrics` (JSON), menyimpan 60 titik; **garis putus-putus batas atas** per resource (CPU/RAM 85, Disk 90, DB 80). Riwayat awal 60 sampel dari tabel `resource_metrics` (diisi `php spark monitor:collect` tiap menit). Label waktu seragam WIB. `/monitor/metrics` memakai snapshot 3 detik dengan `flock` agar pembacaan OS (PowerShell/CIM) tidak dipanggil setiap permintaan |

Hasil uji aktual (`chrome-desktop-monitor.png`, Chrome 157, 17 Nov 2026): CPU 12,0%, RAM 63,5%, Disk 81,9%, Koneksi DB 0,7%, status "Aktif", garis batas atas tampil, riwayat dan titik langsung bersambung. `chrome-mobile360-monitor.png` untuk 360 px; tidak ada overflow.

## 3. Pengujian dan koreksi

| Pengujian | Hasil |
|---|---|
| `php spark monitor:collect` (2×) | `{"cpu_pct":5,"ram_used_mb":9349,"ram_total_mb":16106,"disk_used_gb":202.1,…,"status":"ok"}` lalu 12% (`A15-hasil-cli.txt`); baris tersimpan di `resource_metrics` |
| Ambang → alert | `AlertServiceTest` (CPU 99% memicu `resource:cpu`, satu alert aktif, pengingat 15 menit) memakai stub pembaca |
| Kegagalan pembaca | `status: unavailable`, nilai `null`, alert `reader:os` (A15) |
| Beban | `/monitor/metrics` 1.000 permintaan, 50 konkuren: 0 gagal, 15,6 req/s (A14) |
| Bug yang ditemukan | (1) CPU/RAM selalu "Tidak tersedia" di Windows: `escapeshellarg` merusak skrip → `-EncodedCommand`; (2) `ab` macet: CIM tiap permintaan → snapshot; (3) sumbu waktu grafik bercampur UTC/WIB dan urutan terbalik → label WIB seragam (A11 #6, #15, #16) |

## 4. Batas klaim

- Dibaca dari server Windows pengguna; di server yang dijalankan akun Windows lain (mis. server 8081 pada sesi sandbox) CIM ditolak dan CPU/RAM tampil `Tidak tersedia` — perilaku graceful failure yang dimaksud.
- Adapter Linux (`LinuxResourceReader`) ada tetapi **tidak diuji** pada Linux.
- Penjadwalan `monitor:collect` tiap menit lewat Task Scheduler belum diuji (A15); tanpa penjadwalan riwayat hanya terisi saat perintah dijalankan.
- Pengujian visual hanya Chrome dan Edge; Firefox belum (A12).
