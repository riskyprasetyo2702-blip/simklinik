<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    $_SESSION['error'] = 'Koneksi database tidak tersedia.';
    header('Location: odontogram.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: odontogram.php');
    exit;
}

if (!table_exists($conn, 'odontogram_tindakan')) {
    $_SESSION['error'] = 'Tabel odontogram tidak ditemukan.';
    header('Location: odontogram.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil data form
|--------------------------------------------------------------------------
*/
$pasien_id     = (int)($_POST['pasien_id'] ?? 0);
$kunjungan_id  = (int)($_POST['kunjungan_id'] ?? 0);
$nomor_gigi    = trim($_POST['nomor_gigi'] ?? '');
$surface_code  = trim($_POST['surface_code'] ?? '');
$tindakan_id   = (int)($_POST['tindakan_id'] ?? 0);
$nama_tindakan = trim($_POST['nama_tindakan'] ?? '');
$kategori      = trim($_POST['kategori'] ?? '');
$harga         = (float)($_POST['harga'] ?? 0);
$qty           = (float)($_POST['qty'] ?? 1);
$subtotal      = (float)($_POST['subtotal'] ?? 0);
$satuan_harga  = trim($_POST['satuan_harga'] ?? '');
$catatan       = trim($_POST['catatan'] ?? '');

/*
|--------------------------------------------------------------------------
| Validasi
|--------------------------------------------------------------------------
*/
if ($pasien_id <= 0 || $kunjungan_id <= 0) {
    $_SESSION['error'] = 'Pasien atau kunjungan tidak valid.';
    header('Location: kunjungan.php');
    exit;
}

if ($nomor_gigi === '') {
    $_SESSION['error'] = 'Nomor gigi wajib diisi.';
    header('Location: odontogram.php?pasien_id='.$pasien_id.'&kunjungan_id='.$kunjungan_id);
    exit;
}

if ($nama_tindakan === '') {
    $_SESSION['error'] = 'Tindakan wajib diisi.';
    header('Location: odontogram.php?pasien_id='.$pasien_id.'&kunjungan_id='.$kunjungan_id);
    exit;
}

if ($qty <= 0) $qty = 1;
if ($harga < 0) $harga = 0;

$subtotal = $qty * $harga;

/*
|--------------------------------------------------------------------------
| Cegah duplicate (gigi + tindakan sama)
|--------------------------------------------------------------------------
*/
$cek = db_fetch_one("
    SELECT id FROM odontogram_tindakan
    WHERE kunjungan_id = ?
    AND nomor_gigi = ?
    AND nama_tindakan = ?
    LIMIT 1
", [$kunjungan_id, $nomor_gigi, $nama_tindakan]);

if ($cek) {
    $_SESSION['error'] = 'Tindakan pada gigi tersebut sudah ada.';
    header('Location: odontogram.php?pasien_id='.$pasien_id.'&kunjungan_id='.$kunjungan_id);
    exit;
}

/*
|--------------------------------------------------------------------------
| Susun data adaptif
|--------------------------------------------------------------------------
*/
$data = [];

if (column_exists($conn, 'odontogram_tindakan', 'pasien_id')) {
    $data['pasien_id'] = $pasien_id;
}
if (column_exists($conn, 'odontogram_tindakan', 'kunjungan_id')) {
    $data['kunjungan_id'] = $kunjungan_id;
}
if (column_exists($conn, 'odontogram_tindakan', 'nomor_gigi')) {
    $data['nomor_gigi'] = $nomor_gigi;
}
if (column_exists($conn, 'odontogram_tindakan', 'tooth_number')) {
    $data['tooth_number'] = $nomor_gigi;
}
if (column_exists($conn, 'odontogram_tindakan', 'surface_code')) {
    $data['surface_code'] = $surface_code;
}
if (column_exists($conn, 'odontogram_tindakan', 'tindakan_id')) {
    $data['tindakan_id'] = $tindakan_id;
}
if (column_exists($conn, 'odontogram_tindakan', 'nama_tindakan')) {
    $data['nama_tindakan'] = $nama_tindakan;
}
if (column_exists($conn, 'odontogram_tindakan', 'kategori')) {
    $data['kategori'] = $kategori;
}
if (column_exists($conn, 'odontogram_tindakan', 'harga')) {
    $data['harga'] = $harga;
}
if (column_exists($conn, 'odontogram_tindakan', 'qty')) {
    $data['qty'] = $qty;
}
if (column_exists($conn, 'odontogram_tindakan', 'subtotal')) {
    $data['subtotal'] = $subtotal;
}
if (column_exists($conn, 'odontogram_tindakan', 'satuan_harga')) {
    $data['satuan_harga'] = $satuan_harga;
}
if (column_exists($conn, 'odontogram_tindakan', 'catatan')) {
    $data['catatan'] = $catatan;
}
if (column_exists($conn, 'odontogram_tindakan', 'created_at')) {
    $data['created_at'] = date('Y-m-d H:i:s');
}

/*
|--------------------------------------------------------------------------
| Insert data
|--------------------------------------------------------------------------
*/
$cols = [];
$placeholders = [];
$params = [];

foreach ($data as $col => $val) {
    $cols[] = "`$col`";
    $placeholders[] = "?";
    $params[] = $val;
}

$sql = "INSERT INTO odontogram_tindakan (" . implode(', ', $cols) . ")
        VALUES (" . implode(', ', $placeholders) . ")";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $_SESSION['error'] = 'Gagal prepare odontogram: ' . $conn->error;
    header('Location: odontogram.php?pasien_id='.$pasien_id.'&kunjungan_id='.$kunjungan_id);
    exit;
}

$types = '';
foreach ($params as $p) {
    if (is_int($p)) {
        $types .= 'i';
    } elseif (is_float($p)) {
        $types .= 'd';
    } else {
        $types .= 's';
    }
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$ok = $stmt->execute();
$stmt->close();

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/
if ($ok) {
    $_SESSION['success'] = 'Odontogram berhasil disimpan.';
} else {
    $_SESSION['error'] = 'Gagal menyimpan odontogram.';
}

header('Location: odontogram.php?pasien_id='.$pasien_id.'&kunjungan_id='.$kunjungan_id);
exit;