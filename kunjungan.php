<?php
require_once __DIR__ . '/bootstrap.php';

$pasienId = (int)($_GET['pasien_id'] ?? 0);
$editId = (int)($_GET['edit'] ?? 0);

$pasien = null;
if ($pasienId > 0) {
    $pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasienId]);
}

$editData = null;
if ($editId > 0) {
    $editData = db_fetch_one("SELECT * FROM kunjungan WHERE id = ?", [$editId]);
    if ($editData && !$pasienId) {
        $pasienId = (int)$editData['pasien_id'];
        $pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasienId]);
    }
}

$pasienList = db_fetch_all("SELECT id, no_rm, nama FROM pasien ORDER BY nama ASC");
$kunjunganList = $pasienId > 0
    ? db_fetch_all("SELECT k.*, p.no_rm, p.nama FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id WHERE k.pasien_id = ? ORDER BY k.tanggal DESC", [$pasienId])
    : db_fetch_all("SELECT k.*, p.no_rm, p.nama FROM kunjungan k JOIN pasien p ON p.id = k.pasien_id ORDER BY k.tanggal DESC LIMIT 100");
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kunjungan</title>
<style>
body{font-family:Arial,sans-serif;background:#f6f8fb;margin:0;color:#1f2937}.wrap{max-width:1200px;margin:24px auto;padding:0 16px}.card{background:#fff;border-radius:18px;padding:20px;box-shadow:0 8px 24px rgba(0,0,0,.06);margin-bottom:18px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}input,select,textarea,button{width:100%;padding:11px 12px;border:1px solid #d1d5db;border-radius:12px;box-sizing:border-box}button,.btn{background:#111827;color:#fff;border:none;text-decoration:none;display:inline-block;text-align:center;cursor:pointer}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}.small{font-size:12px;color:#6b7280}.actions a{margin-right:6px;text-decoration:none;padding:7px 10px;border-radius:10px;background:#eef2ff;color:#1e3a8a;display:inline-block}.row{display:flex;gap:10px;flex-wrap:wrap}@media(max-width:768px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
    <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:16px">
        <div>
            <h1 style="margin:0">Kunjungan Pasien</h1>
            <div class="small"><?= $pasien ? 'Pasien: ' . e($pasien['no_rm']) . ' - ' . e($pasien['nama']) : 'Semua kunjungan' ?></div>
        </div>
        <div class="row">
            <a class="btn" style="padding:11px 16px" href="pasien.php">Data Pasien</a>
            <a class="btn" style="padding:11px 16px;background:#4b5563" href="dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>
        <h2 style="margin-top:0"><?= $editData ? 'Edit Kunjungan' : 'Tambah Kunjungan' ?></h2>
        <form method="post" action="simpan_kunjungan.php">
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">
            <div class="grid">
                <div>
                    <label>Pasien</label>
                    <select name="pasien_id" required>
                        <option value="">Pilih pasien</option>
                        <?php foreach ($pasienList as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= ((int)($editData['pasien_id'] ?? $pasienId) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['no_rm']) ?> - <?= e($p['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Tanggal Kunjungan</label>
                    <input type="datetime-local" name="tanggal" required value="<?= e(isset($editData['tanggal']) ? date('Y-m-d\TH:i', strtotime($editData['tanggal'])) : date('Y-m-d\TH:i')) ?>">
                </div>
                <div class="full">
                    <label>Keluhan Utama</label>
                    <textarea name="keluhan" rows="2"><?= e($editData['keluhan'] ?? '') ?></textarea>
                </div>
                <div>
                    <label>Diagnosa</label>
                    <input type="text" name="diagnosa" value="<?= e($editData['diagnosa'] ?? '') ?>">
                </div>
                <div>
                    <label>Dokter</label>
                    <input type="text" name="dokter" value="<?= e($editData['dokter'] ?? '') ?>">
                </div>
                <div class="full">
                    <label>Tindakan</label>
                    <textarea name="tindakan" rows="3"><?= e($editData['tindakan'] ?? '') ?></textarea>
                </div>
                <div class="full">
                    <label>Odontogram / Ringkasan Gigi</label>
                    <textarea name="odontogram" rows="3"><?= e($editData['odontogram'] ?? '') ?></textarea>
                </div>
                <div class="full">
                    <label>Catatan</label>
                    <textarea name="catatan" rows="3"><?= e($editData['catatan'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="row" style="margin-top:14px">
                <button type="submit" style="width:auto;padding:11px 16px">Simpan Kunjungan</button>
                <?php if ($editData): ?><a href="kunjungan.php?pasien_id=<?= (int)$pasienId ?>" class="btn" style="padding:11px 16px;width:auto;background:#6b7280">Batal</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Riwayat Kunjungan</h2>
        <div style="overflow:auto">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Pasien</th><th>Keluhan</th><th>Diagnosa</th><th>Tindakan</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($kunjunganList as $k): ?>
                    <tr>
                        <td><?= e($k['tanggal']) ?></td>
                        <td><strong><?= e($k['no_rm']) ?></strong><br><?= e($k['nama']) ?></td>
                        <td><?= e($k['keluhan']) ?></td>
                        <td><?= e($k['diagnosa']) ?></td>
                        <td><?= e($k['tindakan']) ?></td>
                        <td class="actions">
                            <a href="kunjungan.php?edit=<?= (int)$k['id'] ?>">Edit</a>
                            <a href="invoice.php?pasien_id=<?= (int)$k['pasien_id'] ?>&kunjungan_id=<?= (int)$k['id'] ?>">Buat Invoice</a>
                            <a href="resume_medis.php?kunjungan_id=<?= (int)$k['id'] ?>">Resume</a>
                            <a href="surat_sakit.php?kunjungan_id=<?= (int)$k['id'] ?>">Surat Sakit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$kunjunganList): ?><tr><td colspan="6">Belum ada kunjungan.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
