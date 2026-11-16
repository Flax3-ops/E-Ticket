# Hasil uji HTTP lintas peran

Dijalankan: 2026-11-16 16:32 UTC — `php tests/http/run.php http://localhost:8082`

Ringkasan: **106 dari 106** pemeriksaan lulus.

| ID | Pemeriksaan | Hasil |
|---|---|---|
| AUTH-01 | Login password + OTP berhasil untuk admin (dashboard 200) | Lulus |
| AUTH-06a | Anonim ke /tickets dialihkan ke /login | Lulus |
| API-403a | Anonim ke /api/dashboard/summary -> 403 | Lulus |
| API-403b | Anonim ke /monitor/metrics -> 403 | Lulus |
| ACL-01 | pemohon GET /monitor ditolak (403) | Lulus |
| ACL-02 | pemohon GET /monitor/metrics ditolak (403) | Lulus |
| ACL-03 | pemohon GET /admin/users ditolak (403) | Lulus |
| ACL-04 | pemohon GET /admin/settings ditolak (403) | Lulus |
| ACL-05 | pemohon GET /admin/logs ditolak (403) | Lulus |
| ACL-06 | pemohon GET /reports/tickets.xlsx ditolak (403) | Lulus |
| ACL-07 | pemohon GET /reports/monthly.pdf ditolak (403) | Lulus |
| ACL-08 | pemohon GET /alerts ditolak (403) | Lulus |
| ACL-09 | pemohon GET /api/alerts/active ditolak (403) | Lulus |
| ACL-10 | pemohon GET /slideshow ditolak (403) | Lulus |
| ACL-11 | petugas GET /monitor ditolak (403) | Lulus |
| ACL-12 | petugas GET /admin/users ditolak (403) | Lulus |
| ACL-13 | petugas GET /reports/tickets.xlsx ditolak (403) | Lulus |
| ACL-14 | petugas GET /tickets/new ditolak (403) | Lulus |
| ACL-15 | petugas GET /slideshow ditolak (403) | Lulus |
| ACL-16 | admin GET /tickets/new ditolak (403) | Lulus |
| ACL-OK-01 | Akses sah GET /monitor -> 200 | Lulus |
| ACL-OK-02 | Akses sah GET /monitor/metrics -> 200 | Lulus |
| ACL-OK-03 | Akses sah GET /admin/users -> 200 | Lulus |
| ACL-OK-04 | Akses sah GET /admin/categories -> 200 | Lulus |
| ACL-OK-05 | Akses sah GET /admin/settings -> 200 | Lulus |
| ACL-OK-06 | Akses sah GET /admin/logs -> 200 | Lulus |
| ACL-OK-07 | Akses sah GET /slideshow -> 200 | Lulus |
| ACL-OK-08 | Akses sah GET /alerts -> 200 | Lulus |
| ACL-OK-09 | Akses sah GET /alerts -> 200 | Lulus |
| ACL-OK-10 | Akses sah GET /api/alerts/active -> 200 | Lulus |
| ACL-OK-11 | Akses sah GET /tickets/new -> 200 | Lulus |
| ACL-OK-12 | Akses sah GET /notifications -> 200 | Lulus |
| ACL-OK-13 | Akses sah GET /api/notifications/unread -> 200 | Lulus |
| ACL-OK-14 | Akses sah GET /api/dashboard/summary -> 200 | Lulus |
| TKT-01 | Pemohon membuat tiket valid -> redirect ke detail | Lulus |
| TKT-02 | Nomor tiket TKT-YYYYMMDD-XXXX tampil | Lulus |
| SEC-XSS | Judul berisi <script> di-escape pada halaman detail | Lulus |
| TKT-01b | Tiket invalid (judul/deskripsi pendek) ditolak | Lulus |
| TKT-01c | Kategori tidak ada ditolak | Lulus |
| TKT-03 | sla_due_at = created_at + 4 jam (Kritis) | Lulus |
| ACL-T01 | Pemohon lain tidak bisa melihat tiket (404) | Lulus |
| ACL-T02 | Pemohon lain tidak bisa edit (403) | Lulus |
| ACL-T03 | Pemohon lain tidak bisa komentar (403) | Lulus |
| ACL-T04 | Pemohon lain tidak bisa ubah status (tidak ada perubahan) | Lulus |
| ACL-T05-take | Pemohon POST /take ditolak (403) | Lulus |
| ACL-T05-priority | Pemohon POST /priority ditolak (403) | Lulus |
| ACL-T06-priority | Petugas POST /priority ditolak (403) | Lulus |
| ACL-T05-delete | Pemohon POST /delete ditolak (403) | Lulus |
| ACL-T06-delete | Petugas POST /delete ditolak (403) | Lulus |
| ACL-T05-assign | Pemohon POST /assign ditolak (403) | Lulus |
| ACL-T06-assign | Petugas POST /assign ditolak (403) | Lulus |
| TKT-04 | Daftar tiket dengan pencarian judul 200 | Lulus |
| TKT-09 | Pemohon pemilik mengubah tiket Baru | Lulus |
| TKT-09b | Prioritas tidak berubah lewat edit pemohon | Lulus |
| TKT-05 | Dua petugas mengambil bersamaan: tepat satu berhasil (1 log Diproses) | Lulus |
| TKT-05b | Petugas kedua mendapat pesan tiket sudah diambil | Lulus |
| ACL-T07 | Petugas lain tetap boleh melihat tiket aktif (200) | Lulus |
| ACL-T08 | Petugas bukan penerima tidak bisa komentar (403) | Lulus |
| ACL-T09 | Petugas bukan penerima tidak bisa menyelesaikan | Lulus |
| KOM-01 | Petugas penerima menambah komentar | Lulus |
| TKT-06a | Selesai tanpa catatan ditolak | Lulus |
| TKT-07 | Transisi tidak sah Diproses->Ditutup ditolak | Lulus |
| TKT-09c | Pemohon tidak boleh edit setelah status bukan Baru (403) | Lulus |
| KOM-03a | Berkas palsu (MZ berekstensi png) ditolak lewat validasi MIME | Lulus |
| KOM-02a | Berkas > 2 MB ditolak | Lulus |
| KOM-02b | PNG valid diunggah pemilik | Lulus |
| KOM-03b | Nama berkas diacak (hex 32 + ekstensi), bukan nama asli | Lulus |
| KOM-02c | Lampiran ke-4 ditolak (maks 3) | Lulus |
| KOM-02d | Pemohon lain tidak bisa mengunggah | Lulus |
| KOM-04-1 | Unduh lampiran oleh pemohon pemilik -> 200 | Lulus |
| KOM-04-2 | Unduh lampiran oleh petugas penerima -> 200 | Lulus |
| KOM-04-3 | Unduh lampiran oleh admin -> 200 | Lulus |
| KOM-04-4 | Unduh lampiran oleh pemohon lain -> 404 | Lulus |
| KOM-04-5 | Unduh lampiran oleh petugas bukan penerima -> 404 | Lulus |
| KOM-04-6 | Unduh lampiran oleh anonim -> 302 | Lulus |
| TKT-06b | Petugas penerima menyelesaikan dengan catatan | Lulus |
| TKT-06c | Pemohon lain tidak bisa membuka ulang | Lulus |
| TKT-06d | Pemohon pemilik membuka ulang dalam 7 hari | Lulus |
| TKT-06e | Buka ulang > 7x24 jam ditolak | Lulus |
| TKT-06f | Pemohon menutup tiket Selesai | Lulus |
| TKT-06g | Tiket Ditutup final: pemohon dan admin tidak bisa membuka ulang | Lulus |
| TKT-08 | Setiap perubahan status tercatat trigger dengan pelaku | Lulus |
| TKT-11 | Admin ubah prioritas -> SLA dihitung ulang dari created_at (72 -> 4 jam) | Lulus |
| TKT-11b | Ubah prioritas tanpa alasan ditolak | Lulus |
| TKT-12 | Admin menugaskan petugas dengan alasan (assigned_to terisi, status Diproses) | Lulus |
| TKT-12b | Penugasan tanpa alasan ditolak | Lulus |
| TKT-10 | Admin soft delete tiket | Lulus |
| TKT-10-1 | Tiket soft delete dilihat pemohon pemilik -> 404 | Lulus |
| TKT-10-2 | Tiket soft delete dilihat petugas -> 404 | Lulus |
| TKT-10-3 | Tiket soft delete dilihat admin -> 200 | Lulus |
| TKT-10d | Tiket soft delete tidak muncul di daftar | Lulus |
| DSB-03 | Endpoint summary memuat cards, by_status, by_category, by_day | Lulus |
| DSB-03b | Jumlah by_status = tiket aktif (tanpa duplikasi, tanpa soft delete) | Lulus |
| DSB-03c | Dashboard pemohon hanya menghitung tiket miliknya | Lulus |
| LAP-01 | Ekspor Excel semua tiket aktif | Lulus |
| LAP-01b | Ekspor Excel terfilter sesuai jumlah filter | Lulus |
| LAP-02 | Ekspor PDF bulanan (PDF valid) | Lulus |
| LAP-02b | Bulan tidak valid -> 400 | Lulus |
| NTF-10a | Petugas tidak boleh acknowledge (403) | Lulus |
| NTF-10b | Admin acknowledge: acknowledged_by terisi, alert belum pulih | Lulus |
| AUTH-ACT | Sesi pengguna yang dinonaktifkan admin langsung ditolak | Lulus |
| AUTH-ACT2 | Login pengguna nonaktif ditolak | Lulus |
| AUTH-04 | OTP salah 3x mengunci akun: login benar berikutnya ditolak | Lulus |
| AUTH-07 | Log umum tidak memuat nilai OTP mentah | Lulus |
| AUTH-04b | locked_until diisi ~10 menit ke depan | Lulus |
| AUTH-05 | Logout menghapus sesi | Lulus |
