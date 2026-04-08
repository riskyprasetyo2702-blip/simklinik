<?php
require_once 'config.php';

$sql = "
CREATE TABLE IF NOT EXISTS settings_klinik (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nama_klinik VARCHAR(255) DEFAULT NULL,
    alamat_klinik TEXT DEFAULT NULL,
    telepon_klinik VARCHAR(100) DEFAULT NULL,
    email_klinik VARCHAR(150) DEFAULT NULL,
    logo_path VARCHAR(255) DEFAULT NULL,
    qris_path VARCHAR(255) DEFAULT NULL,
    qris_payload TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql)) {
    echo "Tabel settings_klinik berhasil dibuat.";
} else {
    echo "Gagal membuat tabel: " . $conn->error;
}