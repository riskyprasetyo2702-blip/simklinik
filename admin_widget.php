<?php
require_once __DIR__ . '/auth_admin.php';

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

/**
 * Pastikan tabel widget_tindakan ada.
 */
$conn->query("
    CREATE TABLE IF NOT EXISTS widget_tindakan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(150) NOT NULL,
        kode VARCHAR(100) NOT NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = trim($_POST['aksi'] ?? '');

    if ($aksi === 'tambah') {
        $nama = trim($_POST['nama'] ?? '');
        $kode = trim($_POST['kode'] ?? '');

        if ($nama === '' || $kode === '') {
            $_SESSION['error'] = 'Nama dan kode wajib diisi.';
            header('Location: admin_widget.php');
            exit;
        }

        $cek = db_fetch_one("SELECT id FROM widget_tindakan WHERE kode = ? LIMIT 1", [$kode]);
        if ($cek) {
            $_SESSION['error'] = 'Kode widget sudah dipakai. Gunakan kode lain.';
            header('Location: admin_widget.php');
            exit;
        }

        db_run(
            "INSERT INTO widget_tindakan (nama, kode, aktif) VALUES (?, ?, 1)",
            [$nama, $kode]
        );

        $_SESSION['success'] = 'Widget tindakan berhasil ditambahkan.';
        header('Location: admin_widget.php');
        exit;
    }

    if ($aksi === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $aktif = (int)($_POST['aktif'] ?? 0);

        if ($id > 0) {
            db_run(
                "UPDATE widget_tindakan SET aktif = ? WHERE id = ?",
                [$aktif, $id]
            );
            $_SESSION['success'] = 'Status widget berhasil diperbarui.';
        } else {
            $_SESSION['error'] = 'ID widget tidak valid.';
        }

        header('Location: admin_widget.php');
        exit;
    }

    if ($aksi === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $nama = trim($_POST['nama'] ?? '');
        $kode = trim($_POST['kode'] ?? '');

        if ($id <= 0 || $nama === '' || $kode === '') {
            $_SESSION['error'] = 'Data edit widget tidak valid.';
            header('Location: admin_widget.php');
            exit;
        }

        $cek = db_fetch_one(
            "SELECT id FROM widget_tindakan WHERE kode = ? AND id != ? LIMIT 1",
            [$kode, $id]
        );
        if ($cek) {
            $_SESSION['error'] = 'Kode widget sudah dipakai widget lain.';
            header('Location: admin_widget.php');
            exit;
        }

        db_run(
            "UPDATE widget_tindakan SET nama = ?, kode = ? WHERE id = ?",
            [$nama, $kode, $id]
        );

        $_SESSION['success'] = 'Widget berhasil diubah.';
        header('Location: admin_widget.php');
        exit;
    }
}

if (isset($_GET['hapus'])) {
    $id = (int)($_GET['hapus'] ?? 0);

    if ($id > 0) {
        db_run("DELETE FROM widget_tindakan WHERE id = ?", [$id]);
        $_SESSION['success'] = 'Widget tindakan berhasil dihapus.';
    } else {
        $_SESSION['error'] = 'ID widget tidak valid.';
    }

    header('Location: admin_widget.php');
    exit;
}

$editId = (int)($_GET['edit'] ?? 0);
$editData = null;

if ($editId > 0) {
    $editData = db_fetch_one("SELECT * FROM widget_tindakan WHERE id = ? LIMIT 1", [$editId]);
}

$data = db_fetch_all("SELECT * FROM widget_tindakan ORDER BY id DESC");

/**
 * Fallback kalau helper di bootstrap belum ada.
 */
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

