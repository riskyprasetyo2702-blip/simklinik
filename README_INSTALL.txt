Paket SIM Klinik Cloud - pasien, kunjungan, invoice, resume medis, surat sakit

FILE DALAM PAKET:
- bootstrap.php
- pasien.php
- simpan_pasien.php
- kunjungan.php
- simpan_kunjungan.php
- invoice.php
- simpan_invoice.php
- invoice_pdf.php
- resume_medis.php
- surat_sakit.php

CARA PASANG:
1. Upload semua file ke folder project cloud yang sama dengan config.php dan dashboard.php.
2. Pastikan config.php menghasilkan salah satu variabel koneksi berikut:
   - $conn
   - $koneksi
   - $mysqli
   - $db
   - $pdo
3. Setelah upload, buka:
   - pasien.php
   - kunjungan.php
   - invoice.php
4. bootstrap.php akan otomatis membuat tabel inti bila belum ada:
   - pasien
   - kunjungan
   - invoice
   - invoice_items

ALUR KERJA:
- pasien.php -> tambah/edit pasien
- kunjungan.php -> pilih pasien, input kunjungan
- invoice.php -> pilih pasien / kunjungan, tambah item tindakan dan simpan invoice
- invoice_pdf.php?id=ID -> print invoice
- resume_medis.php?kunjungan_id=ID -> print resume medis
- surat_sakit.php?kunjungan_id=ID -> print surat sakit

CATATAN PENTING:
- Saya sengaja tidak mengubah alur kerja besar. Struktur dibuat aman untuk cloud dan tetap sederhana seperti localhost.
- Jika localhost lama memakai nama kolom/tabel tambahan, file ini tetap bisa dijadikan base. Tinggal sesuaikan field tambahan bila diperlukan.
- invoice_pdf.php memakai mode print browser agar langsung bisa Save as PDF tanpa library tambahan.
