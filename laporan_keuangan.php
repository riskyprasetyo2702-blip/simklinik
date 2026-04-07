<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
ensure_keuangan_table();

$bulan = $_GET['bulan'] ?? date('Y-m');
$mulai = $bulan . '-01 00:00:00';
$akhir = date('Y-m-t 23:59:59', strtotime($mulai));

$ringkas = keuangan_ringkasan($bulan);

$harian = db_fetch_all("
    SELECT 
        DATE(tanggal) as tanggal,
        SUM(CASE WHEN jenis='pemasukan' THEN nominal ELSE 0 END) as total_pemasukan,
        SUM(CASE WHEN jenis='pengeluaran' THEN nominal ELSE 0 END) as total_pengeluaran
    FROM keuangan
    WHERE tanggal BETWEEN ? AND ?
    GROUP BY DATE(tanggal)
    ORDER BY DATE(tanggal) ASC
", [$mulai, $akhir]);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Keuangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#eef2f7;">
<div class="container py-4">

    <div class="d-flex justify-content-between mb-3">
        <h3>📊 Laporan Keuangan Klinik</h3>
        <div>
            <a href="keuangan.php" class="btn btn-dark">Manajemen Keuangan</a>
            <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
        </div>
    </div>

    <form method="GET" class="mb-3">
        <input type="month" name="bulan" value="<?= e($bulan) ?>" class="form-control" style="max-width:200px;display:inline-block;">
        <button class="btn btn-primary">Filter</button>
    </form>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white p-3">
                <h6>Total Pemasukan</h6>
                <h4><?= rupiah($ringkas['pemasukan']) ?></h4>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-danger text-white p-3">
                <h6>Total Pengeluaran</h6>
                <h4><?= rupiah($ringkas['pengeluaran']) ?></h4>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-primary text-white p-3">
                <h6>Saldo Bersih</h6>
                <h4><?= rupiah($ringkas['saldo']) ?></h4>
            </div>
        </div>
    </div>

    <table class="table table-bordered bg-white">
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Pemasukan</th>
                <th>Pengeluaran</th>
                <th>Saldo Harian</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($harian): ?>
                <?php foreach ($harian as $r): ?>
                    <tr>
                        <td><?= e($r['tanggal']) ?></td>
                        <td><?= rupiah($r['total_pemasukan']) ?></td>
                        <td><?= rupiah($r['total_pengeluaran']) ?></td>
                        <td><?= rupiah((float)$r['total_pemasukan'] - (float)$r['total_pengeluaran']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="4" class="text-center">Belum ada data.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>
</body>
</html>
