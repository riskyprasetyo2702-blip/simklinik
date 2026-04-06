<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
$conn = db();
if (!($conn instanceof mysqli)) {
    die('Koneksi database tidak ditemukan dari config.php');
}
ensure_odontogram_tables($conn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: odontogram.php');
    exit;
}

$pasien_id = (int)($_POST['pasien_id'] ?? 0);
$kunjungan_id = (int)($_POST['kunjungan_id'] ?? 0);
$tanggal = $_POST['tanggal'] ?? date('Y-m-d');
$keluhan_utama = trim($_POST['keluhan_utama'] ?? '');
$diagnosa_icd10 = trim($_POST['diagnosa_icd10'] ?? '');
$nama_diagnosa = trim($_POST['nama_diagnosa'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');
$total_tagihan = (float)($_POST['total_tagihan'] ?? 0);
$items = $_POST['items'] ?? [];

if ($pasien_id <= 0 || $diagnosa_icd10 === '' || empty($items)) {
    die('Data belum lengkap. Pasien, diagnosa ICD-10, dan item tindakan wajib diisi.');
}

$stmt = $conn->prepare("INSERT INTO odontogram (pasien_id, kunjungan_id, tanggal, keluhan_utama, diagnosa_icd10, nama_diagnosa, catatan, total_tagihan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param('iisssssd', $pasien_id, $kunjungan_id, $tanggal, $keluhan_utama, $diagnosa_icd10, $nama_diagnosa, $catatan, $total_tagihan);
$stmt->execute();
$odontogram_id = $stmt->insert_id;
$stmt->close();

$itemStmt = $conn->prepare("INSERT INTO odontogram_items (odontogram_id, nomor_gigi, kondisi, tindakan, tarif) VALUES (?, ?, ?, ?, ?)");
foreach ($items as $it) {
    $nomor_gigi = trim($it['nomor_gigi'] ?? '');
    $kondisi = trim($it['kondisi'] ?? '');
    $tindakan = trim($it['tindakan'] ?? '');
    $tarif = (float)($it['tarif'] ?? 0);
    if ($nomor_gigi === '' || $tindakan === '') continue;
    $itemStmt->bind_param('isssd', $odontogram_id, $nomor_gigi, $kondisi, $tindakan, $tarif);
    $itemStmt->execute();
}
$itemStmt->close();

$query = http_build_query([
    'pasien_id' => $pasien_id,
    'kunjungan_id' => $kunjungan_id,
    'odontogram_id' => $odontogram_id,
    'diagnosa_icd10' => $diagnosa_icd10,
    'nama_diagnosa' => $nama_diagnosa,
    'billing_total' => $total_tagihan,
]);
header('Location: invoice.php?' . $query);
exit;
