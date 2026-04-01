<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$conn = new mysqli("localhost","root","","simklinik");

$bulan = $_GET['bulan'] ?? date('Y-m');

$data = $conn->query("
SELECT 
    DATE(tanggal_invoice) as tanggal,
    COUNT(*) as jumlah_invoice,
    SUM(CASE WHEN status_bayar='lunas' THEN total ELSE 0 END) as total_lunas,
    SUM(CASE WHEN status_bayar='pending' THEN total ELSE 0 END) as total_pending,
    SUM(CASE WHEN status_bayar='tidak_terbayar' THEN total ELSE 0 END) as total_batal
FROM invoices
WHERE DATE_FORMAT(tanggal_invoice,'%Y-%m') = '$bulan'
GROUP BY DATE(tanggal_invoice)
ORDER BY tanggal ASC
");

$summary = $conn->query("
SELECT 
    SUM(CASE WHEN status_bayar='lunas' THEN total ELSE 0 END) as total_lunas,
    SUM(CASE WHEN status_bayar='pending' THEN total ELSE 0 END) as total_pending,
    SUM(CASE WHEN status_bayar='tidak_terbayar' THEN total ELSE 0 END) as total_batal
FROM invoices
WHERE DATE_FORMAT(tanggal_invoice,'%Y-%m') = '$bulan'
")->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
<title>Laporan Keuangan</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body style="background:#eef2f7;">
<div class="container py-4">

<div class="d-flex justify-content-between mb-3">
    <h3>📊 Laporan Keuangan</h3>
    <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
</div>

<form method="GET" class="mb-3">
    <input type="month" name="bulan" value="<?= $bulan ?>" class="form-control" style="max-width:200px;display:inline;">
    <button class="btn btn-primary">Filter</button>
</form>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-success text-white p-3">
            <h6>Total Lunas</h6>
            <h4>Rp <?= number_format($summary['total_lunas'],0,',','.') ?></h4>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-warning text-dark p-3">
            <h6>Pending</h6>
            <h4>Rp <?= number_format($summary['total_pending'],0,',','.') ?></h4>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card bg-danger text-white p-3">
            <h6>Tidak Terbayar</h6>
            <h4>Rp <?= number_format($summary['total_batal'],0,',','.') ?></h4>
        </div>
    </div>
</div>

<table class="table table-bordered">
<thead>
<tr>
    <th>Tanggal</th>
    <th>Jumlah Invoice</th>
    <th>Lunas</th>
    <th>Pending</th>
    <th>Tidak Terbayar</th>
</tr>
</thead>
<tbody>

<?php while($r = $data->fetch_assoc()): ?>
<tr>
<td><?= $r['tanggal'] ?></td>
<td><?= $r['jumlah_invoice'] ?></td>
<td>Rp <?= number_format($r['total_lunas'],0,',','.') ?></td>
<td>Rp <?= number_format($r['total_pending'],0,',','.') ?></td>
<td>Rp <?= number_format($r['total_batal'],0,',','.') ?></td>
</tr>
<?php endwhile; ?>

</tbody>
</table>

</div>
</body>
</html>