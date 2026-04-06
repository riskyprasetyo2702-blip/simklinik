<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: pasien.php'); exit; }
$id = (int)($_POST['id'] ?? 0);
$no_rm = trim($_POST['no_rm'] ?? '');
$nik = trim($_POST['nik'] ?? '');
$nama = trim($_POST['nama'] ?? '');
$jk = trim($_POST['jk'] ?? '');
$tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
$tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
$telepon = trim($_POST['telepon'] ?? '');
$alamat = trim($_POST['alamat'] ?? '');
$alergi = trim($_POST['alergi'] ?? '');
if ($no_rm === '' || $nama === '') { $_SESSION['error'] = 'No RM dan nama pasien wajib diisi.'; header('Location: pasien.php'.($id ? '?edit='.$id : '')); exit; }
$dup = $id > 0 ? db_fetch_one("SELECT id FROM pasien WHERE no_rm=? AND id<>?", [$no_rm, $id]) : db_fetch_one("SELECT id FROM pasien WHERE no_rm=?", [$no_rm]);
if ($dup) { $_SESSION['error'] = 'No RM sudah digunakan.'; header('Location: pasien.php'.($id ? '?edit='.$id : '')); exit; }
if ($id > 0) {
    $ok = db_run("UPDATE pasien SET no_rm=?, nik=?, nama=?, jk=?, tempat_lahir=?, tanggal_lahir=?, telepon=?, alamat=?, alergi=? WHERE id=?", [$no_rm,$nik,$nama,$jk,$tempat_lahir,$tanggal_lahir,$telepon,$alamat,$alergi,$id]);
    $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Data pasien berhasil diperbarui.' : 'Gagal memperbarui data pasien.';
} else {
    $newId = db_insert("INSERT INTO pasien (no_rm, nik, nama, jk, tempat_lahir, tanggal_lahir, telepon, alamat, alergi) VALUES (?,?,?,?,?,?,?,?,?)", [$no_rm,$nik,$nama,$jk,$tempat_lahir,$tanggal_lahir,$telepon,$alamat,$alergi]);
    $_SESSION[$newId ? 'success' : 'error'] = $newId ? 'Pasien berhasil disimpan.' : 'Gagal menyimpan pasien.';
}
header('Location: pasien.php'); exit;