<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
ensure_keuangan_table();

$bulan = $_GET['bulan'] ?? date('Y-m');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'tambah_pengeluaran') {
    $tanggal   = trim($_POST['tanggal'] ?? date('Y-m-d H:i:s'));
    $kategori  = trim($_POST['kategori'] ?? 'Operasional');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $nominal   = (float)($_POST['nominal'] ?? 0);

    if ($nominal > 0) {
        tambah_pengeluaran($tanggal, $kategori, $deskripsi, $nominal);
        $_SESSION['success'] = 'Pengeluaran berhasil ditambahkan.';
    } else {
        $_SESSION['error'] = 'Nominal pengeluaran harus lebih dari 0.';
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
", [$mulai, $akhir]);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manajemen Keuangan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body{background:#eef4f8}
        .cardx{background:#fff;border:1px solid #dde7ef;border-radius:22px;padding:22px;box-shadow:0 10px 24px rgba(0,0,0,.05)}
        .stat{border-radius:20px;padding:20px;color:#fff}
        .stat h6{margin:0 0 8px;font-size:14px}
        .stat h3{margin:0;font-weight:800}
    </style>
</head>
<body>
<div class="container py-4">

    <?php flash_message(); ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-1">Manajemen Keuangan Klinik</h2>
            <div class="text-muted">Pemasukan otomatis dari invoice lunas + pengeluaran manual</div>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-secondary">Dashboard</a>
            <a href="laporan_keuangan.php" class="btn btn-dark">Laporan</a>
        </div>
    </div>

    <form method="get" class="mb-4">
        <div class="row g-2">
            <div class="col-md-3">
                <input type="month" name="bulan" value="<?= e($bulan) ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Filter</button>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat bg-success">
                <h6>Total Pemasukan</h6>
                <h3><?= rupiah($ringkas['pemasukan']) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat bg-danger">
                <h6>Total Pengeluaran</h6>
                <h3><?= rupiah($ringkas['pengeluaran']) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat bg-primary">
                <h6>Saldo Bersih</h6>
                <h3><?= rupiah($ringkas['saldo']) ?></h3>
            </div>
        </div>
    </div>

    <div class="cardx mb-4">
        <h4 class="mb-3">Tambah Pengeluaran</h4>
        <form method="post">
            <input type="hidden" name="aksi" value="tambah_pengeluaran">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal</label>
                    <input type="datetime-local" name="tanggal" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Kategori</label>
                    <select name="kategori" class="form-select">
                        <option>Operasional</option>
                        <option>Bahan</option>
                        <option>Gaji</option>
                        <option>Sewa</option>
                        <option>Listrik / Air / Internet</option>
                        <option>Maintenance</option>
                        <option>Lain-lain</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Deskripsi</label>
                    <input type="text" name="deskripsi" class="form-control" placeholder="Contoh: beli bahan tambal">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Nominal</label>
                    <input type="number" step="0.01" name="nominal" class="form-control" required>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-danger">Simpan Pengeluaran</button>
            </div>
        </form>
    </div>

    <div class="cardx">
        <h4 class="mb-3">Jurnal Keuangan</h4>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jenis</th>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th>Pasien</th>
                        <th>Referensi</th>
                        <th>Metode</th>
                        <th class="text-end">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): ?>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= e($r['tanggal']) ?></td>
                                <td>
                                    <?php if ($r['jenis'] === 'pemasukan'): ?>
                                        <span class="badge bg-success">Pemasukan</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Pengeluaran</span>
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
                                <td class="text-end fw-bold"><?= rupiah($r['nominal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center text-muted">Belum ada data keuangan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>