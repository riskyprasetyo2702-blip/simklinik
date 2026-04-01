<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "simklinik");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Request tidak valid");
}

function esc($conn, $value) {
    return mysqli_real_escape_string($conn, trim($value ?? ''));
}

$pasien_id          = (int)($_POST['pasien_id'] ?? 0);
$kunjungan_id       = (int)($_POST['kunjungan_id'] ?? 0);
$tanggal_surat      = esc($conn, $_POST['tanggal_surat'] ?? '');
$tanggal_mulai      = esc($conn, $_POST['tanggal_mulai'] ?? '');
$tanggal_selesai    = esc($conn, $_POST['tanggal_selesai'] ?? '');
$diagnosis_singkat  = esc($conn, $_POST['diagnosis_singkat'] ?? '');
$keterangan         = esc($conn, $_POST['keterangan'] ?? '');
$dokter_nama        = esc($conn, $_POST['dokter_nama'] ?? '');
$dokter_sip         = esc($conn, $_POST['dokter_sip'] ?? '');

if ($pasien_id <= 0 || $kunjungan_id <= 0 || $tanggal_surat === '' || $tanggal_mulai === '' || $tanggal_selesai === '' || $dokter_nama === '') {
    die("Data surat sakit belum lengkap");
}

$start = strtotime($tanggal_mulai);
$end   = strtotime($tanggal_selesai);
if ($start === false || $end === false || $end < $start) {
    die("Tanggal surat sakit tidak valid");
}

$lama_istirahat = (int)(($end - $start) / 86400) + 1;
$nomor_surat = 'SS/' . date('Ymd') . '/' . strtoupper(substr(md5(uniqid('', true)), 0, 6));

$sql = "INSERT INTO surat_sakit (
    pasien_id,
    kunjungan_id,
    nomor_surat,
    tanggal_surat,
    tanggal_mulai,
    tanggal_selesai,
    lama_istirahat,
    diagnosis_singkat,
    keterangan,
    dokter_nama,
    dokter_sip
) VALUES (
    $pasien_id,
    $kunjungan_id,
    '$nomor_surat',
    '$tanggal_surat',
    '$tanggal_mulai',
    '$tanggal_selesai',
    $lama_istirahat,
    '$diagnosis_singkat',
    '$keterangan',
    '$dokter_nama',
    '$dokter_sip'
)";

if (!mysqli_query($conn, $sql)) {
    die("Gagal membuat surat sakit: " . mysqli_error($conn));
}

$surat_id = mysqli_insert_id($conn);
header("Location: print_surat_sakit.php?id=" . $surat_id);
exit;
