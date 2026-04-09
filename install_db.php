<?php
require_once 'bootstrap.php';

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

$sql = "
CREATE TABLE IF NOT EXISTS settings_klinik (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_klinik VARCHAR(150),
    alamat_klinik TEXT,
    telepon_klinik VARCHAR(50),
    email_klinik VARCHAR(100),
    logo_path VARCHAR(255),
    qris_path VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql)) {
    echo "Tabel settings_klinik berhasil dibuat / sudah ada.";
} else {
    echo "Gagal membuat tabel: " . $conn->error;
}
