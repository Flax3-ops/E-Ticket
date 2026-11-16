# Matriks keterlacakan elemen kompetensi — status bukti aktual

Tanggal: 17 November 2026, kode `v1.1.1`. Legenda status: **Terbukti** = ada artefak + hasil eksekusi/pengukuran nyata yang dapat diperiksa; **Terbukti sebagian** = ada bukti, tetapi ada batas yang dinyatakan; **Diimplementasikan** = kode ada tetapi belum diuji menyeluruh; **Direncanakan** = baru rencana. "Terbukti" berarti *bukti tersedia untuk diperiksa asesor*, bukan keputusan kompetensi (itu wewenang asesor). Nomor elemen mengacu `daftar-17-unit.md`.

| Unit | Subelemen | Status | Bukti (dokumen → berkas) | Batas / yang belum |
|---|---|---|---|---|
| 1 Tools | 1.1, 1.2, 2.1, 2.2, 2.3 | Terbukti | A01 → versi alat, hasil uji tools, risiko yang benar-benar terjadi | — |
| 2 Skalabilitas | 1.1, 1.2, 2.1, 2.2, 2.4 | Terbukti | A02 → benchmark 100.000 tiket (`A10-*.json`), uji 50 sesi (`A14-stress-*`) | Ekstrapolasi 1 juta/10 juta baris tidak diukur |
| | 2.3 (kebutuhan hardware) | Terbukti sebagian | A02 §4 | Skala 10× adalah estimasi berlabel |
| 3 Library | 1.1, 1.2, 2.1, 2.2, 3.1, 3.2 | Terbukti | A03 → `poc/`, angka ukuran/waktu | PoC visual dua grafik tidak direkam sebagai screenshot terpisah |
| 4 UX | 1.1, 1.2, 2.1, 2.2, 2.3 | Terbukti | A04 → hitungan klik sungguhan di Chrome & Edge, `docs/screenshots/` | Uji pengguna manusia belum ada |
| 5 Terstruktur | 1.x, 2.x, 3.1–3.4, 4.1–4.4, 5.1, 5.5 | Terbukti | A05 → `util/hasil-eksekusi.txt` | — |
| | 6.1, 6.2 (koreksi sintaks) | Terbukti sebagian | `util/koreksi-sintaks/`, `hasil-php-l.txt` | Kesalahan dibuat sebagai latihan (dinyatakan), bukan spontan |
| 6 OOP | 1.1–1.4, 2.x, 3.1, 3.2, 4.1, 4.2, 5.1, 5.2 | Terbukti | A06 → `app/`, `oop-demo/`, `TicketEntityTest` | — |
| | 3.3 Overloading | Terbukti sebagian | `oop-demo` `__call()` | PHP tidak punya overloading native (dinyatakan) |
| 7 SQL | 1.1–8.2 | Terbukti | A07 → `database/sql/08_uji_objek.sql`, `A07-hasil-eksekusi.txt` | Dijalankan di `tiket_test` dengan akun DBA |
| 8 Akses DB | 1.1–1.3, 2.1, 2.2, 3.1–3.3, 4.1–4.4 | Terbukti | A08 → `A08-show-grants.txt`, benchmark, uji HTTP | Akun DBA lokal `root` tanpa password (dinyatakan) |
| 9 Algoritma | 1.1–1.3, 2.1–2.4, 3.1, 3.2, 4.1–4.3, 5.1, 5.2 | Terbukti | A09 → `algoritma/`, `A09-benchmark.csv`, flowchart Mermaid | — |
| 10 Migrasi | 1.1–1.3, 2.1, 2.2 | Terbukti sebagian | A10 → tag `v0.9` vs `v1.0`, uji kesetaraan, benchmark satu sesi | Baseline raw dibuat belakangan khusus untuk Unit 10 (dinyatakan terbuka di A10 §0 dan SRS §9) |
| 11 Debugging | 1.1, 1.2, 2.1–2.3, 3.1, 3.2 | Terbukti | A11 → 16 bug nyata dengan log dan commit | Tangkapan layar debugger interaktif (Xdebug) tidak ada; yang ada: log, Toolbar, konsol browser |
| 12 Multimedia | 1.x, 2.1–2.3, 3.1–3.5, 4.1–4.3 | Terbukti | A12 → `slideshow.js` (berkomentar), storyboard, uji Chrome & Edge | — |
| | 5.1–5.4 (kompatibilitas) | Terbukti sebagian | A12 §5 | Firefox hanya screenshot login; ponsel fisik tidak diuji |
| 13 Code review | 1.1, 1.2, 2.1, 2.2, 3.1, 3.2 | Terbukti sebagian | A13 → R1–R10, P1–P3, `git diff v1.0 v1.1` | Review dikerjakan penulis + AI, bukan pihak independen |
| 14 Pengujian | 1.1–1.3, 2.1–2.3, 3.1–3.3, 4.2–4.4, 5.1, 5.2, 6.1–6.4 | Terbukti | A14 → PHPUnit 29 tes, HTTP 119/119, stress 50 sesi | Satu laptop; klien uji berbagi CPU |
| 15 Alert | 1.1, 1.2, 2.1, 2.2, 3.2 | Terbukti | A15 → `AlertServiceTest`, `A15-hasil-cli.txt` | — |
| | 3.1 Graceful failure | Terbukti sebagian | A15 §3 | Penghentian MariaDB sengaja belum diuji; Task Scheduler belum didaftarkan |
| 16 Monitoring | 1.1, 1.2, 2.1, 2.2 | Terbukti | A16 → `*-monitor.png`, `monitor:collect` | Adapter Linux tidak diuji; penjadwalan belum |
| 17 Pembaruan | 1.1, 1.2, 2.1, 2.2 | Terbukti | A17 → `A17-hasil-pembaruan.txt`, tag v1.0→v1.1, migrasi `down()`, restore | Satu mesin, tanpa lalu lintas aktif |

## Kebutuhan fungsional prioritas W (audit)

Seluruh kebutuhan berprioritas W pada SRS §4 diperiksa terhadap kode dan uji HTTP lintas peran (`A14-http-results.md`, 119 pemeriksaan). Pemetaan ID → uji: AUTH-01…07 (`AUTH-*`), TKT-01…12 (`TKT-*`, `ACL-T*`), KOM-01…04 (`KOM-*`), MST-01…03 (`MST-*`; SLA berubah → `A07` langkah 3), DSB-01…04 (`DSB-03*`, A12), NTF-01…12 (`NTF-10*`, `AlertServiceTest`, A15), MON-01…05 (A16, `ACL-OK-02`), LAP-01/02 (`LAP-*`), LOG-01…04 (`LOG-04*`, `AUTH-07`). Pengecualian yang **belum** terbukti: F-NTF-12 (uji henti-DB), F-NTF-09 (jadwal tiap menit), F-DSB-05 (layar penuh sebenarnya di monitor).
