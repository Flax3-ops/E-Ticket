# Status implementasi terhadap SRS

Tanggal pemeriksaan: 17 November 2026, kode `v1.1.1`. SRS tetap menjadi spesifikasi (v0.4). Dokumen ini mencatat hasil yang **benar-benar dijalankan**; rincian per subelemen ada di [MATRIKS-KETERLACAKAN.md](MATRIKS-KETERLACAKAN.md).

| Area | Status | Bukti / batas |
|---|---|---|
| Instalasi, migrasi (5), seeder | Berjalan | `migrate --all`; 10 pengguna/6 kategori/100 tiket/200 komentar |
| Login + OTP, peran, sesi | Terbukti | HTTP `AUTH-*`; akun nonaktif langsung ditolak; OTP salah 3× mengunci |
| Tiket, status, penugasan admin, prioritas, soft delete | Terbukti | HTTP `TKT-*`, `ACL-T*`; PHPUnit `TicketServiceTest` |
| Komentar dan lampiran | Terbukti | HTTP `KOM-*` (bug upload ditemukan dan diperbaiki, A11 #4) |
| Dashboard, grafik, slideshow | Terbukti (Chrome, Edge) | A12; Firefox hanya halaman login |
| Alert, notifikasi, acknowledge | Terbukti | `AlertServiceTest`, `A15-hasil-cli.txt`; jadwal Task Scheduler **belum didaftarkan/diuji** |
| Monitor resource | Terbukti di Windows akun pengguna | A16; bug pembaca CPU/RAM diperbaiki; adapter Linux tidak diuji |
| Ekspor Excel/PDF | Terbukti | HTTP `LAP-*` termasuk filter |
| Objek SQL, COMMIT/ROLLBACK, hak akun | Terbukti | A07, A08 |
| Performa 100.000 tiket | Terbukti, ada patch | A10: halaman pertama 1,97 s → 20 ms (v1.1) |
| 50 sesi serentak | Terbukti (50/50, 0 error) | A14; CPU jenuh, p95 0,8–2,0 s |
| Migrasi teknologi Unit 10 | Terbukti sebagian | tag `v0.9` → `v1.0`; baseline dibuat belakangan (dinyatakan) |
| Pembaruan/rollback Unit 17 | Terbukti | `A17-hasil-pembaruan.txt` |
| Analisis statis dan uji unit | Lulus | PHPStan level 5, PHPCS PSR-12, PHPUnit 29 tes/115 assertion, HTTP 119/119 |

## Yang belum terbukti (jujur)

1. Windows Task Scheduler (`scripts/register_tasks.ps1`) tidak dijalankan karena perubahan sistem persisten; harus dijalankan manual.
2. Uji sengaja menghentikan MariaDB (halaman gangguan F-NTF-12).
3. Firefox untuk halaman terautentikasi, grafik, dan slideshow; ponsel fisik.
4. Layar penuh slideshow pada monitor sebenarnya (hanya terbukti tombol memanggil API).
5. Review kode dan pengujian oleh pihak independen; uji pengguna manusia (Unit 4).
6. `LinuxResourceReader`; ekstrapolasi 1 juta baris.
7. Pengukuran NF-01 (p95 daftar tiket, satu pengguna, 30 permintaan) secara terpisah.

## Catatan lingkungan penting

- `.env` harus UTF-8 **tanpa BOM** (BOM membuat `CI_ENVIRONMENT` tidak terbaca; ditemukan dan diperbaiki).
- Server `spark serve` yang dijalankan akun Windows lain tidak boleh membaca CIM; jalankan server dengan akun Anda sendiri agar CPU/RAM terbaca.
- OPcache nonaktif pada `php.ini` XAMPP bawaan; mengaktifkannya menaikkan throughput ±2,3× pada uji ini (A02).
- Setelah memulihkan backup, jalankan `scripts/restore_db.ps1` (memasang ulang hak `EXECUTE`).
