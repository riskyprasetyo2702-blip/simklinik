<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

if (!table_exists($conn, 'kunjungan')) {
    die('Tabel kunjungan tidak ditemukan di database.');
}

$pasienId = (int)($_GET['pasien_id'] ?? 0);
$editId   = (int)($_GET['edit'] ?? 0);

$pasien = null;
if ($pasienId > 0 && table_exists($conn, 'pasien')) {
    $pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasienId]);
}

$editData = null;
if ($editId > 0) {
    $editData = db_fetch_one("SELECT * FROM kunjungan WHERE id = ?", [$editId]);
    if ($editData && !$pasienId) {
        $pasienId = (int)($editData['pasien_id'] ?? 0);
        if ($pasienId > 0 && table_exists($conn, 'pasien')) {
            $pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasienId]);
        }
    }
}

$pasienList = pasien_options();
$icd10List = icd10_options();
$tindakanList = tindakan_options();

if ($pasienId > 0) {
    $kunjunganList = db_fetch_all("
        SELECT k.*, p.no_rm, p.nama
        FROM kunjungan k
        JOIN pasien p ON p.id = k.pasien_id
        WHERE k.pasien_id = ?
        ORDER BY k.tanggal DESC, k.id DESC
    ", [$pasienId]);
} else {
    $kunjunganList = db_fetch_all("
        SELECT k.*, p.no_rm, p.nama
        FROM kunjungan k
        JOIN pasien p ON p.id = k.pasien_id
        ORDER BY k.tanggal DESC, k.id DESC
        LIMIT 100
    ");
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Kunjungan Pasien</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1280px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:20px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);margin-bottom:18px}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.grid3{display:grid;grid-template-columns:1.2fr .9fr 1fr;gap:16px}
.full{grid-column:1/-1}
input,select,textarea,button{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px}
button,.btn{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;border:none;font-weight:700;cursor:pointer;padding:12px 16px;border-radius:12px}
.btn.secondary{background:#475569}
.small{font-size:13px;color:#64748b}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
.actions a{margin-right:6px;margin-bottom:6px;text-decoration:none;padding:8px 10px;border-radius:10px;background:#eff6ff;color:#1d4ed8;display:inline-block;font-size:13px}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#e2e8f0;font-size:12px}
@media(max-width:900px){.grid,.grid3{grid-template-columns:1fr}}
</style>
<script>
function isiIcd(selectEl){
    const opt = selectEl.options[selectEl.selectedIndex];
    document.getElementById('icd10_code').value = opt.value || '';
    if (opt.dataset.nama) {
        document.getElementById('diagnosa').value = opt.dataset.nama;
    }
}

function tambahTindakanDariMaster(sel){
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;

    const nama = opt.getAttribute('data-nama') || '';
    const area = document.getElementById('tindakan');
    const current = area.value.trim();

    if (current === '') {
        area.value = nama;
    } else {
        const lines = current.split('\\n').map(v => v.trim()).filter(Boolean);
        if (!lines.includes(nama)) {
            area.value = current + "\\n" + nama;
        }
    }

    sel.selectedIndex = 0;
}
</script>
</head>
<body>
<div class="wrap">

    <div class="row" style="margin-bottom:16px">
        <div>
            <h1 style="margin:0">Kunjungan Pasien</h1>
            <div class="small">
                <?= $pasien ? 'Pasien: ' . e($pasien['no_rm'] ?? '') . ' - ' . e($pasien['nama'] ?? '') : 'Semua riwayat kunjungan' ?>
            </div>
        </div>
        <div class="row">
            <a class="btn secondary" href="pasien.php">Data Pasien</a>
            <a class="btn" href="dashboard.php">Dashboard</a>
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
                            <option value="<?= (int)$p['id'] ?>" <?= ((int)($editData['pasien_id'] ?? $pasienId) === (int)$p['id']) ? 'selected' : '' ?>>
                                <?= e($p['no_rm'] ?? '') ?> - <?= e($p['nama'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Tanggal Kunjungan</label>
                    <input
                        type="datetime-local"
                        name="tanggal"
                        required
                        value="<?= e(isset($editData['tanggal']) ? date('Y-m-d\TH:i', strtotime($editData['tanggal'])) : date('Y-m-d\TH:i')) ?>">
                </div>

                <div class="full">
                    <label>Keluhan Utama</label>
                    <textarea name="keluhan" rows="3"><?= e($editData['keluhan'] ?? '') ?></textarea>
                </div>

                <div>
                    <label>Diagnosa</label>
                    <input type="text" id="diagnosa" name="diagnosa" value="<?= e($editData['diagnosa'] ?? '') ?>" placeholder="Diagnosa">
                </div>

                <div>
                    <label>Dokter</label>
                    <input type="text" name="dokter" value="<?= e($editData['dokter'] ?? 'drg. Andreas Aryo Risky Prasetyo') ?>">
                </div>

                <div>
                    <label>ICD-10</label>
                    <select onchange="isiIcd(this)">
                        <option value="">Pilih ICD-10</option>
                        <?php foreach ($icd10List as $icd): ?>
                            <option
                                value="<?= e($icd['kode'] ?? '') ?>"
                                data-nama="<?= e($icd['diagnosis'] ?? '') ?>"
                                <?= (($editData['icd10_code'] ?? '') === ($icd['kode'] ?? '')) ? 'selected' : '' ?>>
                                <?= e($icd['kode'] ?? '') ?> - <?= e($icd['diagnosis'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" id="icd10_code" name="icd10_code" value="<?= e($editData['icd10_code'] ?? '') ?>">
                </div>

                <div class="full">
                    <label>Pilih Tindakan dari Master</label>
                    <select onchange="tambahTindakanDariMaster(this)">
                        <option value="">Pilih tindakan...</option>
                        <?php foreach ($tindakanList as $t): ?>
                            <?php $namaT = $t['nama_tindakan'] ?? $t['nama'] ?? ''; ?>
                            <option value="<?= (int)($t['id'] ?? 0) ?>" data-nama="<?= e($namaT) ?>">
                                <?= e($namaT) ?><?= !empty($t['kategori']) ? ' - ' . e($t['kategori']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="full">
                    <label>Tindakan</label>
                    <textarea id="tindakan" name="tindakan" rows="4" placeholder="Pilih dari master tindakan atau tulis manual"><?= e($editData['tindakan'] ?? '') ?></textarea>
                </div>

                <div class="full">
                    <label>Catatan</label>
                    <textarea name="catatan" rows="3"><?= e($editData['catatan'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="row" style="margin-top:16px">
                <button type="submit" style="width:auto">Simpan Kunjungan</button>
                <?php if ($editData): ?>
                    <a href="kunjungan.php?pasien_id=<?= (int)$pasienId ?>" class="btn secondary">Batal Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Riwayat Kunjungan</h2>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Pasien</th>
                        <th>Keluhan</th>
                        <th>Diagnosa</th>
                        <th>Tindakan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($kunjunganList as $k): ?>
                    <tr>
                        <td><?= e($k['tanggal'] ?? '') ?></td>
                        <td>
                            <span class="badge"><?= e($k['no_rm'] ?? '') ?></span><br>
                            <strong><?= e($k['nama'] ?? '') ?></strong>
                        </td>
                        <td><?= nl2br(e($k['keluhan'] ?? '')) ?></td>
                        <td>
                            <?= e($k['icd10_code'] ?? '') ?><br>
                            <strong><?= e($k['diagnosa'] ?? '') ?></strong>
                        </td>
                        <td><?= nl2br(e($k['tindakan'] ?? '')) ?></td>
                        <td class="actions">
                            <a href="kunjungan.php?edit=<?= (int)$k['id'] ?>">Edit</a>
                            <a href="odontogram.php?pasien_id=<?= (int)$k['pasien_id'] ?>&kunjungan_id=<?= (int)$k['id'] ?>">Odontogram</a>
                            <a href="invoices.php?pasien_id=<?= (int)$k['pasien_id'] ?>&kunjungan_id=<?= (int)$k['id'] ?>">Invoices</a>
                            <a href="resume_medis.php?kunjungan_id=<?= (int)$k['id'] ?>">Resume</a>
                            <a href="surat_sakit.php?kunjungan_id=<?= (int)$k['id'] ?>">Surat Sakit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$kunjunganList): ?>
                    <tr>
                        <td colspan="6">Belum ada riwayat kunjungan.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
