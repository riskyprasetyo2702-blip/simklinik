<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

/**
 * Pastikan tabel keuangan ada dengan struktur minimal
 * Aman untuk sistem lama karena hanya membuat jika belum ada
 */
if (!table_exists($conn, 'keuangan')) {
    $conn->query("
        CREATE TABLE keuangan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATETIME NOT NULL,
            jenis VARCHAR(30) NOT NULL,
            deskripsi TEXT DEFAULT NULL,
            nominal DECIMAL(15,2) NOT NULL DEFAULT 0,
            invoice_id INT DEFAULT NULL,
            pasien_id INT DEFAULT NULL,
            INDEX idx_tanggal (tanggal),
            INDEX idx_jenis (jenis),
            INDEX idx_invoice_id (invoice_id),
            INDEX idx_pasien_id (pasien_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

$bulan = $_GET['bulan'] ?? date('Y-m');
$mode  = $_GET['mode'] ?? 'semua';

$mulai = $bulan . '-01 00:00:00';
$akhir = date('Y-m-t 23:59:59', strtotime($mulai));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah_pengeluaran') {
        $tanggal    = trim($_POST['tanggal'] ?? date('Y-m-d H:i:s'));
        $deskripsi  = trim($_POST['deskripsi'] ?? '');
        $nominal    = (float)($_POST['nominal'] ?? 0);

        $tanggal = str_replace('T', ' ', $tanggal);
        if (strlen($tanggal) === 16) {
            $tanggal .= ':00';
        }

        if ($deskripsi === '') {
            $_SESSION['error'] = 'Deskripsi pengeluaran wajib diisi.';
        } elseif ($nominal <= 0) {
            $_SESSION['error'] = 'Nominal pengeluaran harus lebih dari 0.';
        } else {
            db_insert(
                "INSERT INTO keuangan (tanggal, jenis, deskripsi, nominal, invoice_id, pasien_id)
                 VALUES (?, 'pengeluaran', ?, ?, NULL, NULL)",
                [$tanggal, $deskripsi, $nominal]
            );
            $_SESSION['success'] = 'Pengeluaran berhasil ditambahkan.';
        }

        header('Location: keuangan.php?bulan=' . urlencode($bulan) . '&mode=' . urlencode($mode));
        exit;
    }

    if ($aksi === 'hapus_manual') {
        $id = (int)($_POST['id'] ?? 0);

        $cek = db_fetch_one("SELECT * FROM keuangan WHERE id=?", [$id]);
        if ($cek) {
            if (($cek['jenis'] ?? '') === 'pengeluaran' && empty($cek['invoice_id'])) {
                db_run("DELETE FROM keuangan WHERE id=?", [$id]);
                $_SESSION['success'] = 'Data pengeluaran berhasil dihapus.';
            } else {
                $_SESSION['error'] = 'Data ini tidak bisa dihapus dari menu ini.';
            }
        } else {
            $_SESSION['error'] = 'Data tidak ditemukan.';
        }

        header('Location: keuangan.php?bulan=' . urlencode($bulan) . '&mode=' . urlencode($mode));
        exit;
    }
}

/**
 * Ringkasan
 */
$summary = db_fetch_one("
    SELECT
        COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN nominal ELSE 0 END),0) AS total_pemasukan,
        COALESCE(SUM(CASE WHEN jenis='pengeluaran' THEN nominal ELSE 0 END),0) AS total_pengeluaran
    FROM keuangan
    WHERE tanggal BETWEEN ? AND ?
", [$mulai, $akhir]);

$totalPemasukan  = (float)($summary['total_pemasukan'] ?? 0);
$totalPengeluaran = (float)($summary['total_pengeluaran'] ?? 0);
$saldoBersih     = $totalPemasukan - $totalPengeluaran;

/**
 * Filter mode
 */
$whereMode = "";
$params = [$mulai, $akhir];

if ($mode === 'pemasukan') {
    $whereMode = " AND k.jenis='pemasukan' ";
}
if ($mode === 'pengeluaran') {
    $whereMode = " AND k.jenis='pengeluaran' ";
}

/**
 * Data jurnal keuangan
 */
