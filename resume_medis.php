<?php
require_once __DIR__ . '/bootstrap.php';

$kunjunganId = (int)($_GET['kunjungan_id'] ?? 0);
$data = db_fetch_one("SELECT k.*, p.no_rm, p.nama, p.jk, p.tanggal_lahir, p.alamat, p.telepon FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id WHERE k.id=?", [$kunjunganId]);
if (!$data) die('Data kunjungan tidak ditemukan.');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><title>Resume Medis</title>
<style>
body{font-family:Arial,sans-serif;color:#111;margin:30px;line-height:1.5}table{width:100%;border-collapse:collapse}td{padding:6px 4px;vertical-align:top}.box{border:1px solid #d1d5db;border-radius:12px;padding:14px;margin-top:14px}.no-print{margin-bottom:20px}@media print {.no-print{display:none} body{margin:0;padding:16px}}
</style>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Print / Save PDF</button></div>
<h2 style="text-align:center;margin-bottom:24px">RESUME MEDIS</h2>
<table>
<tr><td width="160">No. Rekam Medis</td><td>: <?= e($data['no_rm']) ?></td></tr>
<tr><td>Nama Pasien</td><td>: <?= e($data['nama']) ?></td></tr>
<tr><td>Jenis Kelamin</td><td>: <?= e($data['jk']) ?></td></tr>
<tr><td>Tanggal Lahir</td><td>: <?= e($data['tanggal_lahir']) ?></td></tr>
<tr><td>Telepon</td><td>: <?= e($data['telepon']) ?></td></tr>
<tr><td>Alamat</td><td>: <?= e($data['alamat']) ?></td></tr>
<tr><td>Tanggal Kunjungan</td><td>: <?= e($data['tanggal']) ?></td></tr>
<tr><td>Dokter</td><td>: <?= e($data['dokter']) ?></td></tr>
</table>
<div class="box"><strong>Keluhan Utama</strong><br><?= nl2br(e($data['keluhan'])) ?></div>
<div class="box"><strong>Diagnosa</strong><br><?= nl2br(e($data['diagnosa'])) ?></div>
<div class="box"><strong>Tindakan</strong><br><?= nl2br(e($data['tindakan'])) ?></div>
<div class="box"><strong>Odontogram / Temuan Klinis</strong><br><?= nl2br(e($data['odontogram'])) ?></div>
<div class="box"><strong>Catatan Tambahan</strong><br><?= nl2br(e($data['catatan'])) ?></div>
<br><br>
<div style="text-align:right">Dokter Pemeriksa<br><br><br>________________________</div>
</body>
</html>
