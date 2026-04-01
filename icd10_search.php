<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost","root","","simklinik");
if ($conn->connect_error) {
    exit('<div class="list-group-item text-danger">Koneksi database gagal</div>');
}

$q = trim($_GET['q'] ?? '');

if ($q === '' || strlen($q) < 2) {
    exit;
}

$stmt = $conn->prepare("
    SELECT kode, nama
    FROM icd10
    WHERE kode LIKE CONCAT('%', ?, '%')
       OR nama LIKE CONCAT('%', ?, '%')
    ORDER BY kode ASC
    LIMIT 20
");

if (!$stmt) {
    exit('<div class="list-group-item text-danger">Prepare query ICD-10 gagal</div>');
}

$stmt->bind_param("ss", $q, $q);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<div class='list-group-item'>Tidak ada hasil</div>";
    exit;
}

while ($row = $result->fetch_assoc()) {
    $kode = htmlspecialchars($row['kode']);
    $nama = htmlspecialchars($row['nama']);

    echo "<button type='button' class='list-group-item list-group-item-action icd-item' ";
    echo "data-kode='" . $kode . "' ";
    echo "data-nama='" . $nama . "'>";
    echo "<strong>" . $kode . "</strong> - " . $nama;
    echo "</button>";
}