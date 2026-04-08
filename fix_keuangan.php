<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

if (!table_exists($conn, 'invoice')) {
    die('Tabel invoice tidak ditemukan.');
}

$list = db_fetch_all("SELECT id FROM invoice ORDER BY id ASC");

$total = 0;
foreach ($list as $row) {
    sync_invoice_finance((int)$row['id']);
    $total++;
}

echo "Sinkron selesai. Total invoice diproses: " . $total;