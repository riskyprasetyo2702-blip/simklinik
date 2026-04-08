<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

if (!table_exists($conn, 'settings_klinik')) {
    die('Tabel settings_klinik belum ada. Jalankan SQL tabelnya dulu.');
}

$data = db_fetch_one("SELECT * FROM settings_klinik ORDER BY id ASC LIMIT 1");
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pengaturan Klinik</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1000px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:20px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);margin-bottom:18px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.full{grid-column:1/-1}
input,textarea,button{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px}
button,.btn{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;border:none;font-weight:700;cursor:pointer;padding:12px 16px;border-radius:12px}
.btn.secondary{background:#475569}
.preview-box{border:1px dashed #cbd5e1;border-radius:16px;padding:16px;text-align:center;background:#fafafa}
.preview-box img{max-width:220px;max-height:220px;height:auto}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.small{font-size:13px;color:#64748b}
@media(max-width:768px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">

    <div class="row" style="margin-bottom:16px">
        <div>
            <h1 style="margin:0">Pengaturan Klinik</h1>
            <div class="small">Upload logo klinik dan QRIS untuk invoice serta dokumen.</div>
        </div>
        <div class="row">
            <a class="btn secondary" href="dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>

        <form method="post" action="simpan_settings_klinik.php" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= (int)($data['id'] ?? 0) ?>">

            <div class="grid">
                <div class="full">
                    <label>Nama Klinik</label>
                    <input type="text" name="nama_klinik" value="<?= e($data['nama_klinik'] ?? 'Klinik Praktek Mandiri Dokter Gigi Andreas Aryo Risky Prasetyo') ?>">
                </div>

                <div>
                    <label>Telepon Klinik</label>
                    <input type="text" name="telepon_klinik" value="<?= e($data['telepon_klinik'] ?? '') ?>">
                </div>

                <div>
                    <label>Email Klinik</label>
                    <input type="text" name="email_klinik" value="<?= e($data['email_klinik'] ?? '') ?>">
                </div>

                <div class="full">
                    <label>Alamat Klinik</label>
                    <textarea name="alamat_klinik" rows="3"><?= e($data['alamat_klinik'] ?? '') ?></textarea>
                </div>

                <div>
                    <label>Upload Logo Klinik</label>
                    <input type="file" name="logo_klinik" accept=".jpg,.jpeg,.png,.webp">
                    <div class="small" style="margin-top:8px">Format: JPG, PNG, WEBP</div>
                </div>

                <div>
                    <label>Upload Gambar QRIS</label>
                    <input type="file" name="qris_image" accept=".jpg,.jpeg,.png,.webp">
                    <div class="small" style="margin-top:8px">Format: JPG, PNG, WEBP</div>
                </div>

                <div class="full">
                    <label>Payload / Keterangan QRIS</label>
                    <textarea name="qris_payload" rows="3"><?= e($data['qris_payload'] ?? '') ?></textarea>
                </div>

                <div>
                    <div class="preview-box">
                        <strong>Preview Logo</strong><br><br>
                        <?php if (!empty($data['logo_path'])): ?>
                            <img src="<?= e($data['logo_path']) ?>" alt="Logo Klinik">
                        <?php else: ?>
                            <div class="small">Belum ada logo</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <div class="preview-box">
                        <strong>Preview QRIS</strong><br><br>
                        <?php if (!empty($data['qris_path'])): ?>
                            <img src="<?= e($data['qris_path']) ?>" alt="QRIS">
                        <?php else: ?>
                            <div class="small">Belum ada QRIS</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div style="margin-top:18px">
                <button type="submit">Simpan Pengaturan Klinik</button>
            </div>
        </form>
    </div>

</div>
</body>
</html>