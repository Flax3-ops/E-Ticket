# A13 — Code review

Ketentuan: SRS, pembatasan akses server, validasi input, PSR-12, penyimpanan kredensial di `.env`, dan transaksi untuk status. Temuan dan perbaikan selama review:

| Temuan | Perbaikan | Verifikasi |
|---|---|---|
| Hak akses file berisiko jika hanya tombol disembunyikan | `TicketService::canParticipate()` dipakai pada upload/unduh | Pemeriksaan kode; uji peran manual masih perlu |
| `MATCH ... AGAINST` tidak bisa dipakai sebelum index | Index dibuat migrasi v1.1; benchmark sebelum memakai LIKE | A08 JSON |
| Backup dengan akun aplikasi membutuhkan hak tambahan | Akun backup dipisah dari akun aplikasi | `backup:db` berhasil |
| PHPCS menandai panjang baris sebagai peringatan | Panjang baris dikecualikan di `phpcs.xml`; PSR-12 memberi batas lunak, bukan kesalahan sintaks | PHPCS keluar kode 0 |

Pengecualian guideline hanya dicatat pada konfigurasi PHPCS di atas; komentar pengecualian dalam kode produksi belum diperlukan. Review independen dan diff commit sebelum/sesudah patch masih perlu dibuat untuk bukti asesmen.
