<?php
require_once 'bootstrap.php';
ensure_logged_in();

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    die('Akses ditolak');
}

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'tambah') {
        $nama = trim($_POST['nama'] ?? '');
        $kode = trim($_POST['kode'] ?? '');

        if ($nama !== '' && $kode !== '') {
            db_run("INSERT INTO widget_tindakan (nama, kode, aktif) VALUES (?, ?, 1)", [$nama, $kode]);
            $_SESSION['success'] = 'Widget tindakan berhasil ditambahkan.';
        } else {
            $_SESSION['error'] = 'Nama dan kode wajib diisi.';
        }

        header('Location: admin_widget.php');
        exit;
    }

    if ($aksi === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $aktif = (int)($_POST['aktif'] ?? 0);

        db_run("UPDATE widget_tindakan SET aktif = ? WHERE id = ?", [$aktif, $id]);
        $_SESSION['success'] = 'Status widget berhasil diupdate.';
        header('Location: admin_widget.php');
        exit;
    }
}

if (isset($_GET['hapus'])) {
    $id = (int)($_GET['hapus'] ?? 0);
    db_run("DELETE FROM widget_tindakan WHERE id = ?", [$id]);
    $_SESSION['success'] = 'Widget tindakan berhasil dihapus.';
    header('Location: admin_widget.php');
    exit;
}

$data = db_fetch_all("SELECT * FROM widget_tindakan ORDER BY id DESC");
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin - Widget Tindakan</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1200px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:22px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);margin-bottom:18px}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.btn{display:inline-block;text-decoration:none;background:#0f172a;color:#fff;padding:10px 14px;border-radius:10px;font-weight:700}
.btn.secondary{background:#475569}
.grid{display:grid;grid-template-columns:1fr 1fr auto;gap:14px}
input,button,select{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:12px}
button{background:#0f172a;color:#fff;border:none;cursor:pointer;font-weight:700}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#e2e8f0;font-size:12px}
.small{font-size:13px;color:#64748b}
@media(max-width:800px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">

    <div class="top">
        <div>
            <h1 style="margin:0">Widget Tindakan</h1>
            <div class="small">Atur tombol tindakan cepat yang tampil di sistem</div>
        </div>
        <div>
            <a class="btn secondary" href="admin_panel.php">Admin Panel</a>
            <a class="btn" href="dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>

        <h2 style="margin-top:0">Tambah Widget</h2>
        <form method="post">
            <input type="hidden" name="aksi" value="tambah">

            <div class="grid">
                <div>
                    <label>Nama Widget</label>
                    <input type="text" name="nama" placeholder="Contoh: Tambal Gigi">
                </div>
                <div>
                    <label>Kode</label>
                    <input type="text" name="kode" placeholder="Contoh: tambal">
                </div>
                <div style="align-self:end;">
                    <button type="submit">Tambah Widget</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Daftar Widget</h2>

        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nama</th>
                    <th>Kode</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $w): ?>
                <tr>
                    <td><?= (int)$w['id'] ?></td>
                    <td><?= e($w['nama'] ?? '-') ?></td>
                    <td><span class="badge"><?= e($w['kode'] ?? '-') ?></span></td>
                    <td><?= ((int)($w['aktif'] ?? 0) === 1) ? 'Aktif' : 'Nonaktif' ?></td>
                    <td>
                        <form method="post" style="display:inline-block;">
                            <input type="hidden" name="aksi" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                            <input type="hidden" name="aktif" value="<?= ((int)($w['aktif'] ?? 0) === 1) ? 0 : 1 ?>">
                            <button type="submit"><?= ((int)($w['aktif'] ?? 0) === 1) ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                        </form>

                        <a class="btn secondary" href="?hapus=<?= (int)$w['id'] ?>" onclick="return confirm('Hapus widget ini?')">Hapus</a>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (!$data): ?>
                <tr>
                    <td colspan="5">Belum ada widget tindakan.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>
