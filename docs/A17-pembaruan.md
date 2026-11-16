# A17 — Pembaruan perangkat lunak (Unit 17, J.620100.047.01)

Bukti utama: `docs/A17-hasil-pembaruan.txt` — keluaran nyata `bash scripts/demo_update.sh`, dijalankan 17 November 2026 pada worktree Git terpisah dan database `tiket_upgrade` / `tiket_upgrade_restore` (bukan `tiket_demo`). Pembaruan yang didemonstrasikan: **v1.0 yang sudah berjalan → v1.1**.

## 1. Diferensiasi perangkat lunak (elemen 1)

### 1.1 Perbedaan v1.0 → v1.1 (`git diff --stat v1.0 v1.1 -- app`: 17 berkas, +215/−35)

| Jenis | Perubahan | Berkas |
|---|---|---|
| Skema DB | Index `idx_active_list (deleted_at, status, priority)` pada `tickets`; kolom `alerts.acknowledged_at` | `app/Database/Migrations/2026-11-17-000005_ListIndexesAndAckTime.php` (`up()` dan `down()`) |
| Kinerja | Deferred join pada daftar tiket (halaman pertama 100.000 tiket 1,97 s → 20 ms; A10 §4) | `app/Models/TicketListQuery.php` |
| Hasil code review (A13) | `TicketService::canEdit`, konstanta bernama, hapus N+1 | `TicketService.php`, `AlertService.php`, `Tickets.php` |
| Fitur | Halaman galat berkode (`E-ACL-403`, `E-TKT-404`…), badge Lewat SLA, waktu acknowledge, Entity `Ticket`/`TicketModel` | `Helpers/error_helper.php`, `Views/errors/friendly.php`, `Entities/`, `Models/TicketModel.php`, `Views/*` |
| Tanpa perubahan | Dependensi Composer (`composer.lock` identik) | — |

Riwayat antar tag: `v0.8` → `v0.9-audit` → `v0.9` → `v1.0` → `v1.1` (`git log --oneline --decorate`; `git diff <tagA> <tagB>`). Catatan kejujuran: tag lama dibuat pada 16–17 Nov 2026 sesuai urutan kerja nyata (SRS §9); tidak ada riwayat yang dimundurkan.

### 1.2 Mekanisme penerapan (prosedur)

| Langkah | Perintah | Syarat lanjut |
|---|---|---|
| 1 Backup | `php spark backup:db` (memanggil `mysqldump --routines --triggers --single-transaction` dengan akun backup terpisah) | Berkas `.sql` > 0 byte |
| 2 Ambil kode | `git fetch --tags && git checkout v1.1` | Kode tag benar |
| 3 Dependensi | `composer install --no-dev` | Tidak ada galat |
| 4 Migrasi | `php spark migrate` (akun DBA) | `Migrations complete.` |
| 5 Uji asap | Login, buat tiket, dashboard (`tests/http/run.php`) | Semua lulus |
| 6 Bila gagal | `php spark migrate:rollback` → `git checkout v1.0` → bila perlu pulihkan backup dengan `scripts/restore_db.ps1` | Uji asap v1.0 lulus |

## 2. Patch dan penerapannya (elemen 2)

**2.1 Program pertambahan diferensiasi:** migrasi `ListIndexesAndAckTime` (berpasangan `up()`/`down()`), skrip pencadangan `BackupDb`, `scripts/restore_db.ps1`, `scripts/demo_update.sh` (menjalankan seluruh prosedur).

**2.2 Penerapan pada perangkat lunak yang berjalan** — hasil `A17-hasil-pembaruan.txt` (angka dikutip langsung):

| Tahap | Hasil |
|---|---|
| Pasang v1.0 (worktree di tag `v1.0`, `composer install`, `migrate --all`, `db:seed DemoSeeder`) | 4 migrasi tercatat; data awal `users=10 tickets=100 comments=200 ticket_logs=49 notifications=449`; `idx_active_list=0`, `alerts.acknowledged_at=0` |
| Uji asap v1.0 berjalan | **106/108** — dua yang gagal (`LOG-04`, `LOG-04b`) adalah fitur baru v1.1 (halaman galat berkode) sehingga memang belum ada di v1.0 |
| Backup (`backup:db`) | `tiket_upgrade-20261116-171429.sql`, 161.312 byte; data saat backup `tickets=102 comments=207 ticket_logs=55 notifications=464` |
| Patch: `git checkout v1.1`, `composer install`, `migrate` | `Running: 2026-11-17-000005 … ListIndexesAndAckTime`, `Migrations complete.`; skema `idx_active_list=3` (3 kolom) `acknowledged_at=1`; **jumlah data identik** sebelum/sesudah migrasi |
| Uji asap v1.1 | **109/109 lulus** |
| Rollback: `migrate:rollback` | `Rolling back: … ListIndexesAndAckTime` → `Done rolling back migrations`; skema kembali `idx_active_list=0 acknowledged_at=0 migrasi_tercatat=4` |
| Kembali ke kode v1.0 + uji asap | 106/108 (sama dengan sebelum patch); aplikasi berfungsi penuh pada keadaan lama |
| Restore backup ke database terpisah (`restore_db.ps1`) | `users=10 tickets=102 comments=207 ticket_logs=55 notifications=464` — **identik** dengan data saat backup; trigger 2, routine 4, view 6 ikut pulih; hak `tiket_app` dipasang ulang |
| Terapkan ulang v1.1 | Skema akhir `idx_active_list=3 acknowledged_at=1 migrasi_tercatat=5`, kode `v1.1` |

Temuan operasional dari proses ini: (1) setelah restore dump, hak `EXECUTE` routine hilang (A11 #3) — ditangani `restore_db.ps1`; (2) kegagalan koneksi karena password akun aplikasi kosong tercermin di log sebagai `Unable to connect to the database` dan aplikasi pulih setelah `.env` dikoreksi (sekaligus bukti graceful failure A15); (3) `rm -rf` pada worktree yang berisi *junction* ke `vendor` utama sempat menghapus sebagian `vendor` repo utama — dipulihkan dengan `composer install` dari `composer.lock` dan skrip diubah agar memasang `vendor` di worktree sendiri tanpa junction.

`tiket_demo` dan `tiket_test` kemudian dimigrasi memakai prosedur yang sama (`migrate` dengan akun DBA; `tiket_test` lewat `database/apply_migration.php`).

## 3. Batas klaim

- Pembaruan didemonstrasikan pada satu mesin, dengan data demo (±100 tiket); tidak ada uji pembaruan dengan lalu lintas pengguna aktif/tanpa-henti (*zero-downtime*).
- Rollback skema diuji untuk satu migrasi; `down()` migrasi lain (empat pertama) ada tetapi tidak diuji ulang pada demonstrasi ini.
- Backup memakai akun DBA lokal `root` tanpa password (bawaan XAMPP); pada lingkungan lain akun backup harus berpassword.
