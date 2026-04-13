<?php
require_once __DIR__ . '/auth_admin.php';

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

/*
|--------------------------------------------------------------------------
| Helper fallback
|--------------------------------------------------------------------------
*/
if (!function_exists('db_fetch_one')) {
    function db_fetch_one(string $sql, array $params = [])
    {
        $rows = db_fetch_all($sql, $params);
        return $rows[0] ?? null;
    }
}

if (!function_exists('flash_message')) {
    function flash_message(): void
    {
        if (!empty($_SESSION['success'])) {
            echo '<div class="alert success">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }

        if (!empty($_SESSION['error'])) {
            echo '<div class="alert error">' . htmlspecialchars($_SESSION['error']) . '</div>';
            unset($_SESSION['error']);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Pastikan tabel tindakan ada
|--------------------------------------------------------------------------
*/
$conn->query("
    CREATE TABLE IF NOT EXISTS tindakan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kode VARCHAR(50) DEFAULT NULL,
        nama_tindakan VARCHAR(150) NOT NULL,
        kategori VARCHAR(100) DEFAULT NULL,
        harga DECIMAL(15,2) NOT NULL DEFAULT 0,
        satuan_harga VARCHAR(50) DEFAULT NULL,
        aktif ENUM('yes','no') NOT NULL DEFAULT 'yes',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/*
|--------------------------------------------------------------------------
| Simpan Tambah / Edit
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = trim($_POST['aksi'] ?? '');

    if ($aksi === 'simpan') {
        $id = (int)($_POST['id'] ?? 0);
        $kode = trim($_POST['kode'] ?? '');
        $nama = trim($_POST['nama_tindakan'] ?? '');
        $kategori = trim($_POST['kategori'] ?? '');
        $harga = (float)($_POST['harga'] ?? 0);
        $satuan = trim($_POST['satuan_harga'] ?? '');
        $aktif = trim($_POST['aktif'] ?? 'yes');

        if ($nama === '') {
            $_SESSION['error'] = 'Nama tindakan wajib diisi.';
            header('Location: tindakan.php');
            exit;
        }

        if ($harga < 0) {
            $_SESSION['error'] = 'Harga tidak boleh minus.';
            header('Location: tindakan.php');
            exit;
        }

        if ($kode !== '') {
            $cekKode = db_fetch_one(
                "SELECT id FROM tindakan WHERE kode = ? AND id != ? LIMIT 1",
                [$kode, $id]
            );

            if ($cekKode) {
                $_SESSION['error'] = 'Kode tindakan sudah dipakai.';
                header('Location: tindakan.php' . ($id > 0 ? '?edit=' . $id : ''));
                exit;
            }
        }

        if ($id > 0) {
            db_run(
                "UPDATE tindakan
                 SET kode = ?, nama_tindakan = ?, kategori = ?, harga = ?, satuan_harga = ?, aktif = ?
                 WHERE id = ?",
                [$kode, $nama, $kategori, $harga, $satuan, $aktif, $id]
            );
            $_SESSION['success'] = 'Tindakan berhasil diupdate.';
        } else {
            db_run(
                "INSERT INTO tindakan (kode, nama_tindakan, kategori, harga, satuan_harga, aktif)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$kode, $nama, $kategori, $harga, $satuan, $aktif]
            );
            $_SESSION['success'] = 'Tindakan berhasil ditambahkan.';
        }

        header('Location: tindakan.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Tambah harga manual
    |--------------------------------------------------------------------------
    */
    if ($aksi === 'tambah_harga') {
        $id = (int)($_POST['id'] ?? 0);
        $nominal = (float)($_POST['nominal'] ?? 0);

        if ($id <= 0 || $nominal <= 0) {
            $_SESSION['error'] = 'Nominal tambah harga tidak valid.';
            header('Location: tindakan.php');
            exit;
        }

        db_run(
            "UPDATE tindakan SET harga = harga + ? WHERE id = ?",
            [$nominal, $id]
        );

        $_SESSION['success'] = 'Harga tindakan berhasil ditambah.';
        header('Location: tindakan.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Kurangi harga manual
    |--------------------------------------------------------------------------
    */
    if ($aksi === 'kurangi_harga') {
        $id = (int)($_POST['id'] ?? 0);
        $nominal = (float)($_POST['nominal'] ?? 0);

        if ($id <= 0 || $nominal <= 0) {
            $_SESSION['error'] = 'Nominal pengurangan harga tidak valid.';
            header('Location: tindakan.php');
            exit;
        }

        $item = db_fetch_one("SELECT harga FROM tindakan WHERE id = ? LIMIT 1", [$id]);

        if (!$item) {
            $_SESSION['error'] = 'Data tindakan tidak ditemukan.';
            header('Location: tindakan.php');
            exit;
        }

        $hargaBaru = max(0, (float)$item['harga'] - $nominal);

        db_run(
            "UPDATE tindakan SET harga = ? WHERE id = ?",
            [$hargaBaru, $id]
        );

        $_SESSION['success'] = 'Harga tindakan berhasil dikurangi.';
        header('Location: tindakan.php');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Toggle aktif/nonaktif
    |--------------------------------------------------------------------------
    */
    if ($aksi === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = trim($_POST['status'] ?? 'yes');

        if ($id > 0 && in_array($status, ['yes', 'no'], true)) {
            db_run("UPDATE tindakan SET aktif = ? WHERE id = ?", [$status, $id]);
            $_SESSION['success'] = 'Status tindakan berhasil diubah.';
        } else {
            $_SESSION['error'] = 'Status tindakan tidak valid.';
        }

        header('Location: tindakan.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Hapus
|--------------------------------------------------------------------------
*/
if (isset($_GET['hapus'])) {
    $id = (int)($_GET['hapus'] ?? 0);

    if ($id > 0) {
        db_run("DELETE FROM tindakan WHERE id = ?", [$id]);
        $_SESSION['success'] = 'Tindakan berhasil dihapus.';
    } else {
        $_SESSION['error'] = 'ID tindakan tidak valid.';
    }

    header('Location: tindakan.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil data edit
|--------------------------------------------------------------------------
*/
$edit = null;
if (isset($_GET['edit'])) {
    $editId = (int)($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $edit = db_fetch_one("SELECT * FROM tindakan WHERE id = ? LIMIT 1", [$editId]);
    }
}

/*
|--------------------------------------------------------------------------
| Filter & Pencarian
|--------------------------------------------------------------------------
*/
$q = trim($_GET['q'] ?? '');
$filterKategori = trim($_GET['kategori'] ?? '');

$sql = "SELECT * FROM tindakan WHERE 1=1";
$params = [];

if ($q !== '') {
    $sql .= " AND (nama_tindakan LIKE ? OR kode LIKE ?)";
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}

if ($filterKategori !== '') {
    $sql .= " AND kategori = ?";
    $params[] = $filterKategori;
}

$sql .= " ORDER BY kategori ASC, nama_tindakan ASC";
$list = db_fetch_all($sql, $params);

$kategoriList = db_fetch_all("
    SELECT DISTINCT kategori
    FROM tindakan
    WHERE kategori IS NOT NULL AND kategori != ''
    ORDER BY kategori ASC
");
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tindakan</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1280px;margin:24px auto;padding:0 16px}
.hero{
    background:linear-gradient(135deg,#0f172a,#1d4ed8);
    color:#fff;
    border-radius:24px;
    padding:24px;
    box-shadow:0 20px 40px rgba(15,23,42,.15);
    margin-bottom:18px;
}
.hero h1{margin:0 0 8px;font-size:32px}
.hero p{margin:0;opacity:.92;line-height:1.6}
.top-actions{margin-top:16px;display:flex;gap:10px;flex-wrap:wrap}
.btn{
    display:inline-block;
    text-decoration:none;
    background:#0f172a;
    color:#fff;
    padding:10px 14px;
    border-radius:10px;
    font-weight:700;
    border:none;
    cursor:pointer;
}
.btn.secondary{background:#475569}
.btn.warning{background:#b45309}
.btn.danger{background:#b91c1c}
.btn.success{background:#166534}
.card{
    background:#fff;
    border-radius:20px;
    padding:20px;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
    margin-bottom:18px;
}
.grid{
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:14px;
}
.grid-2{
    display:grid;
    grid-template-columns:2fr 1fr 1fr;
    gap:14px;
}
input,select{
    width:100%;
    padding:12px;
    border:1px solid #cbd5e1;
    border-radius:12px;
}
label{display:block;margin-bottom:6px;font-weight:700}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:collapse;min-width:1100px}
.table th,.table td{
    padding:12px;
    border-bottom:1px solid #e5e7eb;
    text-align:left;
    vertical-align:top;
}
.badge{
    display:inline-block;
    padding:6px 10px;
    border-radius:999px;
    background:#e2e8f0;
    font-size:12px;
}
.badge.active{background:#dcfce7;color:#166534}
.badge.inactive{background:#fee2e2;color:#991b1b}
.small{font-size:13px;color:#64748b}
.alert{padding:12px 14px;border-radius:12px;margin-bottom:16px;font-weight:700}
.alert.success{background:#dcfce7;color:#166534}
.alert.error{background:#fee2e2;color:#991b1b}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.inline-form{display:inline-flex;gap:8px;align-items:center;flex-wrap:wrap}
.inline-form input[type="number"]{width:120px;padding:8px 10px}
.section-title{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
@media(max-width:900px){
    .grid,.grid-2{grid-template-columns:1fr}
    .hero h1{font-size:26px}
}
</style>
</head>
<body>
<div class="wrap">

    <div class="hero">
        <h1>🦷 Master Tindakan</h1>
        <p>Kelola seluruh daftar tindakan klinik, kategori, harga, status aktif, dan penyesuaian harga manual dari satu halaman admin.</p>
        <div class="top-actions">
            <a class="btn secondary" href="admin_panel.php">Admin Panel</a>
            <a class="btn" href="dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>

        <div class="section-title">
            <div>
                <h2 style="margin:0"><?= $edit ? 'Edit Tindakan' : 'Tambah Tindakan Baru' ?></h2>
                <div class="small">Isi data tindakan dengan lengkap agar billing lebih rapi.</div>
            </div>
        </div>

        <form method="post">
            <input type="hidden" name="aksi" value="simpan">
            <input type="hidden" name="id" value="<?= htmlspecialchars($edit['id'] ?? '') ?>">

            <div class="grid">
                <div>
                    <label>Kode</label>
                    <input type="text" name="kode" value="<?= htmlspecialchars($edit['kode'] ?? '') ?>" placeholder="Contoh: TMB001">
                </div>

                <div>
                    <label>Nama Tindakan</label>
                    <input type="text" name="nama_tindakan" value="<?= htmlspecialchars($edit['nama_tindakan'] ?? '') ?>" placeholder="Contoh: Tambal Gigi" required>
                </div>

                <div>
                    <label>Kategori</label>
                    <input type="text" name="kategori" value="<?= htmlspecialchars($edit['kategori'] ?? '') ?>" placeholder="Contoh: Konservasi">
                </div>

                <div>
                    <label>Harga</label>
                    <input type="number" step="0.01" name="harga" value="<?= htmlspecialchars($edit['harga'] ?? 0) ?>" required>
                </div>

                <div>
                    <label>Satuan Harga</label>
                    <input type="text" name="satuan_harga" value="<?= htmlspecialchars($edit['satuan_harga'] ?? '') ?>" placeholder="Contoh: per gigi / per tindakan">
                </div>

                <div>
                    <label>Status</label>
                    <select name="aktif">
                        <option value="yes" <?= (($edit['aktif'] ?? 'yes') === 'yes') ? 'selected' : '' ?>>Aktif</option>
                        <option value="no" <?= (($edit['aktif'] ?? '') === 'no') ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
            </div>

            <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;">
                <button class="btn" type="submit"><?= $edit ? 'Simpan Perubahan' : 'Tambah Tindakan' ?></button>
                <?php if ($edit): ?>
                    <a class="btn secondary" href="tindakan.php">Batal Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="section-title">
            <div>
                <h2 style="margin:0">Filter & Pencarian</h2>
                <div class="small">Cari tindakan berdasarkan nama/kode atau filter per kategori.</div>
            </div>
        </div>

        <form method="get">
            <div class="grid-2">
                <div>
                    <label>Cari</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama tindakan atau kode">
                </div>

                <div>
                    <label>Kategori</label>
                    <select name="kategori">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategoriList as $k): ?>
                            <option value="<?= htmlspecialchars($k['kategori']) ?>" <?= $filterKategori === ($k['kategori'] ?? '') ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k['kategori']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="align-self:end;">
                    <button class="btn" type="submit">Terapkan Filter</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="section-title">
            <div>
                <h2 style="margin:0">Daftar Tindakan</h2>
                <div class="small">Total data: <?= count($list) ?></div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                        <th>Kategori</th>
                        <th>Harga</th>
                        <th>Satuan</th>
                        <th>Status</th>
                        <th>Tambah/Kurangi Manual</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['kode'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['nama_tindakan'] ?? '-') ?></td>
                        <td><span class="badge"><?= htmlspecialchars($row['kategori'] ?? '-') ?></span></td>
                        <td><?= rupiah($row['harga'] ?? 0) ?></td>
                        <td><?= htmlspecialchars($row['satuan_harga'] ?? '-') ?></td>
                        <td>
                            <?php if (($row['aktif'] ?? 'yes') === 'yes'): ?>
                                <span class="badge active">Aktif</span>
                            <?php else: ?>
                                <span class="badge inactive">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;flex-direction:column;gap:8px;">
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="aksi" value="tambah_harga">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <input type="number" step="0.01" min="1" name="nominal" placeholder="Nominal">
                                    <button class="btn success" type="submit">+ Tambah</button>
                                </form>

                                <form method="post" class="inline-form">
                                    <input type="hidden" name="aksi" value="kurangi_harga">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <input type="number" step="0.01" min="1" name="nominal" placeholder="Nominal">
                                    <button class="btn warning" type="submit">- Kurangi</button>
                                </form>
                            </div>
                        </td>
                        <td>
                            <div class="actions">
                                <a class="btn secondary" href="?edit=<?= (int)$row['id'] ?>">Edit</a>
                                <a class="btn danger" href="?hapus=<?= (int)$row['id'] ?>" onclick="return confirm('Hapus tindakan ini?')">Hapus</a>

                                <form method="post" class="inline-form">
                                    <input type="hidden" name="aksi" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                                    <input type="hidden" name="status" value="<?= (($row['aktif'] ?? 'yes') === 'yes') ? 'no' : 'yes' ?>">
                                    <button class="btn" type="submit">
                                        <?= (($row['aktif'] ?? 'yes') === 'yes') ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>

                    <?php if (!$list): ?>
                    <tr>
                        <td colspan="8">Belum ada data tindakan.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
