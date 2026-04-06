<?php
require_once __DIR__ . '/bootstrap.php';

$kunjunganId = (int)($_GET['kunjungan_id'] ?? 0);
$data = db_fetch_one("SELECT k.*, p.no_rm, p.nama, p.jk, p.tanggal_lahir, p.alamat FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id WHERE k.id=?", [$kunjunganId]);
if (!$data) die('Data kunjungan tidak ditemukan.');

$hari = (int)($_GET['hari'] ?? 1);
$tglMulai = $_GET['mulai'] ?? date('Y-m-d');
$tglSelesai = date('Y-m-d', strtotime($tglMulai . ' +' . max(0, $hari-1) . ' day'));
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><title>Surat Sakit</title>
<style>
body{font-family:Arial,sans-serif;color:#111;margin:40px;line-height:1.7}.no-print{margin-bottom:20px}@media print {.no-print{display:none} body{margin:0;padding:26px}}
</style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">Print / Save PDF</button>
    <form method="get" style="display:inline-block;margin-left:12px">
        <input type="hidden" name="kunjungan_id" value="<?= (int)$kunjunganId ?>">
        <label>Mulai: <input type="date" name="mulai" value="<?= e($tglMulai) ?>"></label>
        <label>Hari: <input type="number" min="1" name="hari" value="<?= (int)$hari ?>" style="width:70px"></label>
        <button type="submit">Update</button>
    </form>
</div>

<div style="text-align:center">
    <h2 style="margin-bottom:0">SURAT KETERANGAN SAKIT</h2>
    <p>Nomor: <?= 'SKS/' . date('Ym') . '/' . str_pad((string)$kunjunganId, 3, '0', STR_PAD_LEFT) ?></p>
</div>

<p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>
<table>
<tr><td width="140">Nama</td><td>: <?= e($data['nama']) ?></td></tr>
<tr><td>No. RM</td><td>: <?= e($data['no_rm']) ?></td></tr>
<tr><td>Jenis Kelamin</td><td>: <?= e($data['jk']) ?></td></tr>
<tr><td>Tanggal Lahir</td><td>: <?= e($data['tanggal_lahir']) ?></td></tr>
<tr><td>Alamat</td><td>: <?= e($data['alamat']) ?></td></tr>
</table>

<p>Berdasarkan hasil pemeriksaan pada tanggal <strong><?= e(date('d-m-Y', strtotime($data['tanggal']))) ?></strong>, pasien tersebut memerlukan istirahat selama <strong><?= (int)$hari ?> hari</strong>, terhitung mulai tanggal <strong><?= e(date('d-m-Y', strtotime($tglMulai))) ?></strong> sampai dengan <strong><?= e(date('d-m-Y', strtotime($tglSelesai))) ?></strong>.</p>
<p>Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>

<br><br>
<div style="text-align:right">
    <?= e(date('d F Y')) ?><br>
    Dokter Pemeriksa<br><br><br>
    ________________________
</div>
</body>
</html>
