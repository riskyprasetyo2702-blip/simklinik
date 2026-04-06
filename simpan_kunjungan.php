<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: kunjungan.php'); exit; }
$id = (int)($_POST['id'] ?? 0);
$pasien_id = (int)($_POST['pasien_id'] ?? 0);
$tanggal = trim($_POST['tanggal'] ?? '');
$keluhan = trim($_POST['keluhan'] ?? '');
$icd10_code = trim($_POST['icd10_code'] ?? '');
$diagnosa = trim($_POST['diagnosa'] ?? '');
$dokter = trim($_POST['dokter'] ?? current_user_name());
$tindakan = trim($_POST['tindakan'] ?? '');
$catatan = trim($_POST['catatan'] ?? '');
if ($pasien_id <= 0 || $tanggal === '') { $_SESSION['error'] = 'Pasien dan tanggal kunjungan wajib diisi.'; header('Location: kunjungan.php'.($id ? '?edit='.$id : '')); exit; }
if ($icd10_code !== '' && $diagnosa === '') {
    $icd = db_fetch_one("SELECT * FROM icd10 WHERE kode=?", [$icd10_code]);
    if ($icd) $diagnosa = $icd['kode'].' - '.$icd['diagnosis'];
}
if ($id > 0) {
    $ok = db_run("UPDATE kunjungan SET pasien_id=?, tanggal=?, keluhan=?, diagnosa=?, icd10_code=?, dokter=?, tindakan=?, catatan=? WHERE id=?", [$pasien_id,$tanggal,$keluhan,$diagnosa,$icd10_code,$dokter,$tindakan,$catatan,$id]);
    $_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Kunjungan berhasil diperbarui.' : 'Gagal memperbarui kunjungan.';
} else {
    $newId = db_insert("INSERT INTO kunjungan (pasien_id, tanggal, keluhan, diagnosa, icd10_code, dokter, tindakan, catatan) VALUES (?,?,?,?,?,?,?,?)", [$pasien_id,$tanggal,$keluhan,$diagnosa,$icd10_code,$dokter,$tindakan,$catatan]);
    $_SESSION[$newId ? 'success' : 'error'] = $newId ? 'Kunjungan berhasil disimpan.' : 'Gagal menyimpan kunjungan.';
}
header('Location: kunjungan.php?pasien_id='.$pasien_id); exit;