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

$pasien_id     = (int)($_POST['pasien_id'] ?? 0);
$kunjungan_id  = (int)($_POST['kunjungan_id'] ?? 0);
$keluhan       = esc($conn, $_POST['keluhan_utama'] ?? '');
$anamnesis     = esc($conn, $_POST['anamnesis'] ?? '');
$pemeriksaan   = esc($conn, $_POST['pemeriksaan'] ?? '');
$diagnosa      = esc($conn, $_POST['diagnosa'] ?? '');
$icd10_code    = esc($conn, $_POST['icd10_code'] ?? '');
$tindakan      = esc($conn, $_POST['tindakan'] ?? '');
$terapi        = esc($conn, $_POST['terapi'] ?? '');
$instruksi     = esc($conn, $_POST['instruksi'] ?? '');
$catatan       = esc($conn, $_POST['catatan'] ?? '');
$dokter_nama   = esc($conn, $_POST['dokter_nama'] ?? '');
$dokter_sip    = esc($conn, $_POST['dokter_sip'] ?? '');

if ($pasien_id <= 0 || $kunjungan_id <= 0 || $dokter_nama === '') {
    die("Data resume medis belum lengkap");
}

$sql = "INSERT INTO resume_medis (
    pasien_id,
    kunjungan_id,
    keluhan_utama,
    anamnesis,
    pemeriksaan,
    diagnosa,
    icd10_code,
    tindakan,
    terapi,
    instruksi,
    catatan,
    dokter_nama,
    dokter_sip
) VALUES (
    $pasien_id,
    $kunjungan_id,
    '$keluhan',
    '$anamnesis',
    '$pemeriksaan',
    '$diagnosa',
    '$icd10_code',
    '$tindakan',
    '$terapi',
    '$instruksi',
    '$catatan',
    '$dokter_nama',
    '$dokter_sip'
)";

if (!mysqli_query($conn, $sql)) {
    die("Gagal menyimpan resume medis: " . mysqli_error($conn));
}

$resume_id = mysqli_insert_id($conn);
header("Location: print_resume_medis.php?id=" . $resume_id);
exit;