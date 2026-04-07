<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
ensure_keuangan_schema();

$bulan = $_GET['bulan'] ?? date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_pengeluaran') {
    $tanggal   = trim($_POST['tanggal'] ?? date('Y-m-d H:i:s'));
    $kategori  = trim($_POST['kategori'] ?? 'Operasional');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $nominal   = (float)($_POST['nominal'] ?? 0);

    if ($tanggal !== '') {
        $tanggal = str_replace('T', ' ', $tanggal);
        if (strlen($tanggal) === 16) $tanggal .= ':00';
    } else {
        $tanggal = date('Y-m-d H:i:s');
    }

    if ($nominal <= 0) {
        $_SESSION['error'] = 'Nominal pengeluaran harus lebih dari 0.';
    } else {
        tambah_pengeluaran($tanggal, $kategori, $deskripsi, $nominal);
        $_SESSION['success'] = 'Pengeluaran berhasil ditambahkan.';
    }

    header('Location: keuangan.php?bulan=' . urlencode($bulan));
    exit;
}

$mulai = $bulan . '-01 00:00:00';
$akhir = date('Y-m-t 23:59:59', strtotime($mulai));
$ringkas = keuangan_ringkasan($bulan);

$rows = db_fetch_all("
    SELECT k.*, p.nama, p.no_rm
    FROM keuangan k
    LEFT JOIN pasien p ON p.id = k.pasien_id
    WHERE k.tanggal BETWEEN ? AND ?
    ORDER BY k.tanggal DESC, k.id DESC
", array($mulai, $akhir));
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Keuangan</title>
    <style>
        *{box-sizing:border-box;font-family:Inter,Arial,sans-serif}
        body{margin:0;background:#f4f8fb;color:#0f172a}
        .wrap{max-width:1400px;margin:0 auto;padding:24px}
        .card{background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:22px;box-shadow:0 14px 30px rgba(15,23,42,.06);margin-bottom:18px}
        .head,.row{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
        .grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
        .grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        input,select,textarea,button{width:100%;padding:13px 14px;border:1px solid #cbd5e1;border-radius:14px}
        .btn,button{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;text-align:center;border:none;font-weight:700;cursor:pointer}
        .btn.secondary{background:#475569}
        .stat{padding:20px;border-radius:20px;color:#fff}
        .stat h4{margin:0 0 8px}
        .stat h2{margin:0}
        .success{background:linear-gradient(135deg,#16a34a,#22c55e)}
        .danger{background:linear-gradient(135deg,#dc2626,#ef4444)}
        .primary{background:linear-gradient(135deg,#2563eb,#3b82f6)}
        .table-wrap{overflow:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px;border-bottom:1px solid #e2e8f0;vertical-align:top}
        .badge{display:inline-block;padding:8px 12px;border-radius:999px;font-size:12px;font-weight:700}
        .in{background:#dcfce7;color:#166534}
        .out{background:#fee2e2;color:#991b1b}
        .small{color:#64748b;font-size:13px}
        @media(max-width:980px){.grid,.grid2{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="wrap">

    <div class="head">
        <div>
            <h1 style="margin:0 0 8px">Manajemen Keuangan</h1>
            <div class="small">Pemasukan otomatis dari invoice lunas dan pengeluaran manual klinik</div>
        </div>
        <div class="row">
            <a class="btn secondary" href="dashboard.php">Dashboard</a>
            <a class="btn" href="invoice.php">Billing</a>
            <a class="btn secondary" href="laporan_keuangan.php">Laporan</a>
        </div>
    </div>

    <?php flash_message(); ?>

    <div class="card">
        <form method="get" class="row" style="align-items:end">
            <div style="min-width:220px">
                <label>Bulan</label>
                <input type="month" name="bulan" value="<?= e($bulan) ?>">
            </div>
            <div>
                <button type="submit" style="width:auto;padding:13px 18px">Filter</button>
            </div>
        </form>
    </div>

    <div class="grid">
        <div class="stat success">
            <h4>Total Pemasukan</h4>
            <h2><?= e(rupiah($ringkas['pemasukan'])) ?></h2>
        </div>
        <div class="stat danger">
            <h4>Total Pengeluaran</h4>
            <h2><?= e(rupiah($ringkas['pengeluaran'])) ?></h2>
        </div>
        <div class="stat primary">
            <h4>Saldo Bersih</h4>
            <h2><?= e(rupiah($ringkas['saldo'])) ?></h2>
        </div>
        <div class="stat" style="background:linear-gradient(135deg,#0f172a,#334155)">
            <h4>Periode</h4>
            <h2><?= e($bulan) ?></h2>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Tambah Pengeluaran</h2>
        <form method="post">
            <input type="hidden" name="aksi" value="tambah_pengeluaran">
            <div class="grid2">
                <div>
                    <label>Tanggal</label>
                    <input type="datetime-local" name="tanggal" value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div>
                    <label>Kategori</label>
                    <select name="kategori">
                        <option value="Operasional">Operasional</option>
                        <option value="Bahan">Bahan</option>
                        <option value="Gaji">Gaji</option>
                        <option value="Sewa">Sewa</option>
                        <option value="Listrik / Air / Internet">Listrik / Air / Internet</option>
                        <option value="Maintenance">Maintenance</option>
                        <option value="Lain-lain">Lain-lain</option>
                    </select>
                </div>
                <div>
                    <label>Deskripsi</label>
                    <input type="text" name="deskripsi" placeholder="Contoh: beli bahan tambal atau bayar listrik">
                </div>
                <div>
                    <label>Nominal</label>
                    <input type="number" step="0.01" name="nominal" min="0">
                </div>
            </div>
            <div style="margin-top:16px">
                <button type="submit" style="width:auto;padding:13px 18px;background:#dc2626">Simpan Pengeluaran</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Jurnal Keuangan</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th>Pasien</th>
                        <th>Referensi</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th>Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e($r['tanggal']) ?></td>
                                <td>
                                    <?php if (($r['jenis'] ?? '') === 'pemasukan'): ?>
                                        <span class="badge in">Pemasukan</span>
                                    <?php else: ?>
                                        <span class="badge out">Pengeluaran</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($r['kategori'] ?? '-') ?></td>
                                <td><?= e($r['deskripsi'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($r['nama'])): ?>
                                        <strong><?= e($r['no_rm']) ?></strong><br><?= e($r['nama']) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= e($r['referensi_no'] ?? '-') ?></td>
                                <td><?= e(strtoupper($r['metode_bayar'] ?? '-')) ?></td>
                                <td><?= e($r['status'] ?? '-') ?></td>
                                <td><strong><?= e(rupiah($r['nominal'])) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9">Belum ada data keuangan pada periode ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
