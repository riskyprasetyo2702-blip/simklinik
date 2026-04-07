<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

$bulan = $_GET['bulan'] ?? date('Y-m');
$mulai = $bulan . '-01 00:00:00';
$akhir = date('Y-m-t 23:59:59', strtotime($mulai));

/**
 * RINGKASAN BULANAN
 */
$ringkasan = db_fetch_one("
    SELECT
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(jenis,''))='pemasukan' THEN nominal ELSE 0 END),0) AS pemasukan,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(jenis,''))='pengeluaran' THEN nominal ELSE 0 END),0) AS pengeluaran
    FROM keuangan
    WHERE tanggal BETWEEN ? AND ?
", [$mulai, $akhir]);

$pemasukan   = (float)($ringkasan['pemasukan'] ?? 0);
$pengeluaran = (float)($ringkasan['pengeluaran'] ?? 0);
$saldo       = $pemasukan - $pengeluaran;

/**
 * LAPORAN HARIAN
 */
$harian = db_fetch_all("
    SELECT 
        DATE(tanggal) AS tgl,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(jenis,''))='pemasukan' THEN nominal ELSE 0 END),0) AS pemasukan,
        COALESCE(SUM(CASE WHEN LOWER(COALESCE(jenis,''))='pengeluaran' THEN nominal ELSE 0 END),0) AS pengeluaran
    FROM keuangan
    WHERE tanggal BETWEEN ? AND ?
    GROUP BY DATE(tanggal)
    ORDER BY DATE(tanggal) ASC
", [$mulai, $akhir]);

/**
 * PEMASUKAN PER METODE BAYAR
 * Ambil hanya dari keuangan yang terkait invoice lunas
 */
