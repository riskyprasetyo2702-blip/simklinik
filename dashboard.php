<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

function safe_table_exists_local($conn, $table) {
    return function_exists('table_exists') ? table_exists($conn, $table) : false;
}

$bulanIni   = date('Y-m');
$awalBulan  = date('Y-m-01 00:00:00');
$akhirBulan = date('Y-m-t 23:59:59');

$totalPasien       = 0;
$totalKunjungan    = 0;
$totalInvoice      = 0;
$totalPendapatan   = 0;
$totalBelumLunas   = 0;
$kunjunganTerakhir = [];
$invoiceTerakhir   = [];
$grafikLabel       = [];
$grafikData        = [];

if (safe_table_exists_local($conn, 'pasien')) {
    $row = db_fetch_one("SELECT COUNT(*) AS total FROM pasien");
    $totalPasien = (int)($row['total'] ?? 0);
}

if (safe_table_exists_local($conn, 'kunjungan')) {
    $row = db_fetch_one("SELECT COUNT(*) AS total FROM kunjungan WHERE tanggal BETWEEN ? AND ?", [$awalBulan, $akhirBulan]);
    $totalKunjungan = (int)($row['total'] ?? 0);

    $kunjunganTerakhir = db_fetch_all("
        SELECT k.tanggal, p.no_rm, p.nama, COALESCE(k.tindakan, k.diagnosa, '-') AS tindakan
        FROM kunjungan k
        LEFT JOIN pasien p ON p.id = k.pasien_id
        ORDER BY k.tanggal DESC, k.id DESC
        LIMIT 6
    ");
}

if (safe_table_exists_local($conn, 'invoice')) {
    $row = db_fetch_one("SELECT COUNT(*) AS total FROM invoice");
    $totalInvoice = (int)($row['total'] ?? 0);

    $row = db_fetch_one("
        SELECT COALESCE(SUM(total),0) AS total
        FROM invoice
        WHERE LOWER(COALESCE(status_bayar,'')) IN ('pending','belum terbayar')
    ");
    $totalBelumLunas = (float)($row['total'] ?? 0);

    $invoiceTerakhir = db_fetch_all("
        SELECT i.no_invoice, i.tanggal, i.total, i.status_bayar, p.nama
        FROM invoice i
        LEFT JOIN pasien p ON p.id = i.pasien_id
        ORDER BY i.tanggal DESC, i.id DESC
        LIMIT 6
    ");
}

if (safe_table_exists_local($conn, 'keuangan')) {
    $row = db_fetch_one("
        SELECT COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN nominal ELSE 0 END),0) AS total
        FROM keuangan
        WHERE tanggal BETWEEN ? AND ?
    ", [$awalBulan, $akhirBulan]);

    $totalPendapatan = (float)($row['total'] ?? 0);

    $grafik = db_fetch_all("
        SELECT DATE(tanggal) AS tgl,
               COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN nominal ELSE 0 END),0) AS total
        FROM keuangan
        WHERE tanggal BETWEEN ? AND ?
        GROUP BY DATE(tanggal)
        ORDER BY DATE(tanggal) ASC
    ", [$awalBulan, $akhirBulan]);

    foreach ($grafik as $g) {
        $grafikLabel[] = date('d M', strtotime($g['tgl']));
        $grafikData[]  = (float)($g['total'] ?? 0);
    }
}

if (!$grafikLabel) {
    for ($i = 6; $i >= 0; $i--) {
        $grafikLabel[] = date('d M', strtotime("-$i days"));
        $grafikData[]  = 0;
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard SIM Klinik Gigi</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        *{box-sizing:border-box;font-family:Inter,Arial,sans-serif}
        body{margin:0;background:#f4f7fb;color:#0f172a}
        .page{max-width:1450px;margin:0 auto;padding:22px}
        .topbar{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:22px}
        .brand{display:flex;align-items:center;gap:12px}
        .brand-badge{
            width:46px;height:46px;border-radius:16px;
            background:linear-gradient(135deg,#2563eb,#60a5fa);
            display:flex;align-items:center;justify-content:center;
            color:#fff;font-size:22px;font-weight:800
        }
        .brand h1{margin:0;font-size:24px}
        .brand p{margin:4px 0 0;color:#64748b}
        .nav{display:flex;gap:10px;flex-wrap:wrap}
        .nav a{
            text-decoration:none;color:#334155;background:#fff;border:1px solid #d9e3ef;
            padding:12px 18px;border-radius:14px;font-weight:700;
            box-shadow:0 8px 22px rgba(15,23,42,.04)
        }
        .nav a.active{background:#dbeafe;color:#1d4ed8;border-color:#bfdbfe}

        .grid4{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;margin-bottom:18px}
        .grid2{display:grid;grid-template-columns:1.45fr 1fr;gap:18px}

        .card{
            background:#fff;border:1px solid #e2e8f0;border-radius:26px;padding:22px;
            box-shadow:0 14px 30px rgba(15,23,42,.06)
        }

        .stat{position:relative;overflow:hidden;min-height:150px}
        .stat h3{margin:0 0 20px;font-size:14px;color:#334155}
        .stat .num{font-size:28px;font-weight:800;margin-bottom:8px}
        .stat .caption{color:#475569;font-size:14px}
        .stat .icon{position:absolute;right:18px;top:16px;font-size:24px;opacity:.85}

        .blue{background:linear-gradient(135deg,#dbeafe,#eff6ff)}
        .yellow{background:linear-gradient(135deg,#fef3c7,#fff7dd)}
        .rose{background:linear-gradient(135deg,#ffe4e6,#fff1f2)}
        .green{background:linear-gradient(135deg,#dcfce7,#f0fdf4)}

        .section-title{margin:0 0 16px;font-size:18px}
        .subcards{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}
        .mini{padding:16px 18px;border-radius:18px;font-weight:700}
        .mini .small{display:block;color:#64748b;font-size:13px;font-weight:600;margin-bottom:8px}
        .mini-blue{background:#e0f2fe}
        .mini-rose{background:#ffe4e6;color:#9f1239}

        .table{width:100%;border-collapse:collapse}
        .table th,.table td{padding:14px 10px;border-bottom:1px solid #e5e7eb;vertical-align:top;text-align:left}
        .table th{color:#334155}
        .muted{color:#64748b}
        .nowrap{white-space:nowrap}
        .badge{
            display:inline-block;padding:7px 12px;border-radius:999px;font-size:12px;font-weight:700
        }
        .badge.lunas{background:#dcfce7;color:#166534}
        .badge.pending{background:#fef3c7;color:#92400e}
        .badge.belum{background:#fee2e2;color:#991b1b}

        .action-row{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:16px;flex-wrap:wrap}
        .btn-primary{
            display:inline-block;text-decoration:none;background:#3f8b5f;color:#fff;
            padding:16px 26px;border-radius:18px;font-weight:800
        }
        .link-more{text-decoration:none;color:#35548b;font-weight:700}

        @media (max-width:1200px){
            .grid4{grid-template-columns:repeat(2,minmax(0,1fr))}
            .grid2{grid-template-columns:1fr}
        }
        @media (max-width:700px){
            .grid4{grid-template-columns:1fr}
            .subcards{grid-template-columns:1fr}
            .nav{width:100%}
            .nav a{flex:1;text-align:center}
        }
    </style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div class="brand">
            <div class="brand-badge">🦷</div>
            <div>
                <h1>Dashboard SIM Klinik Gigi</h1>
                <p>Ringkasan pasien, kunjungan, invoice, dan laporan keuangan</p>
            </div>
        </div>
        <div class="nav">
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="pasien.php">Pasien</a>
            <a href="kunjungan.php">Kunjungan</a>
            <a href="invoice.php">Invoice</a>
            <a href="laporan_keuangan.php">Keuangan</a>
        </div>
    </div>

    <?php flash_message(); ?>

    <div class="grid4">
        <div class="card stat blue">
            <div class="icon">👥</div>
            <h3>Total Pasien</h3>
            <div class="num"><?= (int)$totalPasien ?></div>
            <div class="caption">Data pasien aktif di sistem</div>
        </div>

        <div class="card stat yellow">
            <div class="icon">🩺</div>
            <h3>Kunjungan Bulan Ini</h3>
            <div class="num"><?= (int)$totalKunjungan ?></div>
            <div class="caption">Jumlah kunjungan pada periode berjalan</div>
        </div>

        <div class="card stat rose">
            <div class="icon">🧾</div>
            <h3>Invoice Belum Lunas</h3>
            <div class="num"><?= e(rupiah($totalBelumLunas)) ?></div>
            <div class="caption">Total outstanding billing pasien</div>
        </div>

        <div class="card stat green">
            <div class="icon">💰</div>
            <h3>Pendapatan Bulan Ini</h3>
            <div class="num"><?= e(rupiah($totalPendapatan)) ?></div>
            <div class="caption">Masuk dari tabel keuangan</div>
        </div>
    </div>

    <div class="grid2">
        <div class="card">
            <h3 class="section-title">Grafik Pemasukan</h3>
            <canvas id="incomeChart" height="120"></canvas>

            <div class="subcards">
                <div class="mini mini-blue">
                    <span class="small">Total invoice</span>
                    <div style="font-size:20px;"><?= (int)$totalInvoice ?></div>
                </div>
                <div class="mini mini-rose">
                    <span class="small">Belum lunas</span>
                    <div style="font-size:20px;"><?= e(rupiah($totalBelumLunas)) ?></div>
                </div>
            </div>

            <div class="action-row">
                <a class="btn-primary" href="invoice.php">Buat Invoice Baru</a>
                <a class="link-more" href="laporan_keuangan.php">Lihat laporan keuangan ›</a>
            </div>
        </div>

        <div class="card">
            <h3 class="section-title">Invoice Terakhir</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>No Invoice</th>
                        <th>Pasien</th>
                        <th>Total</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($invoiceTerakhir): ?>
                        <?php foreach ($invoiceTerakhir as $inv): ?>
                            <?php
                            $st = strtolower((string)($inv['status_bayar'] ?? ''));
                            $cls = $st === 'lunas' ? 'lunas' : ($st === 'pending' ? 'pending' : 'belum');
                            ?>
                            <tr>
                                <td class="nowrap"><?= e($inv['no_invoice'] ?? '-') ?></td>
                                <td><?= e($inv['nama'] ?? '-') ?></td>
                                <td class="nowrap"><?= e(rupiah($inv['total'] ?? 0)) ?></td>
                                <td><span class="badge <?= e($cls) ?>"><?= e($inv['status_bayar'] ?? '-') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="muted">Belum ada invoice.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-top:18px;">
        <div class="action-row" style="margin-top:0;margin-bottom:10px;">
            <h3 class="section-title" style="margin:0;">Kunjungan Pasien Terakhir</h3>
            <a class="link-more" href="kunjungan.php">Lihat semua kunjungan ›</a>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Pasien</th>
                    <th>Tindakan / Diagnosa</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($kunjunganTerakhir): ?>
                    <?php foreach ($kunjunganTerakhir as $k): ?>
                        <tr>
                            <td class="nowrap"><?= e(date('d M Y', strtotime($k['tanggal'] ?? 'now'))) ?></td>
                            <td>
                                <div><?= e($k['no_rm'] ?? '-') ?></div>
                                <div class="muted"><?= e($k['nama'] ?? '-') ?></div>
                            </td>
                            <td><?= e($k['tindakan'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="muted">Belum ada data kunjungan.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const chartLabels = <?= json_encode($grafikLabel) ?>;
const chartData   = <?= json_encode($grafikData) ?>;

const ctx = document.getElementById('incomeChart');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: chartLabels,
        datasets: [{
            label: 'Pemasukan',
            data: chartData,
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59,130,246,0.12)',
            tension: 0.35,
            fill: true,
            pointRadius: 4,
            pointHoverRadius: 5
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return 'Rp ' + Number(value).toLocaleString('id-ID');
                    }
                }
            }
        }
    }
});
</script>
</body>
</html>
