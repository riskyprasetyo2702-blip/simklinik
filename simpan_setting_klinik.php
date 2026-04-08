<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    $_SESSION['error'] = 'Koneksi database tidak tersedia.';
    header('Location: settings_klinik.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: settings_klinik.php');
    exit;
}

if (!table_exists($conn, 'settings_klinik')) {
    $_SESSION['error'] = 'Tabel settings_klinik belum ada.';
    header('Location: settings_klinik.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$nama_klinik = trim($_POST['nama_klinik'] ?? '');
$alamat_klinik = trim($_POST['alamat_klinik'] ?? '');
$telepon_klinik = trim($_POST['telepon_klinik'] ?? '');
$email_klinik = trim($_POST['email_klinik'] ?? '');
$qris_payload = trim($_POST['qris_payload'] ?? '');

$existing = db_fetch_one("SELECT * FROM settings_klinik ORDER BY id ASC LIMIT 1");

$uploadDir = __DIR__ . '/uploads/klinik/';
$uploadUrl = 'uploads/klinik/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

function upload_image_file($fileKey, $uploadDir, $uploadUrl) {
    if (empty($_FILES[$fileKey]['name'])) {
        return null;
    }

    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = $_FILES[$fileKey]['tmp_name'];
    $name = $_FILES[$fileKey]['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) {
        return false;
    }

    $newName = $fileKey . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $uploadDir . $newName;

    if (move_uploaded_file($tmp, $dest)) {
        return $uploadUrl . $newName;
    }

    return false;
}

$logoPath = $existing['logo_path'] ?? '';
$qrisPath = $existing['qris_path'] ?? '';

$newLogo = upload_image_file('logo_klinik', $uploadDir, $uploadUrl);
if ($newLogo === false) {
    $_SESSION['error'] = 'Upload logo gagal. Format harus JPG/PNG/WEBP.';
    header('Location: settings_klinik.php');
    exit;
}
if ($newLogo !== null) {
    $logoPath = $newLogo;
}

$newQris = upload_image_file('qris_image', $uploadDir, $uploadUrl);
if ($newQris === false) {
    $_SESSION['error'] = 'Upload QRIS gagal. Format harus JPG/PNG/WEBP.';
    header('Location: settings_klinik.php');
    exit;
}
if ($newQris !== null) {
    $qrisPath = $newQris;
}

if ($existing) {
    $ok = db_run("
        UPDATE settings_klinik SET
            nama_klinik = ?,
            alamat_klinik = ?,
            telepon_klinik = ?,
            email_klinik = ?,
            logo_path = ?,
            qris_path = ?,
            qris_payload = ?,
            updated_at = NOW()
        WHERE id = ?
    ", [
        $nama_klinik,
        $alamat_klinik,
        $telepon_klinik,
        $email_klinik,
        $logoPath,
        $qrisPath,
        $qris_payload,
        (int)$existing['id']
    ]);
} else {
    $ok = db_insert("
        INSERT INTO settings_klinik
        (nama_klinik, alamat_klinik, telepon_klinik, email_klinik, logo_path, qris_path, qris_payload, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ", [
        $nama_klinik,
        $alamat_klinik,
        $telepon_klinik,
        $email_klinik,
        $logoPath,
        $qrisPath,
        $qris_payload
    ]);
}

$_SESSION['success'] = $ok ? 'Pengaturan klinik berhasil disimpan.' : 'Gagal menyimpan pengaturan klinik.';
header('Location: settings_klinik.php');
exit;