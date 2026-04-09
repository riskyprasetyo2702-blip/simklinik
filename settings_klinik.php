<?php
require_once 'bootstrap.php';
ensure_logged_in();

$uploadDir = __DIR__ . '/uploads/';
$publicDir = 'uploads/';

// pastikan folder ada
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $logoPath = '';
    $qrisPath = '';

    // ==== UPLOAD LOGO ====
    if (!empty($_FILES['logo']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','webp'])) {
            $fileName = 'logo_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $fileName);
            $logoPath = $publicDir . $fileName;
        }
    }

    // ==== UPLOAD QRIS ====
    if (!empty($_FILES['qris']['name'])) {
        $ext = strtolower(pathinfo($_FILES['qris']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png','jpg','jpeg','webp'])) {
            $fileName = 'qris_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['qris']['tmp_name'], $uploadDir . $fileName);
            $qrisPath = $publicDir . $fileName;
        }
    }

    // ambil data lama (kalau ada)
    $old = db_fetch_one("SELECT * FROM settings_klinik");

    db_run("DELETE FROM settings_klinik");

    db_run("INSERT INTO settings_klinik (nama_klinik, logo_path, qris_path)
        VALUES (?,?,?)", [
        $_POST['nama'] ?? '',
        $logoPath ?: ($old['logo_path'] ?? ''),
        $qrisPath ?: ($old['qris_path'] ?? '')
    ]);

    $_SESSION['success'] = "Berhasil disimpan";
    header("Location: setting_klinik.php");
    exit;
}

$data = db_fetch_one("SELECT * FROM settings_klinik");
?>

<h2>Setting Klinik</h2>

<?php flash_message(); ?>

<form method="POST" enctype="multipart/form-data">
    Nama Klinik:<br>
    <input name="nama" value="<?= $data['nama_klinik'] ?? '' ?>"><br><br>

    Logo:<br>
    <input type="file" name="logo"><br>
    <?php if (!empty($data['logo_path'])): ?>
        <br><img src="<?= $data['logo_path'] ?>" style="height:80px;">
    <?php endif; ?>
    <br><br>

    QRIS:<br>
    <input type="file" name="qris"><br>
    <?php if (!empty($data['qris_path'])): ?>
        <br><img src="<?= $data['qris_path'] ?>" style="height:120px;">
    <?php endif; ?>
    <br><br>

    <button>Simpan</button>
</form>
