-- Migration for existing SimKlinik PRO database
USE simklinik;

ALTER TABLE tindakan ADD COLUMN harga_min INT DEFAULT NULL AFTER harga;
ALTER TABLE tindakan ADD COLUMN harga_max INT DEFAULT NULL AFTER harga_min;
ALTER TABLE tindakan ADD COLUMN satuan_harga VARCHAR(50) DEFAULT 'per tindakan' AFTER harga_max;
ALTER TABLE tindakan ADD COLUMN keterangan VARCHAR(255) DEFAULT NULL AFTER satuan_harga;
ALTER TABLE tindakan ADD COLUMN aktif ENUM('yes','no') DEFAULT 'yes' AFTER keterangan;

ALTER TABLE treatments ADD COLUMN kategori VARCHAR(100) NULL AFTER nama_tindakan;
ALTER TABLE invoice_items ADD COLUMN nama_tindakan VARCHAR(255) NULL AFTER treatment_id;

CREATE TABLE IF NOT EXISTS odontogram_tindakan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pasien_id INT NOT NULL,
  kunjungan_id INT NOT NULL,
  nomor_gigi VARCHAR(10) NOT NULL,
  surface_code VARCHAR(5) DEFAULT NULL,
  tindakan_id INT NOT NULL,
  nama_tindakan VARCHAR(255) NOT NULL,
  kategori VARCHAR(100) DEFAULT NULL,
  harga INT DEFAULT 0,
  qty INT DEFAULT 1,
  subtotal INT DEFAULT 0,
  satuan_harga VARCHAR(50) DEFAULT 'per tindakan',
  catatan TEXT DEFAULT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resume_medis (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pasien_id INT NOT NULL,
  kunjungan_id INT NOT NULL,
  keluhan_utama TEXT NULL,
  anamnesis TEXT NULL,
  pemeriksaan TEXT NULL,
  diagnosa VARCHAR(255) NULL,
  icd10_code VARCHAR(20) NULL,
  tindakan TEXT NULL,
  terapi TEXT NULL,
  instruksi TEXT NULL,
  catatan TEXT NULL,
  dokter_nama VARCHAR(150) NOT NULL,
  dokter_sip VARCHAR(100) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS surat_sakit (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pasien_id INT NOT NULL,
  kunjungan_id INT NOT NULL,
  nomor_surat VARCHAR(100) NOT NULL UNIQUE,
  tanggal_surat DATE NOT NULL,
  tanggal_mulai DATE NOT NULL,
  tanggal_selesai DATE NOT NULL,
  lama_istirahat INT NOT NULL DEFAULT 1,
  diagnosis_singkat VARCHAR(255) NULL,
  keterangan TEXT NULL,
  dokter_nama VARCHAR(150) NOT NULL,
  dokter_sip VARCHAR(100) NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO treatments (kode, nama_tindakan, kategori, harga)
SELECT t.kode, t.nama_tindakan, t.kategori, t.harga
FROM tindakan t
LEFT JOIN treatments tr ON tr.kode = t.kode
WHERE tr.id IS NULL;
