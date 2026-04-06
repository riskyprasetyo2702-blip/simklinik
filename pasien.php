<?php
require_once __DIR__ . '/bootstrap.php';

$keyword = trim($_GET['q'] ?? '');
$editId = (int)($_GET['edit'] ?? 0);

if ($keyword !== '') {
    $pasienList = db_fetch_all(
        "SELECT * FROM pasien WHERE no_rm LIKE ? OR nama LIKE ? OR telepon LIKE ? ORDER BY id DESC",
        ["%$keyword%", "%$keyword%", "%$keyword%"]
    );
} else {
    $pasienList = db_fetch_all("SELECT * FROM pasien ORDER BY id DESC LIMIT 200");
}

$editData = null;
if ($editId > 0) {
    $editData = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$editId]);
}

function next_rm() {
    $row = db_fetch_one("SELECT no_rm FROM pasien ORDER BY id DESC LIMIT 1");
    $num = 1;
    if (!empty($row['no_rm']) && preg_match('/(\d+)$/', $row['no_rm'], $m)) {
        $num = ((int)$m[1]) + 1;
    }
    return 'RM' . str_pad((string)$num, 6, '0', STR_PAD_LEFT);
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Pasien</title>
<style>
body{font-family:Arial,sans-serif;background:#f6f8fb;margin:0;color:#1f2937}
.wrap{max-width:1200px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:18px;padding:20px;box-shadow:0 8px 24px rgba(0,0,0,.06);margin-bottom:18px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}
input,select,textarea,button{width:100%;padding:11px 12px;border:1px solid #d1d5db;border-radius:12px;box-sizing:border-box}
button,.btn{background:#111827;color:#fff;border:none;text-decoration:none;display:inline-block;text-align:center;cursor:pointer}
.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}
.actions a{margin-right:6px;text-decoration:none;padding:7px 10px;border-radius:10px;background:#eef2ff;color:#1e3a8a;display:inline-block}.small{font-size:12px;color:#6b7280}
.topbar{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}.row{display:flex;gap:10px;flex-wrap:wrap}
@media(max-width:768px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <div>
            <h1 style="margin:0">Pasien</h1>
            <div class="small">Alur disamakan dengan versi localhost, disesuaikan ke cloud.</div>
        </div>
        <div class="row">
            <a class="btn" style="padding:11px 16px" href="dashboard.php">Kembali Dashboard</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>
        <form method="get" class="row">
            <input type="text" name="q" placeholder="Cari no RM / nama / telepon" value="<?= e($keyword) ?>" style="flex:1;min-width:260px">
            <button type="submit" style="width:auto;padding:0 18px">Cari</button>
            <a href="pasien.php" class="btn" style="padding:11px 16px;width:auto;background:#4b5563">Reset</a>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0"><?= $editData ? 'Edit Pasien' : 'Tambah Pasien' ?></h2>
        <form method="post" action="simpan_pasien.php">
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">
            <div class="grid">
                <div>
                    <label>No. RM</label>
                    <input type="text" name="no_rm" required value="<?= e($editData['no_rm'] ?? next_rm()) ?>">
                </div>
                <div>
                    <label>NIK</label>
                    <input type="text" name="nik" value="<?= e($editData['nik'] ?? '') ?>">
                </div>
                <div>
                    <label>Nama Pasien</label>
                    <input type="text" name="nama" required value="<?= e($editData['nama'] ?? '') ?>">
                </div>
                <div>
                    <label>Jenis Kelamin</label>
                    <select name="jk">
                        <option value="L" <?= (($editData['jk'] ?? '') === 'L') ? 'selected' : '' ?>>Laki-laki</option>
                        <option value="P" <?= (($editData['jk'] ?? '') === 'P') ? 'selected' : '' ?>>Perempuan</option>
                    </select>
                </div>
                <div>
                    <label>Tempat Lahir</label>
                    <input type="text" name="tempat_lahir" value="<?= e($editData['tempat_lahir'] ?? '') ?>">
                </div>
                <div>
                    <label>Tanggal Lahir</label>
                    <input type="date" name="tanggal_lahir" value="<?= e($editData['tanggal_lahir'] ?? '') ?>">
                </div>
                <div>
                    <label>Telepon</label>
                    <input type="text" name="telepon" value="<?= e($editData['telepon'] ?? '') ?>">
                </div>
                <div class="full">
                    <label>Alamat</label>
                    <textarea name="alamat" rows="3"><?= e($editData['alamat'] ?? '') ?></textarea>
                </div>
                <div class="full">
                    <label>Alergi / Catatan Penting</label>
                    <textarea name="alergi" rows="2"><?= e($editData['alergi'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="row" style="margin-top:14px">
                <button type="submit" style="width:auto;padding:11px 16px">Simpan Pasien</button>
                <?php if ($editData): ?>
                    <a href="pasien.php" class="btn" style="padding:11px 16px;width:auto;background:#6b7280">Batal Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Daftar Pasien</h2>
        <div style="overflow:auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>No RM</th>
                        <th>Nama</th>
                        <th>JK</th>
                        <th>Telepon</th>
                        <th>Tanggal Lahir</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pasienList as $p): ?>
                    <tr>
                        <td><?= e($p['no_rm']) ?></td>
                        <td>
                            <strong><?= e($p['nama']) ?></strong><br>
                            <span class="small"><?= e($p['alamat']) ?></span>
                        </td>
                        <td><?= e($p['jk']) ?></td>
                        <td><?= e($p['telepon']) ?></td>
                        <td><?= e($p['tanggal_lahir']) ?></td>
                        <td class="actions">
                            <a href="pasien.php?edit=<?= (int)$p['id'] ?>">Edit</a>
                            <a href="kunjungan.php?pasien_id=<?= (int)$p['id'] ?>">Kunjungan</a>
                            <a href="invoice.php?pasien_id=<?= (int)$p['id'] ?>">Invoice</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$pasienList): ?>
                    <tr><td colspan="6">Belum ada data pasien.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
