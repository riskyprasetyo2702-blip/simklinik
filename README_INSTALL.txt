SIMKLINIK CLOUD UPGRADE

File inti yang diganti:
- bootstrap.php
- dashboard.php
- pasien.php
- simpan_pasien.php
- kunjungan.php
- simpan_kunjungan.php
- odontogram.php
- simpan_odontogram.php
- invoice.php
- simpan_invoice.php
- invoice_pdf.php
- resume_medis.php
- surat_sakit.php
- pasien_history.php

Catatan penting:
1. Sistem ini memakai config.php cloud yang sudah ada.
2. bootstrap.php akan otomatis membuat tabel inti jika belum ada dan mengisi master tindakan + ICD-10 dasar.
3. Harga yang ditanam sudah mengikuti daftar yang Anda kirim.
4. Invoice PDF dibuat dengan print browser / Save as PDF tanpa library tambahan.
5. Untuk tampilkan gambar QRIS, isi konstanta QRIS_IMAGE_URL di bootstrap.php.
6. Pastikan file lama yang bentrok diganti dengan versi baru ini.
