# A15 — Alert notification

Aturan: tiket Kritis belum diambil >30 menit, tiket lewat SLA, error aplikasi, CPU/RAM/disk/DB melewati ambang, dan pembaca metrik gagal. Penerima, frekuensi, `active_key`, `occurrences`, dan dua pemeriksaan pemulihan dijelaskan di SRS §4.6. `php spark alert:check` berhasil dijalankan; PHPUnit `AlertServiceTest` membuktikan satu alert aktif berulang lalu pulih setelah dua pemeriksaan normal.

Graceful failure teramati: WMI/CIM gagal di lingkungan ini, monitor menyajikan `Tidak tersedia`, disk/DB masih terbaca, dan fitur tiket tetap berfungsi. Saat DB gagal, halaman gangguan dan pemulihan layanan baru perlu diuji manual. Penjadwalan Windows Task Scheduler belum dibuat pada perangkat pengguna.
