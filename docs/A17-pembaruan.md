# A17 — Pembaruan perangkat lunak

Migrasi memiliki `up()` dan `down()`. `backup:db` berhasil membuat dump termasuk view/routine/trigger melalui akun backup terpisah. Prosedur pembaruan: backup → checkout versi → `composer install` → `php spark migrate` dengan akun DBA → uji asap → bila gagal rollback migrasi dan pulihkan backup.

Belum ada tag v0.1/v0.5/v1.0/v1.1 atau diff antar rilis yang autentik. Patch v1.1, penerapan pada versi sebelumnya, dan demonstrasi rollback lengkap masih harus dilakukan. Dokumen ini bukan klaim bahwa Unit 17 sudah terpenuhi.
