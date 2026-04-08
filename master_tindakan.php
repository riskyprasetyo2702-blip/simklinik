<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

/*
|--------------------------------------------------------------------------
| Simpan data (Tambah / Edit)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = (int)($_POST['id'] ?? 0);
    $kode = trim($_POST['kode'] ?? '');
    $nama = trim($_POST['nama_tindakan'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $harga = (float)($_POST['harga'] ?? 0);
    $satuan = trim($_POST['satuan_harga'] ?? '');
    $aktif = trim($_POST['aktif'] ?? 'yes');

    if ($nama === '') {
        $_SESSION['error'] = 'Nama tindakan wajib diisi';
        header("Location: master_tindakan.php");
        exit;
    }

    if ($id > 0) {
        db_run("
            UPDATE tindakan SET
                kode=?,
                nama_tindakan=?,
                kategori=?,
                harga=?,
                satuan_harga=?,
                aktif=?
            WHERE id=?
        ", [$kode,$nama,$kategori,$harga,$satuan,$aktif,$id]);

        $_SESSION['success'] = 'Tindakan berhasil diupdate';
    } else {
        db_insert("
            INSERT INTO tindakan
            (kode,nama_tindakan,kategori,harga,satuan_harga,aktif)
            VALUES (?,?,?,?,?,?)
        ", [$kode,$nama,$kategori,$harga,$satuan,$aktif]);

        $_SESSION['success'] = 'Tindakan berhasil ditambahkan';
    }

    header("Location: master_tindakan.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Hapus
|--------------------------------------------------------------------------
*/
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    db_run("DELETE FROM tindakan WHERE id=?", [$id]);

    $_SESSION['success'] = 'Tindakan dihapus';
    header("Location: master_tindakan.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| Ambil data
|--------------------------------------------------------------------------
*/
$list = [];
if (table_exists($conn,'tindakan')) {
    $list = db_fetch_all("SELECT * FROM tindakan ORDER BY kategori,nama_tindakan ASC");
}

/*
|--------------------------------------------------------------------------
| Edit data
|--------------------------------------------------------------------------
*/
$edit = null;
if (isset($_GET['edit'])) {
    $edit = db_fetch_one("SELECT * FROM tindakan WHERE id=?",[(int)$_GET['edit']]);
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Master Tindakan</title>
<style>
body{font-family:Arial;background:#f5f7fb;margin:0}
.wrap{max-width:1200px;margin:20px auto;padding:15px}
.card{background:#fff;border-radius:14px;padding:18px;margin-bottom:15px}
input,select{width:100%;padding:10px;border:1px solid #ccc;border-radius:8px}
button{background:#0f172a;color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid #ddd}
.badge{background:#eee;padding:5px 8px;border-radius:8px;font-size:12px}
.row{display:flex;gap:10px;flex-wrap:wrap}
.col{flex:1;min-width:200px}
a.btn{padding:6px 10px;background:#e0e7ff;border-radius:6px;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">

<h2>Master Tindakan</h2>

<div class="card">
<form method="post">
<input type="hidden" name="id" value="<?= e($edit['id'] ?? '') ?>">

<div class="row">
<div class="col">
<label>Kode</label>
<input type="text" name="kode" value="<?= e($edit['kode'] ?? '') ?>">
</div>

<div class="col">
<label>Nama Tindakan</label>
<input type="text" name="nama_tindakan" value="<?= e($edit['nama_tindakan'] ?? '') ?>">
</div>

<div class="col">
<label>Kategori</label>
<input type="text" name="kategori" value="<?= e($edit['kategori'] ?? '') ?>">
</div>

<div class="col">
<label>Harga</label>
<input type="number" name="harga" value="<?= e($edit['harga'] ?? 0) ?>">
</div>

<div class="col">
<label>Satuan</label>
<input type="text" name="satuan_harga" value="<?= e($edit['satuan_harga'] ?? '') ?>">
</div>

<div class="col">
<label>Status</label>
<select name="aktif">
<option value="yes" <?= (isset($edit['aktif']) && $edit['aktif']=='yes')?'selected':'' ?>>Aktif</option>
<option value="no" <?= (isset($edit['aktif']) && $edit['aktif']=='no')?'selected':'' ?>>Nonaktif</option>
</select>
</div>
</div>

<br>
<button type="submit">Simpan Tindakan</button>
</form>
</div>

<div class="card">
<table class="table">
<thead>
<tr>
<th>Kode</th>
<th>Nama</th>
<th>Kategori</th>
<th>Harga</th>
<th>Satuan</th>
<th>Status</th>
<th>Aksi</th>
</tr>
</thead>
<tbody>
<?php foreach($list as $row): ?>
<tr>
<td><?= e($row['kode']) ?></td>
<td><?= e($row['nama_tindakan']) ?></td>
<td><span class="badge"><?= e($row['kategori']) ?></span></td>
<td><?= rupiah($row['harga']) ?></td>
<td><?= e($row['satuan_harga']) ?></td>
<td><?= e($row['aktif']) ?></td>
<td>
<a class="btn" href="?edit=<?= $row['id'] ?>">Edit</a>
<a class="btn" href="?hapus=<?= $row['id'] ?>" onclick="return confirm('Hapus?')">Hapus</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

</div>
</body>
</html>