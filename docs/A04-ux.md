# A04 — Perancangan user experience (Unit 4, J.620100.006.01)

Titik awal semua hitungan: **dashboard setelah login**. Satu "aksi" = satu klik (atau submit). Mengisi kolom teks/memilih opsi pada form yang sama tidak dihitung sebagai aksi terpisah; itu dicatat sebagai langkah. Pengukuran memakai **klik sungguhan** (`Input.dispatchMouseEvent` lewat CDP, `tests/browser/capture.mjs`) di Chrome 157 dan Edge 157 pada aplikasi v1.1; hasil mentah di `docs/screenshots/hasil-chrome.json` dan `hasil-edge.json`.

## 1. Kebutuhan interaksi dan batas aksi (elemen 1, 2.1)

| Skenario (UC) | Peran | Batas atas (SRS §7.1) | Urutan aksi yang diukur | Aksi nyata | Hasil |
|---|---:|---:|---|---:|---|
| Buat tiket (UC-01) | Pemohon | 4 | 1 klik "+ Tiket baru" → isi judul, deskripsi, kategori → 2 klik "Buat tiket" | **2** | Lulus; tiket tercipta (`/tickets/{id}`) |
| Ambil tiket | Petugas | 3 | 1 klik "Tiket" → 2 klik nomor tiket → 3 klik "Ambil tiket" | **3** | Lulus; status menjadi Diproses |
| Beri komentar | Petugas | 3 | 1 "Tiket" → 2 buka tiket → isi komentar → 3 "Kirim komentar" | **3** | Lulus; komentar tampil |
| Selesaikan tiket | Petugas | 3 | 1 "Tiket" → 2 buka tiket → isi catatan → 3 "Simpan status" | **3** | Lulus; status Selesai |
| Tutup tiket | Pemohon | 3 | 1 "Tiket" → 2 buka tiket → pilih "Tutup", isi catatan → 3 "Simpan status" | **3** | Lulus; status Ditutup |
| Ekspor laporan | Admin | 4 | 1 "Tiket" → 2 "Ekspor Excel" (unduhan) | **2** | Lulus; unduhan diperiksa terpisah (`LAP-01`) |

Hasil identik di Chrome dan Edge. Semua skenario di bawah batas atas atau tepat pada batasnya; tidak ada yang melampaui.

## 2. Layout yang memfasilitasi batas atas (elemen 2.2) dan wireframe

Dasar layout: navigasi atas tetap (Dashboard, Tiket, Notifikasi, Alert, Monitor, Pengguna, Kategori, Pengaturan, Log menurut peran) sehingga setiap modul **satu klik** dari halaman mana pun; tombol aksi utama ditempatkan pada konteksnya (tombol "+ Tiket baru" di dashboard Pemohon; "Ambil tiket", form status, komentar, dan lampiran di halaman detail).

Wireframe tekstual per halaman (tangkapan layar aktual ada di `docs/screenshots/`):

```text
Dashboard      : [Nav] / Judul + tombol aksi / banner alert (Petugas, Admin) / 4 kartu / 3 grafik
Daftar tiket   : [Nav] / Judul + "+ Tiket baru" (Pemohon) / filter (cari, status, prioritas, kategori) / tombol ekspor (Admin) / tabel / pagination
Form tiket     : [Nav] / Judul / Judul, Deskripsi, Kategori, Prioritas / "Buat tiket"
Detail tiket   : [Nav] / Kolom kiri: isi, komentar + form, lampiran + unggah; Kolom kanan: tindakan sesuai peran, riwayat status
Notifikasi     : [Nav] / daftar + tombol tandai dibaca
Alert          : [Nav] / tabel aturan, kejadian, status, tombol "Akui" (Admin)
Monitor        : [Nav] / 4 kartu metrik / grafik garis 60 titik + garis batas atas
Slideshow      : [Nav] / satu frame besar + progres / Sebelumnya, Jeda, Berikutnya, Layar penuh
```

Bukti layout responsif: `chrome-mobile360-*.png` (360 px) dan `chrome-desktop-*.png` (1366 px); tidak ada overflow horizontal pada 12 halaman × 2 lebar (kolom `overflow_horizontal` = `false` pada JSON hasil). Mode gelap: `chrome-desktop-dashboard-mode-gelap.png`; tombol Tema mengubah kelas `dark` (diuji otomatis).

## 3. Skenario lain dengan aksi di bawah batas atas (elemen 2.3)

| Skenario | Aksi | Catatan |
|---|---:|---|
| Pemohon melihat status tiketnya | 2 (Tiket → buka) | Dashboard menampilkan ringkasan per status |
| Admin mengakui alert | 2 (Alert → Akui) | Tombol langsung pada baris alert |
| Admin memindahkan penugasan | 3 (Tiket → buka → Simpan penugasan) | Alasan wajib |
| Melihat statistik layar penuh | 1 (Layar statistik) lalu 1 (Layar penuh) | Slideshow berjalan otomatis |

Batas atas untuk skenario tambahan ditetapkan < 4 (SRS §7.1); seluruhnya ≤ 3.

## 4. Catatan dan batas

- "Aksi" dihitung secara mekanis oleh skrip; uji pengguna manusia (waktu, kesalahan) **belum** dilakukan dan tidak diklaim.
- Skenario membuka ulang tiket (Selesai → Diproses, ≤ 7 hari) memakai form status yang sama; diuji fungsional di `A14-http-results.md` (`TKT-06d/e`), bukan pada hitungan aksi.
- Pemeriksaan izin dilakukan di server, bukan bergantung pada tombol yang disembunyikan (`ACL-*` pada A14).
