<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

$tanggal_mulai = trim($_GET['tanggal_mulai'] ?? date('Y-m-01'));
$tanggal_selesai = trim($_GET['tanggal_selesai'] ?? date('Y-m-d'));

/*
|--------------------------------------------------------------------------
| Data keuangan
|--------------------------------------------------------------------------
*/
$keuanganList = [];
$totalPemasukan = 0;

if (table_exists($conn, 'keuangan')) {
    $keuanganList = db_fetch_all("
        SELECT k.*, p.nama, i.no_invoice, i.metode_bayar
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
| Rekap metode bayar dari invoice lunas
|--------------------------------------------------------------------------
*/
$metodeBayarList = [];
if (table_exists($conn, 'invoice')) {
    $metodeBayarList = db_fetch_all("
        SELECT metode_bayar, COUNT(*) as jumlah, COALESCE(SUM(total),0) as total
        FROM invoice
        WHERE LOWER(status_bayar) = 'lunas'
        AND DATE(tanggal) BETWEEN ? AND ?
        GROUP BY metode_bayar
        ORDER BY total DESC
    ", [$tanggal_mulai, $tanggal_selesai]);
}

/*
|--------------------------------------------------------------------------
| Rekap status invoice
|--------------------------------------------------------------------------
*/
$statusInvoiceList = [];
if (table_exists($conn, 'invoice')) {
    $statusInvoiceList = db_fetch_all("
        SELECT status_bayar, COUNT(*) as jumlah, COALESCE(SUM(total),0) as total
        FROM invoice
        WHERE DATE(tanggal) BETWEEN ? AND ?
        GROUP BY status_bayar
        ORDER BY jumlah DESC
    ", [$tanggal_mulai, $tanggal_selesai]);
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
.wrap{max-width:1280px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:20px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);margin-bottom:18px}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.stat{
    background:linear-gradient(135deg,#0f172a,#1d4ed8);
    color:#fff;
    border-radius:18px;
    padding:20px;
}
.stat .label{font-size:13px;opacity:.9;margin-bottom:8px}
.stat .value{font-size:28px;font-weight:800}
input,button{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px}
button,.btn{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;border:none;font-weight:700;cursor:pointer;padding:12px 16px;border-radius:12px}
.btn.secondary{background:#475569}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#e2e8f0;font-size:12px}
.right{text-align:right}
.small{font-size:13px;color:#64748b}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
@media print{
    .no-print{display:none!important}
    body{background:#fff}
    .card{box-shadow:none;border:none}
}
</style>
</head>
<body>
<div class="wrap">

    <div class="row no-print" style="margin-bottom:16px">
        <div>
            <h1 style="margin:0">Laporan Keuangan</h1>
            <div class="small">Rekap pemasukan dan invoice klinik.</div>
        </div>
        <div class="row">
            <a class="btn secondary" href="dashboard.php">Dashboard</a>
            <button type="button" class="btn" onclick="window.print()">Print</button>
        </div>
    </div>

    <div class="card no-print">
        <form method="get" class="row">
            <div style="min-width:220px">
                <label>Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="<?= e($tanggal_mulai) ?>">
            </div>
            <div style="min-width:220px">
                <label>Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" value="<?= e($tanggal_selesai) ?>">
            </div>
            <div style="align-self:end">
                <button type="submit">Tampilkan</button>
            </div>
        </form>
    </div>

    <div class="grid">
        <div class="stat">
            <div class="label">Total Pemasukan</div>
            <div class="value"><?= rupiah($totalPemasukan) ?></div>
        </div>
        <div class="stat">
            <div class="label">Jumlah Transaksi Keuangan</div>
            <div class="value"><?= count($keuanganList) ?></div>
        </div>
        <div class="stat">
            <div class="label">Periode</div>
            <div class="value" style="font-size:20px"><?= e($tanggal_mulai) ?> s/d <?= e($tanggal_selesai) ?></div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Rekap Metode Pembayaran</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Metode Bayar</th>
                        <th>Jumlah Invoice Lunas</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($metodeBayarList as $m): ?>
                        <tr>
                            <td><span class="badge"><?= strtoupper(e($m['metode_bayar'] ?? '-')) ?></span></td>
                            <td><?= e($m['jumlah'] ?? 0) ?></td>
                            <td class="right"><?= rupiah($m['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$metodeBayarList): ?>
                        <tr><td colspan="3">Belum ada data metode pembayaran.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Rekap Status Invoice</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Jumlah</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($statusInvoiceList as $s): ?>
                        <tr>
                            <td><span class="badge"><?= e($s['status_bayar'] ?? '-') ?></span></td>
                            <td><?= e($s['jumlah'] ?? 0) ?></td>
                            <td class="right"><?= rupiah($s['total'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$statusInvoiceList): ?>
                        <tr><td colspan="3">Belum ada data invoice.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Detail Pemasukan</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Invoice</th>
                        <th>Pasien</th>
                        <th>Deskripsi</th>
                        <th>Metode</th>
                        <th class="right">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($keuanganList as $k): ?>
                        <tr>
                            <td><?= e($k['tanggal'] ?? '') ?></td>
                            <td><?= e($k['no_invoice'] ?? '-') ?></td>
                            <td><?= e($k['nama'] ?? '-') ?></td>
                            <td><?= e($k['deskripsi'] ?? '-') ?></td>
                            <td><?= strtoupper(e($k['metode_bayar'] ?? '-')) ?></td>
                            <td class="right"><?= rupiah($k['nominal'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$keuanganList): ?>
                        <tr><td colspan="6">Belum ada data pemasukan.</td></tr>
                    <?php endif; ?>
                </tbody>
                <?php if ($keuanganList): ?>
                <tfoot>
                    <tr>
                        <th colspan="5" class="right">Total</th>
                        <th class="right"><?= rupiah($totalPemasukan) ?></th>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

</div>
</body>
</html>
