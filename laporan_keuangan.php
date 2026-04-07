<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database gagal');
}

$bulan = $_GET['bulan'] ?? date('Y-m');

$mulai = $bulan . '-01 00:00:00';
$akhir = date('Y-m-t 23:59:59', strtotime($mulai));

/**
 * ===== RINGKASAN =====
 */
$ringkasan = db_fetch_one("
    SELECT
        COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN nominal ELSE 0 END),0) AS pemasukan,
        COALESCE(SUM(CASE WHEN jenis='pengeluaran' THEN nominal ELSE 0 END),0) AS pengeluaran
    FROM keuangan
    WHERE tanggal BETWEEN ? AND ?
", [$mulai, $akhir]);

$pemasukan = (float)$ringkasan['pemasukan'];
$pengeluaran = (float)$ringkasan['pengeluaran'];
$saldo = $pemasukan - $pengeluaran;

/**
 * ===== HARIAN =====
 */
$harian = db_fetch_all("
    SELECT 
        DATE(tanggal) AS tgl,
        SUM(CASE WHEN jenis='pemasukan' THEN nominal ELSE 0 END) AS pemasukan,
        SUM(CASE WHEN jenis='pengeluaran' THEN nominal ELSE 0 END) AS pengeluaran
    FROM keuangan
    WHERE tanggal BETWEEN ? AND ?
    GROUP BY DATE(tanggal)
    ORDER BY tgl ASC
", [$mulai, $akhir]);

/**
 * ===== PER METODE BAYAR =====
 */
$metode = db_fetch_all("
    SELECT 
        i.metode_bayar,
        SUM(k.nominal) total
    FROM keuangan k
    JOIN invoice i ON i.id = k.invoice_id
    WHERE k.jenis='pemasukan'
    AND k.tanggal BETWEEN ? AND ?
    GROUP BY i.metode_bayar
    ORDER BY total DESC
", [$mulai, $akhir]);

?>
<!doctype html>
<html>
<head>
    <title>Laporan Keuangan</title>
    <style>
        body{font-family:Inter;background:#f4f8fb;margin:0;padding:24px}
        .card{background:#fff;border-radius:20px;padding:20px;margin-bottom:16px}
        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
        .stat{padding:20px;border-radius:16px;color:#fff}
        .green{background:#16a34a}
        .red{background:#dc2626}
        .blue{background:#2563eb}
        table{width:100%;border-collapse:collapse}
        th,td{padding:10px;border-bottom:1px solid #ddd}
        th{background:#f1f5f9}
    </style>
</head>
<body>

<h2>Laporan Keuangan Klinik</h2>

<form method="GET">
    <input type="month" name="bulan" value="<?= e($bulan) ?>">
    <button>Filter</button>
</form>

<div class="grid">
    <div class="stat green">
        <h4>Pemasukan</h4>
        <h2><?= rupiah($pemasukan) ?></h2>
    </div>

    <div class="stat red">
        <h4>Pengeluaran</h4>
        <h2><?= rupiah($pengeluaran) ?></h2>
    </div>

    <div class="stat blue">
        <h4>Saldo</h4>
        <h2><?= rupiah($saldo) ?></h2>
    </div>
</div>

<div class="card">
    <h3>Laporan Harian</h3>
    <table>
        <tr>
            <th>Tanggal</th>
            <th>Pemasukan</th>
            <th>Pengeluaran</th>
            <th>Saldo</th>
        </tr>

        <?php foreach ($harian as $h): ?>
        <tr>
            <td><?= e($h['tgl']) ?></td>
            <td><?= rupiah($h['pemasukan']) ?></td>
            <td><?= rupiah($h['pengeluaran']) ?></td>
            <td><?= rupiah($h['pemasukan'] - $h['pengeluaran']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <h3>Pemasukan per Metode Bayar</h3>
    <table>
        <tr>
            <th>Metode</th>
            <th>Total</th>
        </tr>

        <?php foreach ($metode as $m): ?>
        <tr>
            <td><?= strtoupper($m['metode_bayar']) ?></td>
            <td><?= rupiah($m['total']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <a href="keuangan.php">← Kembali ke Keuangan</a>
</div>

</body>
</html>
