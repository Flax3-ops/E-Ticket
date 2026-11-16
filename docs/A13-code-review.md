# A13 — Code review (Unit 13, J.620100.032.001)

Pelaksana review: **penulis kode itu sendiri bersama asisten AI**; ini bukan review independen oleh pihak ketiga. Asesor atau rekan sebaiknya mengulang review pada `git diff v1.0 v1.1` bila review independen diperlukan.

Ketentuan acuan (1.1): SRS v0.4 (§4 fungsional, §5 NF-04/NF-10), PSR-12 (`phpcs.xml`), PHPStan level 5, praktik proyek: controller tipis dan logika di Service, izin diperiksa di server, input divalidasi, tanpa angka ajaib, kredensial di `.env`.

## 1. Temuan ketidaksesuaian dan perbaikan (elemen 1.2, 2.1, 2.2)

Perbaikan **tanpa mengubah perilaku/spesifikasi** dibuktikan: PHPUnit 29 tes (115 assertion) + uji HTTP 109/109 lulus sebelum dan sesudah, kecuali tes baru yang memang mengunci perilaku.

| # | Temuan (ketentuan yang dilanggar) | Berkas dan diff | Perbaikan | Verifikasi |
|---|---|---|---|---|
| R1 | Aturan edit tiket ditulis dua kali (`edit()` dan `update()`) dengan kondisi panjang; rawan tidak sinkron (best practice DRY, NF-10) | `Tickets.php` — `git diff v1.0 v1.1 -- app/Controllers/Tickets.php` | `TicketService::canEdit()` dipakai keduanya; kondisi sama persis | `TicketServiceTest::testCanEditPolicy`; HTTP `TKT-09`, `TKT-09c`, `ACL-T02` |
| R2 | Angka ajaib 7×86400, 900, 1800, 2 tersebar | `TicketService.php`, `AlertService.php` | Konstanta bernama `REOPEN_WINDOW_SECONDS`, `REMINDER_INTERVAL_SECONDS`, `CRITICAL_UNASSIGNED_SECONDS`, `NORMAL_CHECKS_TO_RESOLVE` | `AlertServiceTest` (throttle 15 menit, pulih 2 pemeriksaan), `testReopenWithinSevenDays…` |
| R3 | N+1 query: satu `SELECT assigned_to` per tiket terlambat di `AlertService::check()` (efisiensi) | `AlertService.php` | Satu query `v_ticket_sla JOIN tickets`; daftar admin diambil sekali | `AlertServiceTest`; `alert:check` 22 alert aktif identik (A15) |
| R4 | Respons galat plain-text tanpa kode (F-LOG-04, NF-12) | Controller/Filter | `friendly_error()` + `errors/friendly.php` (kode `E-ACL-403`, `E-TKT-404`, …), pesan di-escape | HTTP `LOG-04`, `LOG-04b` |
| R5 | Pemeriksaan `getMimeType()` setelah `move()` — bug fungsional (A11 #4) | `Attachments.php` — `git diff v0.8 v0.9-audit` | Baca MIME/ukuran sebelum `move()`, nama acak | HTTP `KOM-02/03/04` |
| R6 | `AuthFilter` tidak memeriksa akun nonaktif; rute admin melewati `RoleFilter` saja (NF-04) | Filter — `git diff v0.8 v0.9-audit` | Pemeriksaan `is_active` dan peran terbaru; `RoleFilter` memakai `AuthFilter` | HTTP `AUTH-ACT`, `AUTH-ACT2` |
| R7 | Logika filter daftar terduplikasi antara `Tickets::index` dan `Reports::tickets` | `git diff v0.9-audit v0.9` | Dipindah ke `TicketListQuery` | `TicketListQueryTest`, HTTP `LAP-01b` |
| R8 | Query halaman daftar lambat (performa, NF-02/A02) | `TicketListQuery.php` | Deferred join + index (A10 §4) | Benchmark A10; `TicketListQueryEquivalenceTest` |
| R9 | Cakupan analisis statis hanya Controllers/Services/Libraries/Commands | `phpstan.neon`, `phpcs.xml` | Ditambah Filters, Models, Entities, Helpers | PHPStan 0 error, PHPCS 0 error |
| R10 | Kondisi unduh lampiran admin untuk tiket soft delete ditolak padahal SRS §4.2.2 mengizinkan admin | `TicketService::canParticipate` | Admin selalu diizinkan | Uji akses `KOM-04-3` |

## 2. Pengecualian penulisan kode terhadap guideline (elemen 3)

| # | Kode | Guideline yang dilonggarkan | Alasan nyata | Komentar pada kode |
|---|---|---|---|---|
| P1 | `TicketListQuery::applyConditions()` — `where("... LIKE '…' ESCAPE '!'", null, false)` dan `MATCH … AGAINST` | "Pakai metode Query Builder, bukan SQL mentah" | `like()` tidak meng-escape wildcard (perbedaan terbukti oleh uji kesetaraan); FULLTEXT tidak punya API Query Builder. Nilai tetap melewati `escapeLikeString()`/`escape()` | Blok komentar `PENGECUALIAN GUIDELINE` di atas fragmen |
| P2 | `tests/_support/Legacy/RawTicketListQuery.php` | "Akses data hanya lewat Query Builder" | Salinan sengaja dipertahankan sebagai pembanding uji kesetaraan Unit 10 | Komentar kepala berkas: "SALINAN IMPLEMENTASI LAMA" |
| P3 | `phpcs.xml` — `Generic.Files.LineLength` dikecualikan | PSR-12: panjang baris | PSR-12 menyebutnya batas lunak; kueri berantai lebih terbaca satu baris | Komentar XML `PENGECUALIAN GUIDELINE` pada `phpcs.xml` |

Tidak ada pengecualian lain; setiap berkas di `app/` lolos PHPCS tanpa `phpcs:ignore`.

## 3. Hasil

PHPStan level 5: 0 error; PHPCS PSR-12: 0 error; PHPUnit 29 tes lulus; HTTP 109/109 lulus. Diff lengkap: `git diff v1.0 v1.1 -- app` (17 berkas, +215/−35). Review independen belum dilakukan.
