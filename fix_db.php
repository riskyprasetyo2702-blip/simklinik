<?php
require_once 'config.php';

mysqli_report(MYSQLI_REPORT_OFF);

echo "Start Fix...<br>";

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Koneksi database tidak valid.");
}

function runQuery($conn, $sql, $label) {
    echo "Menjalankan: $label ...<br>";
    $ok = $conn->query($sql);
    if ($ok) {
        echo "$label OK<br>";
    } else {
        echo "$label GAGAL: " . $conn->error . "<br>";
    }
}

runQuery($conn, "ALTER TABLE odontogram_tindakan MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT", "odontogram_tindakan");
runQuery($conn, "ALTER TABLE invoice MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT", "invoice");
runQuery($conn, "ALTER TABLE invoice_items MODIFY COLUMN id INT NOT NULL AUTO_INCREMENT", "invoice_items");

echo "DONE";
