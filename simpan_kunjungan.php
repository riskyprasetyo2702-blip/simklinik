<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    $_SESSION['error'] = 'Koneksi database tidak tersedia.';
    header('Location: kunjungan.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: kunjungan.php');
    exit;
}

function post_val($key, $default = '')
{
    return trim($_POST[$key] ?? $default);
}

$id        = (int)($_POST['id'] ?? 0);
$pasien_id = (int)($_POST['pasien_id'] ?? 0);
$tanggal   = post_val('tanggal');
$keluhan   = post_val('keluhan');
$diagnosa  = post_val('diagnosa');
$dokter    = post_val('dokter');
$tindakan  = post_val('tindakan');
$catatan   = post_val('catatan');

// dukungan opsional jika nanti form baru pakai ICD-10
$icd10_code = post_val('icd10_code');

if ($pasien_id <= 0) {
    $_SESSION['error'] = 'Pasien wajib dipilih.';
    header('Location: kunjungan.php' . ($id > 0 ? '?edit=' . $id : ''));
    exit;
}

if ($tanggal === '') {
    $_SESSION['error'] = 'Tanggal kunjungan wajib diisi.';
    header('Location: kunjungan.php' . ($id > 0 ? '?edit=' . $id : '?pasien_id=' . $pasien_id));
    exit;
}

if (!table_exists($conn, 'kunjungan')) {
    $_SESSION['error'] = 'Tabel kunjungan tidak ditemukan.';
    header('Location: kunjungan.php');
    exit;
}

// Validasi pasien ada
$cekPasien = db_fetch_one("SELECT id FROM pasien WHERE id = ?", [$pasien_id]);
if (!$cekPasien) {
    $_SESSION['error'] = 'Data pasien tidak ditemukan.';
    header('Location: kunjungan.php');
    exit;
}

// Susun kolom yang benar-benar ada di tabel
$dataMap = [
    'pasien_id'  => $pasien_id,
    'tanggal'    => $tanggal,
    'keluhan'    => $keluhan,
    'diagnosa'   => $diagnosa,
    'icd10_code' => $icd10_code,
    'dokter'     => $dokter,
    'tindakan'   => $tindakan,
    'catatan'    => $catatan,
];

$columns = [];
foreach ($dataMap as $col => $val) {
    if (column_exists($conn, 'kunjungan', $col)) {
        $columns[$col] = $val;
    }
}

if (empty($columns)) {
    $_SESSION['error'] = 'Struktur tabel kunjungan tidak sesuai.';
    header('Location: kunjungan.php');
    exit;
}

if ($id > 0) {
    $setParts = [];
    $params = [];

    foreach ($columns as $col => $val) {
        $setParts[] = "`$col` = ?";
        $params[] = $val;
    }

    if (column_exists($conn, 'kunjungan', 'updated_at')) {
        $setParts[] = "`updated_at` = NOW()";
    }

    $params[] = $id;

    $sql = "UPDATE kunjungan SET " . implode(', ', $setParts) . " WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = 'Gagal menyiapkan update kunjungan: ' . $conn->error;
        header('Location: kunjungan.php?edit=' . $id);
        exit;
    }

    $types = '';
    for ($i = 0; $i < count($params) - 1; $i++) {
        $types .= is_int($params[$i]) ? 'i' : 's';
    }
    $types .= 'i';

    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Kunjungan berhasil diperbarui.';
        header('Location: kunjungan.php?pasien_id=' . $pasien_id);
        exit;
    }

    $_SESSION['error'] = 'Gagal memperbarui kunjungan: ' . $stmt->error;
    header('Location: kunjungan.php?edit=' . $id);
    exit;
}

// INSERT
$insertCols = [];
$placeholders = [];
$params = [];

foreach ($columns as $col => $val) {
    $insertCols[] = "`$col`";
    $placeholders[] = "?";
    $params[] = $val;
}

if (column_exists($conn, 'kunjungan', 'created_at')) {
    $insertCols[] = "`created_at`";
    $placeholders[] = "NOW()";
}

if (column_exists($conn, 'kunjungan', 'updated_at')) {
    $insertCols[] = "`updated_at`";
    $placeholders[] = "NOW()";
}

$sql = "INSERT INTO kunjungan (" . implode(', ', $insertCols) . ")
        VALUES (" . implode(', ', $placeholders) . ")";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $_SESSION['error'] = 'Gagal menyiapkan simpan kunjungan: ' . $conn->error;
    header('Location: kunjungan.php?pasien_id=' . $pasien_id);
    exit;
}

if (!empty($params)) {
    $types = '';
    foreach ($params as $p) {
        $types .= is_int($p) ? 'i' : 's';
    }
    $stmt->bind_param($types, ...$params);
}

if ($stmt->execute()) {
    $kunjungan_baru_id = $conn->insert_id;
    $_SESSION['success'] = 'Kunjungan berhasil disimpan.';
    header('Location: kunjungan.php?pasien_id=' . $pasien_id);
    exit;
}

$_SESSION['error'] = 'Gagal menyimpan kunjungan: ' . $stmt->error;
header('Location: kunjungan.php?pasien_id=' . $pasien_id);
exit;