$metode = db_fetch_all("
    SELECT 
        COALESCE(NULLIF(TRIM(i.metode_bayar), ''), 'tanpa metode') AS metode_bayar,
        COALESCE(SUM(k.nominal),0) AS total
    FROM keuangan k
    LEFT JOIN invoice i ON i.id = k.invoice_id
    WHERE LOWER(COALESCE(k.jenis,''))='pemasukan'
      AND k.tanggal BETWEEN ? AND ?
    GROUP BY COALESCE(NULLIF(TRIM(i.metode_bayar), ''), 'tanpa metode')
    ORDER BY total DESC, metode_bayar ASC
", [$mulai, $akhir]);

/**
 * DETAIL TRANSAKSI PEMASUKAN
 */
$transaksiPemasukan = db_fetch_all("
    SELECT
        k.tanggal,
        k.deskripsi,
        k.nominal,
        i.no_invoice,
        i.metode_bayar,
        p.no_rm,
        p.nama
    FROM keuangan k
    LEFT JOIN invoice i ON i.id = k.invoice_id
    LEFT JOIN pasien p ON p.id = k.pasien_id
    WHERE LOWER(COALESCE(k.jenis,''))='pemasukan'
      AND k.tanggal BETWEEN ? AND ?
    ORDER BY k.tanggal DESC, k.id DESC
", [$mulai, $akhir]);

/**
 * DETAIL TRANSAKSI PENGELUARAN
 */
$transaksiPengeluaran = db_fetch_all("
    SELECT
        tanggal,
        deskripsi,
        nominal
    FROM keuangan
    WHERE LOWER(COALESCE(jenis,''))='pengeluaran'
      AND tanggal BETWEEN ? AND ?
    ORDER BY tanggal DESC, id DESC
", [$mulai, $akhir]);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Keuangan</title>
    <style>
        *{box-sizing:border-box;font-family:Inter,Arial,sans-serif}
        body{margin:0;background:#f4f8fb;color:#0f172a;padding:24px}
        .wrap{max-width:1400px;margin:0 auto}
        .card{background:#fff;border-radius:24px;padding:22px;margin-bottom:18px;border:1px solid #e2e8f0;box-shadow:0 14px 30px rgba(15,23,42,.06)}
        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
        .stat{padding:20px;border-radius:18px;color:#fff}
        .green{background:linear-gradient(135deg,#16a34a,#22c55e)}
        .red{background:linear-gradient(135deg,#dc2626,#ef4444)}
        .blue{background:linear-gradient(135deg,#2563eb,#3b82f6)}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
        .btn{display:inline-block;text-decoration:none;padding:12px 16px;border-radius:14px;background:#0f172a;color:#fff;font-weight:800}
        table{width:100%;border-collapse:collapse}
        th,td{padding:11px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
        th{background:#f8fafc;color:#334155}
        .small{font-size:13px;color:#64748b}
        .right{text-align:right}
        .section-title{margin:0 0 14px;font-size:20px}
        @media(max-width:900px){.grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">

    <div class="toolbar" style="margin-bottom:16px">
        <div>
            <h2 style="margin:0">Laporan Keuangan Klinik</h2>
            <div class="small">Sumber data dari tabel keuangan yang sinkron dengan invoice lunas</div>
        </div>
        <a href="dashboard.php" class="btn">Kembali Dashboard</a>
    </div>

    <div class="card">
        <form method="GET" class="toolbar">
            <div>
                <label for="bulan" class="small">Pilih Bulan</label><br>
                <input type="month" id="bulan" name="bulan" value="<?= e($bulan) ?>" style="padding:12px 14px;border:1px solid #cbd5e1;border-radius:14px">
            </div>
            <div style="align-self:end">
                <button type="submit" class="btn" style="border:none;cursor:pointer">Filter</button>
            </div>
        </form>
    </div>

    <div class="grid">
        <div class="stat green">
            <h4>Pemasukan</h4>
            <h2><?= e(rupiah($pemasukan)) ?></h2>
        </div>

        <div class="stat red">
            <h4>Pengeluaran</h4>
            <h2><?= e(rupiah($pengeluaran)) ?></h2>
        </div>

        <div class="stat blue">
            <h4>Saldo</h4>
            <h2><?= e(rupiah($saldo)) ?></h2>
        </div>
    </div>

    <div class="card">
        <h3 class="section-title">Laporan Harian</h3>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th class="right">Pemasukan</th>
                    <th class="right">Pengeluaran</th>
                    <th class="right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($harian): ?>
                    <?php foreach ($harian as $h): ?>
                    <tr>
                        <td><?= e($h['tgl']) ?></td>
                        <td class="right"><?= e(rupiah($h['pemasukan'])) ?></td>
                        <td class="right"><?= e(rupiah($h['pengeluaran'])) ?></td>
                        <td class="right"><?= e(rupiah((float)$h['pemasukan'] - (float)$h['pengeluaran'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4">Belum ada data harian.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 class="section-title">Pemasukan per Metode Bayar</h3>
        <table>
            <thead>
                <tr>
                    <th>Metode</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($metode): ?>
                    <?php foreach ($metode as $m): ?>
                    <tr>
                        <td><?= e(strtoupper($m['metode_bayar'])) ?></td>
                        <td class="right"><?= e(rupiah($m['total'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="2">Belum ada data metode pembayaran.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 class="section-title">Detail Pemasukan</h3>
        <table>
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
                <?php if ($transaksiPemasukan): ?>
                    <?php foreach ($transaksiPemasukan as $t): ?>
                    <tr>
                        <td><?= e($t['tanggal']) ?></td>
                        <td><?= e($t['no_invoice'] ?? '-') ?></td>
                        <td>
                            <?= e($t['no_rm'] ?? '-') ?><br>
                            <span class="small"><?= e($t['nama'] ?? '-') ?></span>
                        </td>
                        <td><?= e($t['deskripsi'] ?? '-') ?></td>
                        <td><?= e(strtoupper($t['metode_bayar'] ?? '')) ?></td>
                        <td class="right"><?= e(rupiah($t['nominal'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">Belum ada transaksi pemasukan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 class="section-title">Detail Pengeluaran</h3>
        <table>
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Deskripsi</th>
                    <th class="right">Nominal</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transaksiPengeluaran): ?>
                    <?php foreach ($transaksiPengeluaran as $t): ?>
                    <tr>
                        <td><?= e($t['tanggal']) ?></td>
                        <td><?= e($t['deskripsi'] ?? '-') ?></td>
                        <td class="right"><?= e(rupiah($t['nominal'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">Belum ada transaksi pengeluaran.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
