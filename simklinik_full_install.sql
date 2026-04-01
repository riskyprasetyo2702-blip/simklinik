-- Fresh install schema for SimKlinik PRO
CREATE DATABASE IF NOT EXISTS simklinik CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simklinik;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(150) NOT NULL,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'admin',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  no_rm VARCHAR(50) NOT NULL UNIQUE,
  nama VARCHAR(150) NOT NULL,
  nik VARCHAR(50) NULL,
  jenis_kelamin VARCHAR(20) NULL,
  tanggal_lahir DATE NULL,
  no_hp VARCHAR(50) NULL,
  alamat TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  dokter_id INT NULL,
  tanggal_kunjungan DATETIME NOT NULL,
  keluhan TEXT NULL,
  subjective TEXT NULL,
  objective TEXT NULL,
  assessment TEXT NULL,
  plan TEXT NULL,
  diagnosa TEXT NULL,
  icd10_code VARCHAR(20) NULL,
  icd10_nama VARCHAR(255) NULL,
  status_kunjungan VARCHAR(50) DEFAULT 'draft',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_visits_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_visits_dokter FOREIGN KEY (dokter_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE tindakan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(30) NOT NULL UNIQUE,
  nama_tindakan VARCHAR(255) NOT NULL,
  kategori VARCHAR(100) NOT NULL,
  harga INT DEFAULT 0,
  harga_min INT DEFAULT NULL,
  harga_max INT DEFAULT NULL,
  satuan_harga VARCHAR(50) DEFAULT 'per tindakan',
  keterangan VARCHAR(255) DEFAULT NULL,
  aktif ENUM('yes','no') DEFAULT 'yes',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE treatments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(30) NOT NULL UNIQUE,
  nama_tindakan VARCHAR(255) NOT NULL,
  kategori VARCHAR(100) DEFAULT NULL,
  harga DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE invoices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL UNIQUE,
  nomor_invoice VARCHAR(50) NOT NULL UNIQUE,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  diskon DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  metode_bayar VARCHAR(50) NULL,
  status_bayar VARCHAR(50) NOT NULL DEFAULT 'pending',
  tanggal_invoice DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  catatan TEXT NULL,
  CONSTRAINT fk_invoices_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  invoice_id INT NOT NULL,
  treatment_id INT NOT NULL,
  nama_tindakan VARCHAR(255) NULL,
  qty INT NOT NULL DEFAULT 1,
  harga DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  tooth_number VARCHAR(5) NULL,
  surface_code VARCHAR(5) NULL,
  sumber VARCHAR(30) NULL,
  CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
  CONSTRAINT fk_invoice_items_treatment FOREIGN KEY (treatment_id) REFERENCES treatments(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE odontogram_surfaces (
  id INT AUTO_INCREMENT PRIMARY KEY,
  visit_id INT NOT NULL,
  tooth_number VARCHAR(10) NOT NULL,
  surface_code VARCHAR(5) NOT NULL,
  condition_code VARCHAR(30) NOT NULL,
  status_type VARCHAR(30) NOT NULL DEFAULT 'completed',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_visit_surface (visit_id, tooth_number, surface_code),
  CONSTRAINT fk_odontogram_surfaces_visit FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE odontogram_tindakan (
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
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_odontogram_tindakan_patient FOREIGN KEY (pasien_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_odontogram_tindakan_visit FOREIGN KEY (kunjungan_id) REFERENCES visits(id) ON DELETE CASCADE,
  CONSTRAINT fk_odontogram_tindakan_tindakan FOREIGN KEY (tindakan_id) REFERENCES tindakan(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE resume_medis (
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
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_resume_patient FOREIGN KEY (pasien_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_resume_visit FOREIGN KEY (kunjungan_id) REFERENCES visits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE surat_sakit (
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
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_surat_patient FOREIGN KEY (pasien_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_surat_visit FOREIGN KEY (kunjungan_id) REFERENCES visits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE keuangan (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  jenis VARCHAR(50) NOT NULL,
  deskripsi VARCHAR(255) NOT NULL,
  nominal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  invoice_id INT NULL,
  patient_id INT NULL,
  CONSTRAINT fk_keuangan_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
  CONSTRAINT fk_keuangan_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE icd10 (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(20) NOT NULL,
  diagnosis VARCHAR(255) NOT NULL,
  UNIQUE KEY uniq_icd10_kode (kode)
) ENGINE=InnoDB;

INSERT INTO users (id, nama, username, password, role) VALUES (1, 'Administrator', 'admin', '$2y$10$9m70c0yN7p0C4t4k3u7R4.6n2J2m8nU3Xk3n7h7fYQ8eWg2x3YB1i', 'admin');
