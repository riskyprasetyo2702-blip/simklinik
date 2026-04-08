<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

$tanggal_mulai = trim($_GET['tanggal_mulai'] ?? date('Y-m-01'));
$tanggal_selesai = trim($_GET['tanggal_selesai'] ?? date('Y-m-d'));

$keuanganList = [];
$totalPemasukan = 0;
$rekapMetode = [];
$rekapStatus = [];

if (table_exists($conn, 'keuangan') && column_exists($conn, 'keuangan', 'tanggal')) {
    $select = ["k.*"];
    $joinPasien = false;
    $joinInvoice = false;

    if (
        table_exists($conn, 'pasien') &&
        column_exists($conn, 'keuangan', 'pasien_id')
    ) {
        $select[] = "p.nama AS nama_pasien";
        $joinPasien = true;
    } else {
        $select[] = "'' AS nama_pasien";
    }

    if (
        table_exists($conn, 'invoice') &&
        column_exists($conn, 'keuangan', 'invoice_id')
    ) {
        $select[] = "i.no_invoice";
        $select[] = "i.status_bayar";
        $joinInvoice = true;
    } else {
        $select[] = "'' AS no_invoice";
        $select[] = "'' AS status_bayar";
    }

    $sql = "SELECT " . implode(', ', $select) . " FROM keuangan k ";

    if ($joinPasien) {
        $sql .= "LEFT JOIN pasien p ON p.id = k.pasien_id ";
    }

    if ($joinInvoice) {
        $sql .= "LEFT JOIN invoice i ON i.id = k.invoice_id ";
    }

    $sql .= "WHERE DATE(k.tanggal) BETWEEN ? AND ? ORDER BY k.tanggal DESC, k.id DESC";

    $keuanganList = db_fetch_all($sql, [$tanggal_mulai, $tanggal_selesai]);

    foreach ($keuanganList as $row) {
        $nominal = (float)($row['nominal'] ?? 0);
        $totalPemasukan += $nominal;

        $metode = strtolower(trim((string)($row['metode_bayar'] ?? 'lainnya')));
        if ($metode === '') $metode = 'lainnya';
        if (!isset($rekapMetode[$metode])) {
            $rekapMetode[$metode] = ['jumlah' => 0, 'total' => 0];
        }
        $rekapMetode[$metode]['jumlah']++;
        $rekapMetode[$metode]['total'] += $nominal;

        $status = strtolower(trim((string)($row['status_bayar'] ?? 'unknown')));
        if ($status === '') $status = 'unknown';
        if (!isset($rekapStatus[$status])) {
            $rekapStatus[$status] = ['jumlah' => 0, 'total' => 0];
        }
        $rekapStatus[$status]['jumlah']++;
        $rekapStatus[$status]['total'] += $nominal;
    }
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
button,.btn{background:#0f172a;color:#fff;border:none;cursor:pointer;text-decoration:none;padding:12px 16px;border-radius:10px}
.btn.secondary{background:#475569}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #eee;text-align:left}
.badge{padding:6px 10px;border-radius:999px;font-size:12px;background:#eee;display:inline-block}
.right{text-align:right}
.small{font-size:13px;color:#64748b}
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
    <div>
        <h1 style="margin:0">Laporan Keuangan</h1>
        <div class="small">Rekap pemasukan dari tabel keuangan</div>
    </div>
    <div class="row">
        <a class="btn secondary" href="dashboard.php">Dashboard</a>
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
        <div class="value" style="font-size:20px"><?= e($tanggal_mulai) ?> - <?= e($tanggal_selesai) ?></div>
    </div>
</div>

<div class="card">
    <h2>Rekap Metode Pembayaran</h2>
    <table class="table">
        <tr><th>Metode</th><th>Jumlah</th><th>Total</th></tr>
        <?php foreach ($rekapMetode as $metode => $m): ?>
        <tr>
            <td><span class="badge"><?= strtoupper(e($metode)) ?></span></td>
            <td><?= (int)$m['jumlah'] ?></td>
            <td><?= rupiah($m['total']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rekapMetode): ?>
        <tr><td colspan="3">Belum ada data metode pembayaran.</td></tr>
        <?php endif; ?>
    </table>
</div>

<div class="card">
    <h2>Rekap Status Invoice</h2>
    <table class="table">
        <tr><th>Status</th><th>Jumlah</th><th>Total</th></tr>
        <?php foreach ($rekapStatus as $status => $s): ?>
        <tr>
            <td><span class="badge"><?= strtoupper(e($status)) ?></span></td>
            <td><?= (int)$s['jumlah'] ?></td>
            <td><?= rupiah($s['total']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$rekapStatus): ?>
        <tr><td colspan="3">Belum ada data status invoice.</td></tr>
        <?php endif; ?>
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
            <td><?= e($k['tanggal'] ?? '') ?></td>
            <td><?= e($k['no_invoice'] ?? '') ?></td>
            <td><?= e($k['nama_pasien'] ?? '') ?></td>
            <td><?= strtoupper(e($k['metode_bayar'] ?? '')) ?></td>
            <td><?= e($k['status_bayar'] ?? '') ?></td>
            <td class="right"><?= rupiah($k['nominal'] ?? 0) ?></td>
        </tr>
        <?php endforeach; ?>

        <?php if (!$keuanganList): ?>
        <tr><td colspan="6">Belum ada data keuangan.</td></tr>
        <?php else: ?>
        <tr>
            <th colspan="5" class="right">TOTAL</th>
            <th class="right"><?= rupiah($totalPemasukan) ?></th>
        </tr>
        <?php endif; ?>
    </table>
</div>

</div>
</body>
</html>
