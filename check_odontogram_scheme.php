<?php
require_once 'config.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Koneksi database tidak valid.\n");
}

$tables = ['odontogram_tindakan', 'invoice', 'invoice_items'];

foreach ($tables as $table) {
    echo "===== $table =====\n";

    $res = $conn->query("SHOW CREATE TABLE `$table`");
    if ($res && $row = $res->fetch_assoc()) {
        echo $row['Create Table'] . "\n\n";
    } else {
        echo "ERROR: " . $conn->error . "\n\n";
    }
}