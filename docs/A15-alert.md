# A15 — Alert notification (Unit 15, J.620100.044.01)

Kode: `app/Services/AlertService.php`, `app/Libraries/Notifier/*`, `app/Commands/AlertCheck.php`, `app/Controllers/Alerts.php`, tabel `alerts`/`notifications`. Bukti: `tests/unit/AlertServiceTest.php`, `docs/A15-hasil-cli.txt`, `A14-http-results.md` (`NTF-10*`).

## 1. Permasalahan yang membutuhkan tindakan cepat (elemen 1)

| Kode aturan | Kondisi (1.1) | Level | Informasi yang dicatat (1.2) | Penerima |
|---|---|---|---|---|
| `critical_unassigned` | Tiket Kritis berstatus Baru, belum ditugaskan > 30 menit (`CRITICAL_UNASSIGNED_SECONDS`) | critical | Nomor tiket, durasi, `object_key = ticket:<id>` | Semua Petugas dan Admin aktif |
| `sla_overdue` | Tiket terbuka dengan `sla_due_at` lewat (`v_ticket_sla`) | critical | Nomor tiket | Petugas yang ditugaskan (bila ada) dan Admin |
| `app_error` | Baris log `ERROR`/`CRITICAL` dalam 2 menit terakhir | critical | Pesan (180 karakter), kode `E-…` | Admin |
| `resource` | CPU > 85%, RAM > 85%, disk > 90%, koneksi DB > 80% dari maksimum (ambang di `settings`) | critical | Nama metrik dan nilai (%) | Admin |
| `reader` | Pembaca metrik OS gagal (`status = unavailable`) | critical | "Pembaca metrik OS tidak tersedia" | Admin |

Setiap alert menyimpan: `rule_code`, `object_key`, `active_key` (unik; NULL saat pulih), `message`, `occurrences`, `first_seen_at`, `last_seen_at`, `last_notified_at`, `normal_checks`, `resolved_at`, `acknowledged_by`, `acknowledged_at` (v1.1).

## 2. Mekanisme pengiriman dan frekuensi (elemen 2)

- **Mekanisme (2.1):** `AlertService` → `NotifierInterface` → `DatabaseNotifier` menulis baris `notifications`; ikon lonceng + badge membaca `/api/notifications/unread` tiap 30 detik; banner merah di dashboard Petugas/Admin dan halaman `/alerts`. Tanpa email/Telegram (di luar lingkup). Penjadwalan: `php spark alert:check` tiap menit.
- **Frekuensi (2.2):** alert aktif pertama langsung mengirim notifikasi; selama belum pulih, pemeriksaan berikutnya menaikkan `occurrences` dan memperbarui `last_seen_at`, sedangkan **pengingat dikirim paling sering sekali per 15 menit** (`REMINDER_INTERVAL_SECONDS = 900`). Satu `rule_code + object_key` hanya punya satu alert aktif (indeks unik `active_key`). Acknowledge admin tidak memulihkan kondisi.

Bukti nyata (`A15-hasil-cli.txt`, `alert:check` dijalankan 3× berturut-turut dalam 3 detik pada 22 kondisi): setiap alert tercatat `occurrences = 3` dengan **satu** baris aktif per objek (bukan 3 baris). Uji unit `testReminderIsThrottledToOncePerFifteenMinutes`: tiga pemeriksaan → tepat satu notifikasi; setelah `last_notified_at` digeser 901 detik → notifikasi kedua; hanya satu alert aktif.

## 3. Graceful failure dan pemulihan (elemen 3)

| Skenario | Perilaku | Bukti |
|---|---|---|
| Pembaca metrik OS gagal (3.1) | `MonitorService` menangkap pengecualian, menulis `E-MON-001`, mengembalikan `status = unavailable` dan nilai `null` (bukan 0, bukan simulasi); aturan ambang metrik itu **tidak dievaluasi**; alert `reader:os` dibuat; fitur tiket tetap berjalan | `AlertServiceTest::testUnavailableReaderDoesNotUseSimulatedValuesAndRecovers`; log `E-MON-001 Metrik Windows tidak tersedia`; halaman monitor menampilkan "Tidak tersedia" (screenshot sebelum perbaikan) |
| Pembaca kembali normal (3.2) | Setelah **dua** pemeriksaan normal berturut-turut alert `reader:os` ditandai pulih (`resolved_at`, `active_key = NULL`) dan satu notifikasi pemulihan dikirim; masalah yang muncul lagi membuat alert baru | Uji unit yang sama; `testRepeatedAlertAndTwoNormalChecksResolveIt` (CPU 95% → 20%) |
| Koneksi DB gagal | Halaman gangguan, log lokal, layanan kembali setelah DB tersedia (F-NTF-12). Terlihat nyata di log uji: "Error connecting to the database … Unable to connect to the database" saat password akun aplikasi salah (A17) dan aplikasi pulih setelah `.env` diperbaiki | Log `writable/logs`, **uji henti-DB sengaja belum dilakukan** (lihat batas) |
| Pembaca metrik berhasil | Setelah perbaikan pembaca (`-EncodedCommand`), CPU/RAM terbaca di halaman monitor (12%/63,5%) | `chrome-desktop-monitor.png` |

Acknowledge (F-NTF-10): admin menekan "Akui" → `acknowledged_by` + `acknowledged_at` terisi, alert tetap aktif; petugas ditolak 403 (`NTF-10a/b/c`).

## 4. Batas klaim

- `schtasks`/Windows Task Scheduler **belum diuji**: pendaftaran tugas berulang adalah perubahan sistem persisten yang tidak dijalankan otomatis. Skrip `scripts/register_tasks.ps1` tersedia dan harus dijalankan manual oleh pengguna; `php spark alert:check` dan `monitor:collect` sendiri telah dijalankan (`A15-hasil-cli.txt`).
- Uji menghentikan MariaDB untuk melihat halaman gangguan dan pemulihannya belum dilakukan (berisiko bagi database lain di XAMPP yang sama); yang terbukti baru kegagalan koneksi karena kredensial salah dan pemulihan setelah dikoreksi.
- Pemicu "error aplikasi" bergantung pada format log CI4 dan jendela 2 menit.
