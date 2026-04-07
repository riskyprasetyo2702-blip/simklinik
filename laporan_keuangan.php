<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
ensure_keuangan_schema();

$bulan = $_GET['bulan'] ?? date('Y-m');
$mulai = $bulan . '-01 00:00:00';
$akhir = date('Y-m-t 23:59:59', strtotime($mulai));

$ringkas = keuangan_ringkasan($bulan);

$harian = db_fetch_all("
    SELECT 
        DATE(tanggal) AS tanggal,
        COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN nominal ELSE 0 END),0) AS total_pemasukan,
        COALESCE(SUM(CASE WHEN jenis='pengeluaran' THEN nominal ELSE 0 END),0) AS total_pengeluaran
    FROM keuangan
    WHERE tanggal BETWEEN ? AND ?
    GROUP BY DATE(tanggal)
    ORDER BY DATE(tanggal) ASC
", array($mulai, $akhir));

$perMetode = db_fetch_all("
    SELECT metode_bayar, COALESCE(SUM(nominal),0) AS total
    FROM keuangan
    WHERE jenis='pemasukan' AND tanggal BETWEEN ? AND ?
    GROUP BY metode_bayar
    ORDER BY total DESC
", array($mulai, $akhir));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Keuangan</title>
    <style>
        *{box-sizing:border-box;font-family:Inter,Arial,sans-serif}
        body{margin:0;background:#eef2f7;color:#0f172a}
        .wrap{max-width:1300px;margin:0 auto;padding:24px}
        .card{background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:22px;box-shadow:0 14px 30px rgba(15,23,42,.06);margin-bottom:18px}
        .row{display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:flex-start}
        .grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        input,button{padding:13px 14px;border:1px solid #cbd5e1;border-radius:14px}
        .btn,button{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;text-align:center;border:none;font-weight:700;cursor:pointer}
        .btn.secondary{background:#475569}
        .stat{padding:20px;border-radius:20px;color:#fff}
        .success{background:linear-gradient(135deg,#16a34a,#22c55e)}
        .danger{background:linear-gradient(135deg,#dc2626,#ef4444)}
        .primary{background:linear-gradient(135deg,#2563eb,#3b82f6)}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px;border-bottom:1px solid #e2e8f0}
        .small{color:#64748b;font-size:13px}
        @media(max-width:980px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">

    <div class="row">
        <div>
            <h1 style="margin:0 0 8px">Laporan Keuangan Klinik</h1>
            <div class="small">Ringkasan pemasukan invoice lunas dan pengeluaran operasional</div>
        </div>
        <div class="row">
            <a class="btn secondary" href="dashboard.php">Dashboard</a>
            <a class="btn" href="keuangan.php">Manajemen Keuangan</a>
        </div>
    </div>

    <div class="card">
        <form method="get" class="row" style="align-items:end">
            <div>
                <label>Bulan</label><br>
                <input type="month" name="bulan" value="<?= e($bulan) ?>">
            </div>
            <div>
                <button type="submit">Filter</button>
            </div>
        </form>
    </div>

    <div class="grid">
        <div class="stat success">
            <div>Total Pemasukan</div>
            <h2 style="margin:8px 0 0"><?= e(rupiah($ringkas['pemasukan'])) ?></h2>
        </div>
        <div class="stat danger">
            <div>Total Pengeluaran</div>
            <h2 style="margin:8px 0 0"><?= e(rupiah($ringkas['pengeluaran'])) ?></h2>
        </div>
        <div class="stat primary">
            <div>Saldo Bersih</div>
            <h2 style="margin:8px 0 0"><?= e(rupiah($ringkas['saldo'])) ?></h2>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Laporan Harian</h2>
        <table>
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
                    <?php foreach ($harian as $h): ?>
                        <tr>
                            <td><?= e($h['tanggal']) ?></td>
                            <td><?= e(rupiah($h['total_pemasukan'])) ?></td>
                            <td><?= e(rupiah($h['total_pengeluaran'])) ?></td>
                            <td><strong><?= e(rupiah((float)$h['total_pemasukan'] - (float)$h['total_pengeluaran'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">Belum ada data pada periode ini.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Pemasukan per Metode Bayar</h2>
        <table>
            <thead>
                <tr>
                    <th>Metode</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($perMetode): ?>
                    <?php foreach ($perMetode as $m): ?>
                        <tr>
                            <td><?= e(strtoupper($m['metode_bayar'] ?: '-')) ?></td>
                            <td><strong><?= e(rupiah($m['total'])) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2">Belum ada pemasukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
