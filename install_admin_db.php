<?php
require_once 'bootstrap.php';

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

echo "<h2>Install Admin DB</h2>";

if (!table_exists($conn, 'widget_tindakan')) {
    $sqlWidget = "
    CREATE TABLE widget_tindakan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(150),
        kode VARCHAR(50),
        aktif TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";

    if ($conn->query($sqlWidget)) {
        echo "Tabel widget_tindakan: OK<br>";
    } else {
        echo "Tabel widget_tindakan ERROR: " . htmlspecialchars($conn->error) . "<br>";
    }
} else {
    echo "Tabel widget_tindakan sudah ada<br>";
}

if (table_exists($conn, 'widget_tindakan')) {
    $cek = db_fetch_one("SELECT COUNT(*) AS total FROM widget_tindakan");
    $total = (int)($cek['total'] ?? 0);

    if ($total === 0) {
        $seed = "
        INSERT INTO widget_tindakan (nama, kode, aktif) VALUES
        ('Tambal Gigi', 'tambal', 1),
        ('Cabut Gigi', 'cabut', 1),
        ('Scaling', 'scaling', 1),
        ('Endodontik', 'endo', 1),
        ('Konsultasi', 'konsultasi', 1),
        ('Kontrol', 'kontrol', 1)
        ";
        if ($conn->query($seed)) {
            echo "Data awal widget_tindakan: OK<br>";
        } else {
            echo "Data awal widget_tindakan ERROR: " . htmlspecialchars($conn->error) . "<br>";
        }
    } else {
        echo "Data widget_tindakan sudah ada: {$total}<br>";
    }
}

if (table_exists($conn, 'users')) {
    if (!column_exists($conn, 'users', 'role')) {
        if ($conn->query("ALTER TABLE users ADD COLUMN role ENUM('admin','dokter') DEFAULT 'dokter'")) {
            echo "Kolom role di users: OK<br>";
        } else {
            echo "Kolom role ERROR: " . htmlspecialchars($conn->error) . "<br>";
        }
    } else {
        echo "Kolom role sudah ada<br>";
    }

    if (!column_exists($conn, 'users', 'nama_lengkap')) {
        if ($conn->query("ALTER TABLE users ADD COLUMN nama_lengkap VARCHAR(150) NULL")) {
            echo "Kolom nama_lengkap di users: OK<br>";
        } else {
            echo "Kolom nama_lengkap ERROR: " . htmlspecialchars($conn->error) . "<br>";
        }
    } else {
        echo "Kolom nama_lengkap sudah ada<br>";
    }
} else {
    echo "Tabel users tidak ditemukan<br>";
}

echo "<br><strong>Selesai.</strong>";
