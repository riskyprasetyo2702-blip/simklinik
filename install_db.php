<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'bootstrap.php';

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

echo "<h3>Diagnosa Database</h3>";

$dbNameRow = $conn->query("SELECT DATABASE() AS db_name");
$dbName = $dbNameRow ? ($dbNameRow->fetch_assoc()['db_name'] ?? '') : '';
echo "Database aktif: <strong>" . htmlspecialchars($dbName) . "</strong><br><br>";

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
    echo "CREATE TABLE: <strong>OK</strong><br>";
} else {
    echo "CREATE TABLE ERROR: " . htmlspecialchars($conn->error) . "<br>";
}

$check = $conn->query("SHOW TABLES LIKE 'settings_klinik'");
if ($check && $check->num_rows > 0) {
    echo "Cek tabel: <strong>settings_klinik ADA</strong><br>";
} else {
    echo "Cek tabel: <strong>settings_klinik TIDAK ADA</strong><br>";
}

echo "<br><h4>Daftar tabel:</h4>";
$list = $conn->query("SHOW TABLES");
if ($list) {
    echo "<ul>";
    while ($row = $list->fetch_array()) {
        echo "<li>" . htmlspecialchars($row[0]) . "</li>";
    }
    echo "</ul>";
}
