# A16 — Pemantauan resource

Sumber: adapter OS untuk CPU/RAM, fungsi PHP untuk disk fallback, MariaDB `Threads_connected` dan `max_connections`, query uji untuk waktu respons, PHP untuk memori proses. Batas atas CPU/RAM 85%, disk 90%, koneksi DB 80%. Halaman `/monitor` memperbarui tiap 5 detik, mempertahankan 60 titik, dan menggambar garis batas. `monitor:collect` menyimpan metrik tiap pemanggilan; penjadwalan per menit masih perlu diaktifkan.

Pada lingkungan ini akses WMI/CIM ditolak; CPU/RAM tampil `Tidak tersedia` (bukan nol atau angka simulasi). Kondisi tersebut memicu alert pembaca metrik dan tidak menghalangi tiket. Visualisasi lintas browser dan perangkat minimum belum direkam.
