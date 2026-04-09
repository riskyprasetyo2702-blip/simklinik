<?php
require_once 'bootstrap.php';

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

echo "<h2>Install Admin DB</h2>";

$sql1 = "
CREATE TABLE IF NOT EXISTS widget_tindakan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(150),
    kode VARCHAR(50),
    aktif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

if ($conn->query($sql1)) {
    echo "Tabel widget_tindakan: OK<br>";
} else {
    echo "Tabel widget_tindakan ERROR: " . $conn->error . "<br>";
}

if (table_exists($conn, 'users')) {
    if (!column_exists($conn, 'users', 'role')) {
        if ($conn->query("ALTER TABLE users ADD COLUMN role ENUM('admin','dokter') DEFAULT 'dokter'")) {
            echo "Kolom role di users: OK<br>";
        } else {
            echo "Kolom role ERROR: " . $conn->error . "<br>";
        }
    } else {
        echo "Kolom role sudah ada<br>";
    }

    if (!column_exists($conn, 'users', 'nama_lengkap')) {
        if ($conn->query("ALTER TABLE users ADD COLUMN nama_lengkap VARCHAR(150) NULL")) {
            echo "Kolom nama_lengkap di users: OK<br>";
        } else {
            echo "Kolom nama_lengkap ERROR: " . $conn->error . "<br>";
        }
    } else {
        echo "Kolom nama_lengkap sudah ada<br>";
    }
} else {
    echo "Tabel users tidak ditemukan<br>";
}

echo "<br>Selesai.";