if (!function_exists('db_fetch_one')) {
    function db_fetch_one(string $sql, array $params = [])
    {
        $rows = db_fetch_all($sql, $params);
        return $rows[0] ?? null;
    }
}
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
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:18px}
.hero{background:linear-gradient(135deg,#0f172a,#1d4ed8);color:#fff;border-radius:24px;padding:24px;box-shadow:0 20px 40px rgba(15,23,42,.15)}
.hero h1{margin:0 0 8px;font-size:32px}
.hero p{margin:0;opacity:.92;line-height:1.6}
.top-actions{margin-top:18px;display:flex;gap:10px;flex-wrap:wrap}
.btn{display:inline-block;text-decoration:none;background:#0f172a;color:#fff;padding:10px 14px;border-radius:10px;font-weight:700;border:none;cursor:pointer}
.btn.secondary{background:#475569}
.btn.warning{background:#b45309}
.btn.danger{background:#b91c1c}
.card{background:#fff;border-radius:22px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);margin-top:18px}
.grid{display:grid;grid-template-columns:1fr 1fr auto;gap:14px}
input,button,select{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:12px}
button{background:#0f172a;color:#fff;border:none;cursor:pointer;font-weight:700}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:collapse;min-width:760px}
.table th,.table td{padding:12px;border-bottom:1px solid #e5e7eb;text-align:left;vertical-align:top}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#e2e8f0;font-size:12px}
.badge.active{background:#dcfce7;color:#166534}
.badge.inactive{background:#fee2e2;color:#991b1b}
.small{font-size:13px;color:#64748b}
.alert{padding:12px 14px;border-radius:12px;margin-bottom:16px;font-weight:700}
.alert.success{background:#dcfce7;color:#166534}
.alert.error{background:#fee2e2;color:#991b1b}
.actions{display:flex;gap:8px;flex-wrap:wrap}
.inline-form{display:inline-block;margin:0}
@media(max-width:800px){
    .grid{grid-template-columns:1fr}
    .hero h1{font-size:26px}
}
</style>
</head>
<body>
<div class="wrap">

    <div class="hero">
        <h1>🧩 Widget Tindakan</h1>
        <p>Kelola tombol tindakan cepat yang dipakai di sistem. Kamu bisa tambah, edit, aktifkan, nonaktifkan, dan hapus widget dari sini.</p>
        <div class="top-actions">
            <a class="btn secondary" href="admin_panel.php">Admin Panel</a>
            <a class="btn" href="dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>

        <h2 style="margin-top:0"><?= $editData ? 'Edit Widget' : 'Tambah Widget' ?></h2>

        <form method="post">
            <input type="hidden" name="aksi" value="<?= $editData ? 'edit' : 'tambah' ?>">
            <?php if ($editData): ?>
                <input type="hidden" name="id" value="<?= (int)$editData['id'] ?>">
            <?php endif; ?>

            <div class="grid">
                <div>
                    <label>Nama Widget</label>
                    <input
                        type="text"
                        name="nama"
                        placeholder="Contoh: Tambal Gigi"
                        value="<?= htmlspecialchars($editData['nama'] ?? '') ?>"
                        required
                    >
                </div>

                <div>
                    <label>Kode</label>
                    <input
                        type="text"
                        name="kode"
                        placeholder="Contoh: tambal"
                        value="<?= htmlspecialchars($editData['kode'] ?? '') ?>"
                        required
                    >
                </div>

                <div style="align-self:end;">
                    <button type="submit"><?= $editData ? 'Simpan Perubahan' : 'Tambah Widget' ?></button>
                </div>
            </div>

            <?php if ($editData): ?>
                <div style="margin-top:12px;">
                    <a class="btn secondary" href="admin_widget.php">Batal Edit</a>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="top">
            <div>
                <h2 style="margin:0">Daftar Widget</h2>
                <div class="small">Total widget: <?= count($data) ?></div>
            </div>
        </div>

        <div class="table-wrap">
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
                        <td><?= htmlspecialchars($w['nama'] ?? '-') ?></td>
                        <td><span class="badge"><?= htmlspecialchars($w['kode'] ?? '-') ?></span></td>
                        <td>
                            <?php if ((int)($w['aktif'] ?? 0) === 1): ?>
                                <span class="badge active">Aktif</span>
                            <?php else: ?>
                                <span class="badge inactive">Nonaktif</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="actions">
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="aksi" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                                    <input type="hidden" name="aktif" value="<?= ((int)($w['aktif'] ?? 0) === 1) ? 0 : 1 ?>">
                                    <button type="submit" class="btn warning">
                                        <?= ((int)($w['aktif'] ?? 0) === 1) ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>

                                <a class="btn secondary" href="?edit=<?= (int)$w['id'] ?>">Edit</a>
                                <a class="btn danger" href="?hapus=<?= (int)$w['id'] ?>" onclick="return confirm('Hapus widget ini?')">Hapus</a>
                            </div>
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

</div>
</body>
</html>
