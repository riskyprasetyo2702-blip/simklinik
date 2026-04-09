<?php
require_once 'bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database gagal.');
}

if (!table_exists($conn, 'settings_klinik')) {
    die('Tabel settings_klinik belum ada.');
}

$uploadDir = __DIR__ . '/uploads/';
$publicDir = 'uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama = trim($_POST['nama'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $old = db_fetch_one("SELECT * FROM settings_klinik LIMIT 1");

    $logoPath = $old['logo_path'] ?? '';
    $qrisPath = $old['qris_path'] ?? '';

    if (!empty($_FILES['logo']['name']) && (int)$_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
            $fileName = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $fileName)) {
                $logoPath = $publicDir . $fileName;
            }
        }
    }

    if (!empty($_FILES['qris']['name']) && (int)$_FILES['qris']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['qris']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
            $fileName = 'qris_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['qris']['tmp_name'], $uploadDir . $fileName)) {
                $qrisPath = $publicDir . $fileName;
            }
        }
    }

    db_run("DELETE FROM settings_klinik");

    db_run(
        "INSERT INTO settings_klinik (nama_klinik, alamat_klinik, telepon_klinik, email_klinik, logo_path, qris_path)
         VALUES (?, ?, ?, ?, ?, ?)",
        [$nama, $alamat, $telepon, $email, $logoPath, $qrisPath]
    );

    $_SESSION['success'] = 'Settings klinik berhasil disimpan.';
    header('Location: settings_klinik.php');
    exit;
}

$data = db_fetch_one("SELECT * FROM settings_klinik LIMIT 1");
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings Klinik</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;padding:24px;color:#0f172a}
.card{max-width:900px;margin:auto;background:#fff;padding:24px;border-radius:18px;box-shadow:0 12px 28px rgba(15,23,42,.08)}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.full{grid-column:1/-1}
input,textarea,button{width:100%;padding:12px;border:1px solid #cbd5e1;border-radius:12px}
button{background:#0f172a;color:#fff;border:none;cursor:pointer;font-weight:700}
.preview{margin-top:10px}
.preview img{max-height:120px;border:1px solid #e2e8f0;border-radius:10px;padding:8px;background:#fff}
.top{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px}
.btn{display:inline-block;text-decoration:none;background:#475569;color:#fff;padding:10px 14px;border-radius:10px}
@media(max-width:768px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="card">

    <div class="top">
        <div>
            <h2 style="margin:0">Settings Klinik</h2>
            <div style="font-size:13px;color:#64748b;">Upload logo dan QRIS klinik</div>
        </div>
        <a class="btn" href="dashboard.php">Kembali ke Dashboard</a>
    </div>

    <?php flash_message(); ?>

    <form method="post" enctype="multipart/form-data">
        <div class="grid">
            <div class="full">
                <label>Nama Klinik</label>
                <input type="text" name="nama" value="<?= e($data['nama_klinik'] ?? '') ?>">
            </div>

            <div class="full">
                <label>Alamat Klinik</label>
                <textarea name="alamat" rows="3"><?= e($data['alamat_klinik'] ?? '') ?></textarea>
            </div>

            <div>
                <label>Telepon Klinik</label>
                <input type="text" name="telepon" value="<?= e($data['telepon_klinik'] ?? '') ?>">
            </div>

            <div>
                <label>Email Klinik</label>
                <input type="text" name="email" value="<?= e($data['email_klinik'] ?? '') ?>">
            </div>

            <div>
                <label>Upload Logo</label>
                <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp">
                <?php if (!empty($data['logo_path'])): ?>
                    <div class="preview">
                        <img src="<?= e($data['logo_path']) ?>" alt="Logo Klinik">
                    </div>
                <?php endif; ?>
            </div>

            <div>
                <label>Upload QRIS</label>
                <input type="file" name="qris" accept=".png,.jpg,.jpeg,.webp">
                <?php if (!empty($data['qris_path'])): ?>
                    <div class="preview">
                        <img src="<?= e($data['qris_path']) ?>" alt="QRIS">
                    </div>
                <?php endif; ?>
            </div>

            <div class="full">
                <button type="submit">Simpan Settings Klinik</button>
            </div>
        </div>
    </form>

</div>
</body>
</html>
