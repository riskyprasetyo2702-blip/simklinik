<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: dashboard.php'); exit; }
$pasien_id=(int)($_POST['pasien_id'] ?? 0); $kunjungan_id=(int)($_POST['kunjungan_id'] ?? 0); $nomor_gigi=trim($_POST['nomor_gigi'] ?? ''); $surface_code=trim($_POST['surface_code'] ?? ''); $tindakan_id=(int)($_POST['tindakan_id'] ?? 0); $nama_tindakan=trim($_POST['nama_tindakan'] ?? ''); $harga=(float)($_POST['harga'] ?? 0); $qty=(float)($_POST['qty'] ?? 1); $subtotal=(float)($_POST['subtotal'] ?? ($harga*$qty)); $satuan_harga=trim($_POST['satuan_harga'] ?? 'per tindakan'); $catatan=trim($_POST['catatan'] ?? '');
if($pasien_id<=0 || $kunjungan_id<=0 || $nomor_gigi==='' || $tindakan_id<=0){ $_SESSION['error']='Pasien, kunjungan, nomor gigi, dan tindakan wajib diisi.'; header('Location: odontogram.php?pasien_id='.$pasien_id.'&kunjungan_id='.$kunjungan_id); exit; }
$td = db_fetch_one("SELECT * FROM tindakan WHERE id=?", [$tindakan_id]); if($nama_tindakan==='') $nama_tindakan = $td['nama_tindakan'] ?? '';
$ok = db_insert("INSERT INTO odontogram_tindakan (pasien_id, kunjungan_id, nomor_gigi, surface_code, tindakan_id, nama_tindakan, kategori, harga, qty, subtotal, satuan_harga, catatan) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", [$pasien_id,$kunjungan_id,$nomor_gigi,$surface_code,$tindakan_id,$nama_tindakan,$td['kategori'] ?? '',$harga,$qty,$subtotal,$satuan_harga,$catatan]);
$_SESSION[$ok ? 'success' : 'error'] = $ok ? 'Tindakan odontogram berhasil disimpan.' : 'Gagal menyimpan odontogram.';
header('Location: invoice.php?pasien_id='.$pasien_id.'&kunjungan_id='.$kunjungan_id.'&sync_odo=1'); exit;