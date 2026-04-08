<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

$keyword = trim($_GET['q'] ?? '');
$editId  = (int)($_GET['edit'] ?? 0);

if (!table_exists($conn, 'pasien')) {
    die('Tabel pasien tidak ditemukan di database.');
}

if ($keyword !== '') {
    $pasienList = db_fetch_all(
        "SELECT * FROM pasien
         WHERE no_rm LIKE ? OR nama LIKE ? OR telepon LIKE ? OR nik LIKE ?
         ORDER BY id DESC",
        ["%$keyword%", "%$keyword%", "%$keyword%", "%$keyword%"]
    );
} else {
    $pasienList = db_fetch_all("SELECT * FROM pasien ORDER BY id DESC LIMIT 200");
}

$editData = null;
if ($editId > 0) {
    $editData = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$editId]);
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data Pasien</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1280px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:20px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);margin-bottom:18px}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.full{grid-column:1/-1}
input,select,textarea,button{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px}
button,.btn{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;border:none;font-weight:700;cursor:pointer;padding:12px 16px;border-radius:12px}
.btn.secondary{background:#475569}
.btn.blue{background:#1d4ed8}
.btn.green{background:#166534}
.small{font-size:13px;color:#64748b}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
.actions a{margin-right:6px;margin-bottom:6px;text-decoration:none;padding:8px 10px;border-radius:10px;background:#eff6ff;color:#1d4ed8;display:inline-block;font-size:13px}
.badge{display:inline-block;padding:5px 10px;border-radius:999px;background:#e2e8f0;font-size:12px}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">

    <div class="row" style="margin-bottom:16px">
        <div>
            <h1 style="margin:0">Data Pasien</h1>
            <div class="small">Kelola data pasien klinik gigi.</div>
        </div>
        <div class="row">
            <a class="btn secondary" href="dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>
        <form method="get" class="row">
            <input
                type="text"
                name="q"
                placeholder="Cari No. RM / Nama / Telepon / NIK"
                value="<?= e($keyword) ?>"
                style="flex:1;min-width:260px"
            >
            <button type="submit" style="width:auto">Cari</button>
            <a href="pasien.php" class="btn secondary">Reset</a>
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
                        <option value="">Pilih</option>
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
                    <textarea name="alergi" rows="3"><?= e($editData['alergi'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="row" style="margin-top:16px">
                <button type="submit" style="width:auto">Simpan Pasien</button>
                <?php if ($editData): ?>
                    <a href="pasien.php" class="btn secondary">Batal Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Daftar Pasien</h2>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. RM</th>
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
                        <td>
                            <span class="badge"><?= e($p['no_rm'] ?? '') ?></span>
                        </td>
                        <td>
                            <strong><?= e($p['nama'] ?? '') ?></strong><br>
                            <span class="small"><?= e($p['alamat'] ?? '') ?></span>
                        </td>
                        <td><?= e($p['jk'] ?? '') ?></td>
                        <td><?= e($p['telepon'] ?? '') ?></td>
                        <td><?= e($p['tanggal_lahir'] ?? '') ?></td>
                        <td class="actions">
                            <a href="pasien.php?edit=<?= (int)$p['id'] ?>">Edit</a>
                            <a href="kunjungan.php?pasien_id=<?= (int)$p['id'] ?>">Kunjungan</a>
                            <a href="invoice.php?pasien_id=<?= (int)$p['id'] ?>">Invoice</a>
                            <a href="pasien_history.php?pasien_id=<?= (int)$p['id'] ?>">Riwayat</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$pasienList): ?>
                    <tr>
                        <td colspan="6">Belum ada data pasien.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