$rows = db_fetch_all("
    SELECT
        k.*,
        p.no_rm,
        p.nama,
        i.no_invoice,
        i.metode_bayar,
        i.status_bayar
    FROM keuangan k
    LEFT JOIN pasien p ON p.id = k.pasien_id
    LEFT JOIN invoice i ON i.id = k.invoice_id
    WHERE k.tanggal BETWEEN ? AND ?
    $whereMode
    ORDER BY k.tanggal DESC, k.id DESC
", $params);

/**
 * Ringkasan harian
 */
$harian = db_fetch_all("
    SELECT
        DATE(tanggal) AS tgl,
        COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN nominal ELSE 0 END),0) AS pemasukan,
        COALESCE(SUM(CASE WHEN jenis='pengeluaran' THEN nominal ELSE 0 END),0) AS pengeluaran
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
    <title>Manajemen Keuangan</title>
    <style>
        *{box-sizing:border-box;font-family:Inter,Arial,sans-serif}
        body{margin:0;background:#f8fbff;color:#0f172a}
        .wrap{max-width:1450px;margin:0 auto;padding:24px}
        .card{background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:22px;box-shadow:0 14px 30px rgba(15,23,42,.06);margin-bottom:18px}
        .head,.row{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
        .grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
        .grid2{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
        .grid-form{display:grid;grid-template-columns:1.2fr 2fr 1fr auto;gap:12px;align-items:end}
        input,select,textarea,button{width:100%;padding:13px 14px;border:1px solid #cbd5e1;border-radius:14px}
        .btn,button{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;text-align:center;border:none;font-weight:700;cursor:pointer}
        .btn.secondary{background:#475569}
        .btn.green{background:#059669}
        .btn.red{background:#dc2626}
        .btn.orange{background:#d97706}
        .stat{padding:20px;border-radius:22px;color:#fff}
        .stat h4{margin:0 0 8px;font-size:15px}
        .stat h2{margin:0;font-size:28px}
        .success{background:linear-gradient(135deg,#16a34a,#22c55e)}
        .danger{background:linear-gradient(135deg,#dc2626,#ef4444)}
        .primary{background:linear-gradient(135deg,#2563eb,#3b82f6)}
        .dark{background:linear-gradient(135deg,#0f172a,#334155)}
        .table-wrap{overflow:auto}
        table{width:100%;border-collapse:collapse}
        th,td{padding:12px;border-bottom:1px solid #e2e8f0;vertical-align:top}
        th{background:#f8fafc;text-align:left;color:#334155}
        .badge{display:inline-block;padding:8px 12px;border-radius:999px;font-size:12px;font-weight:700}
        .in{background:#dcfce7;color:#166534}
        .out{background:#fee2e2;color:#991b1b}
        .small{color:#64748b;font-size:13px}
        .right{text-align:right}
        .center{text-align:center}
        .section-title{margin:0 0 6px;font-size:24px}
        @media(max-width:980px){
            .grid,.grid2,.grid-form{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
<div class="wrap">

    <div class="head">
        <div>
            <h1 class="section-title">Manajemen Keuangan</h1>
            <div class="small">Terhubung dengan invoice lunas dan input pengeluaran manual</div>
        </div>
        <div class="row">
            <a class="btn secondary" href="dashboard.php">Dashboard</a>
            <a class="btn" href="invoice.php">Billing</a>
            <a class="btn secondary" href="laporan_keuangan.php">Laporan</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>

        <form method="get" class="grid2">
            <div>
                <label>Bulan</label>
                <input type="month" name="bulan" value="<?= e($bulan) ?>">
            </div>
            <div>
                <label>Filter Jenis</label>
                <select name="mode">
                    <option value="semua" <?= $mode === 'semua' ? 'selected' : '' ?>>Semua</option>
                    <option value="pemasukan" <?= $mode === 'pemasukan' ? 'selected' : '' ?>>Pemasukan</option>
                    <option value="pengeluaran" <?= $mode === 'pengeluaran' ? 'selected' : '' ?>>Pengeluaran</option>
                </select>
            </div>
            <div style="display:flex;align-items:end">
                <button type="submit">Filter Data</button>
            </div>
        </form>
    </div>

    <div class="grid">
        <div class="stat success">
            <h4>Total Pemasukan</h4>
            <h2><?= e(rupiah($totalPemasukan)) ?></h2>
        </div>
        <div class="stat danger">
            <h4>Total Pengeluaran</h4>
            <h2><?= e(rupiah($totalPengeluaran)) ?></h2>
        </div>
        <div class="stat primary">
            <h4>Saldo Bersih</h4>
            <h2><?= e(rupiah($saldoBersih)) ?></h2>
        </div>
        <div class="stat dark">
            <h4>Periode</h4>
            <h2><?= e($bulan) ?></h2>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Tambah Pengeluaran Manual</h2>
        <form method="post" class="grid-form">
            <input type="hidden" name="aksi" value="tambah_pengeluaran">

            <div>
                <label>Tanggal</label>
                <input type="datetime-local" name="tanggal" value="<?= date('Y-m-d\TH:i') ?>">
            </div>

            <div>
                <label>Deskripsi</label>
                <input type="text" name="deskripsi" placeholder="Contoh: beli bahan, listrik, maintenance kursi gigi">
            </div>

            <div>
                <label>Nominal</label>
                <input type="number" step="0.01" name="nominal" min="0">
            </div>

            <div>
                <button type="submit" class="btn red">Simpan Pengeluaran</button>
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
                        <th>Deskripsi</th>
                        <th>Pasien</th>
                        <th>Invoice</th>
                        <th>Metode</th>
                        <th>Status Invoice</th>
                        <th class="right">Nominal</th>
                        <th class="center">Aksi</th>
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
                                <td><?= e($r['deskripsi'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($r['nama'])): ?>
                                        <strong><?= e($r['no_rm']) ?></strong><br>
                                        <?= e($r['nama']) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($r['no_invoice'])): ?>
                                        <?= e($r['no_invoice']) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?= e(strtoupper($r['metode_bayar'] ?? '-')) ?></td>
                                <td><?= e($r['status_bayar'] ?? '-') ?></td>
                                <td class="right"><strong><?= e(rupiah($r['nominal'])) ?></strong></td>
                                <td class="center">
                                    <?php if (($r['jenis'] ?? '') === 'pengeluaran' && empty($r['invoice_id'])): ?>
                                        <form method="post" onsubmit="return confirm('Hapus pengeluaran ini?')" style="margin:0">
                                            <input type="hidden" name="aksi" value="hapus_manual">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="btn red" style="padding:8px 12px;width:auto">Hapus</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="small">Auto</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">Belum ada data keuangan pada periode ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Ringkasan Harian</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th class="right">Pemasukan</th>
                        <th class="right">Pengeluaran</th>
                        <th class="right">Saldo Harian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($harian): ?>
                        <?php foreach ($harian as $h): ?>
                            <tr>
                                <td><?= e($h['tgl']) ?></td>
                                <td class="right"><?= e(rupiah($h['pemasukan'])) ?></td>
                                <td class="right"><?= e(rupiah($h['pengeluaran'])) ?></td>
                                <td class="right"><strong><?= e(rupiah((float)$h['pemasukan'] - (float)$h['pengeluaran'])) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4">Belum ada ringkasan harian.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
