<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

/*
|--------------------------------------------------------------------------
| Filter tanggal
|--------------------------------------------------------------------------
*/
$tanggal_mulai = trim($_GET['tanggal_mulai'] ?? date('Y-m-01'));
$tanggal_selesai = trim($_GET['tanggal_selesai'] ?? date('Y-m-d'));

/*
|--------------------------------------------------------------------------
| DATA KEUANGAN (utama)
|--------------------------------------------------------------------------
*/
$keuanganList = [];
$totalPemasukan = 0;

if (table_exists($conn, 'keuangan')) {

    $keuanganList = db_fetch_all("
        SELECT k.*, 
               p.nama AS nama_pasien,
               i.no_invoice,
               i.status_bayar
        FROM keuangan k
        LEFT JOIN pasien p ON p.id = k.pasien_id
        LEFT JOIN invoice i ON i.id = k.invoice_id
        WHERE DATE(k.tanggal) BETWEEN ? AND ?
        ORDER BY k.tanggal DESC, k.id DESC
    ", [$tanggal_mulai, $tanggal_selesai]);

    foreach ($keuanganList as $row) {
        $totalPemasukan += (float)($row['nominal'] ?? 0);
    }
}

/*
|--------------------------------------------------------------------------
| REKAP METODE BAYAR
|--------------------------------------------------------------------------
*/
$rekapMetode = [];

foreach ($keuanganList as $row) {
    $metode = strtolower($row['metode_bayar'] ?? 'lainnya');
    if (!isset($rekapMetode[$metode])) {
        $rekapMetode[$metode] = ['jumlah' => 0, 'total' => 0];
    }

    $rekapMetode[$metode]['jumlah']++;
    $rekapMetode[$metode]['total'] += (float)$row['nominal'];
}

/*
|--------------------------------------------------------------------------
| REKAP STATUS INVOICE
|--------------------------------------------------------------------------
*/
$rekapStatus = [];

foreach ($keuanganList as $row) {
    $status = strtolower($row['status_bayar'] ?? 'unknown');
    if (!isset($rekapStatus[$status])) {
        $rekapStatus[$status] = ['jumlah' => 0, 'total' => 0];
    }

    $rekapStatus[$status]['jumlah']++;
    $rekapStatus[$status]['total'] += (float)$row['nominal'];
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Laporan Keuangan</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1300px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:20px;padding:22px;margin-bottom:18px;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.stat{background:#0f172a;color:#fff;border-radius:18px;padding:20px}
.stat .label{font-size:13px;margin-bottom:6px}
.stat .value{font-size:28px;font-weight:800}
input,button{padding:12px;border:1px solid #ccc;border-radius:10px}
button{background:#0f172a;color:#fff;border:none;cursor:pointer}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #eee;text-align:left}
.badge{padding:6px 10px;border-radius:999px;font-size:12px;background:#eee}
.right{text-align:right}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
@media print{
    .no-print{display:none}
    body{background:#fff}
}
</style>
</head>
<body>
<div class="wrap">

<div class="row no-print">
    <h1>Laporan Keuangan</h1>
    <div>
        <button onclick="window.print()">Print</button>
    </div>
</div>

<div class="card no-print">
<form method="get" class="row">
    <div>
        <label>Tanggal Mulai</label><br>
        <input type="date" name="tanggal_mulai" value="<?= e($tanggal_mulai) ?>">
    </div>
    <div>
        <label>Tanggal Selesai</label><br>
        <input type="date" name="tanggal_selesai" value="<?= e($tanggal_selesai) ?>">
    </div>
    <div style="align-self:end">
        <button type="submit">Filter</button>
    </div>
</form>
</div>

<div class="grid">
    <div class="stat">
        <div class="label">Total Pemasukan</div>
        <div class="value"><?= rupiah($totalPemasukan) ?></div>
    </div>
    <div class="stat">
        <div class="label">Jumlah Transaksi</div>
        <div class="value"><?= count($keuanganList) ?></div>
    </div>
    <div class="stat">
        <div class="label">Periode</div>
        <div class="value"><?= e($tanggal_mulai) ?> - <?= e($tanggal_selesai) ?></div>
    </div>
</div>

<div class="card">
<h2>Rekap Metode Pembayaran</h2>
<table class="table">
<tr><th>Metode</th><th>Jumlah</th><th>Total</th></tr>
<?php foreach ($rekapMetode as $metode => $m): ?>
<tr>
<td><span class="badge"><?= strtoupper($metode) ?></span></td>
<td><?= $m['jumlah'] ?></td>
<td><?= rupiah($m['total']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h2>Rekap Status Invoice</h2>
<table class="table">
<tr><th>Status</th><th>Jumlah</th><th>Total</th></tr>
<?php foreach ($rekapStatus as $status => $s): ?>
<tr>
<td><span class="badge"><?= strtoupper($status) ?></span></td>
<td><?= $s['jumlah'] ?></td>
<td><?= rupiah($s['total']) ?></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h2>Detail Transaksi</h2>
<table class="table">
<tr>
<th>Tanggal</th>
<th>Invoice</th>
<th>Pasien</th>
<th>Metode</th>
<th>Status</th>
<th class="right">Nominal</th>
</tr>

<?php foreach ($keuanganList as $k): ?>
<tr>
<td><?= e($k['tanggal']) ?></td>
<td><?= e($k['no_invoice']) ?></td>
<td><?= e($k['nama_pasien']) ?></td>
<td><?= strtoupper(e($k['metode_bayar'])) ?></td>
<td><?= e($k['status_bayar']) ?></td>
<td class="right"><?= rupiah($k['nominal']) ?></td>
</tr>
<?php endforeach; ?>

<tr>
<th colspan="5" class="right">TOTAL</th>
<th class="right"><?= rupiah($totalPemasukan) ?></th>
</tr>

</table>
</div>

</div>
</body>
</html>
