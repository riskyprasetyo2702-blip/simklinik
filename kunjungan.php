<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

$pasienId = (int)($_GET['pasien_id'] ?? 0);
$editId   = (int)($_GET['edit'] ?? 0);
$qIcd     = trim($_GET['q_icd'] ?? '');

$pasien = null;
if ($pasienId > 0) {
    $pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasienId]);
}

$editData = null;
if ($editId > 0) {
    $editData = db_fetch_one("SELECT * FROM kunjungan WHERE id = ?", [$editId]);
    if ($editData && !$pasienId) {
        $pasienId = (int)($editData['pasien_id'] ?? 0);
        $pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasienId]);
    }
}

$pasienList   = pasien_options();
$icd10List    = icd10_options($qIcd);
$tindakanList = tindakan_options();

$kunjunganList = [];
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

$selectedIcd = $editData['icd10_code'] ?? '';
$selectedTindakanText = $editData['tindakan'] ?? '';
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
.full{grid-column:1/-1}
input,select,textarea,button{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px}
button,.btn{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;border:none;font-weight:700;cursor:pointer;padding:12px 16px;border-radius:12px}
.btn.secondary{background:#475569}
.btn.green{background:#166534}
.btn.blue{background:#1d4ed8}
.small{font-size:13px;color:#64748b}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
.actions a{margin-right:6px;margin-bottom:6px;text-decoration:none;padding:8px 10px;border-radius:10px;background:#eff6ff;color:#1d4ed8;display:inline-block;font-size:13px}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;background:#e2e8f0}
.section-title{margin:0 0 14px}
.help{background:#eff6ff;color:#1e3a8a;border:1px solid #bfdbfe;padding:12px 14px;border-radius:12px;margin-top:12px}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
<script>
function tambahIcdDariMaster(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;

    const kode = opt.getAttribute('data-kode') || '';
    const nama = opt.getAttribute('data-nama') || '';
    const gabung = kode + ' - ' + nama;

    const codeInput = document.getElementById('icd10_code');
    const diagArea = document.getElementById('diagnosa');

    const codeCurrent = codeInput.value.trim();
    const diagCurrent = diagArea.value.trim();

    if (codeCurrent === '') {
        codeInput.value = kode;
    } else {
        const arrCode = codeCurrent.split(',').map(v => v.trim()).filter(Boolean);
        if (!arrCode.includes(kode)) {
            codeInput.value = codeCurrent + ', ' + kode;
        }
    }

    if (diagCurrent === '') {
        diagArea.value = gabung;
    } else {
        const lines = diagCurrent.split('\n').map(v => v.trim()).filter(Boolean);
        if (!lines.includes(gabung)) {
            diagArea.value = diagCurrent + "\n" + gabung;
        }
    }

    sel.selectedIndex = 0;
}

function tambahTindakanDariMaster(sel) {
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;

    const nama = opt.getAttribute('data-nama') || opt.text || '';
    const area = document.getElementById('tindakan');
    const current = area.value.trim();

    if (current === '') {
        area.value = nama;
    } else {
        const lines = current.split('\n').map(v => v.trim()).filter(Boolean);
        if (!lines.includes(nama)) {
            area.value = current + "\n" + nama;
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
        <h2 class="section-title"><?= $editData ? 'Edit Kunjungan' : 'Tambah Kunjungan' ?></h2>

        <form method="post" action="simpan_kunjungan.php">
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">

            <div class="grid">
                <div>
                    <label>Pasien</label>
                    <select name="pasien_id" required>
                        <option value="">Pilih pasien</option>
                        <?php foreach ($pasienList as $p): ?>
                            <option value="<?= (int)($p['id'] ?? 0) ?>"
                                <?= ((int)($editData['pasien_id'] ?? $pasienId) === (int)($p['id'] ?? 0)) ? 'selected' : '' ?>>
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
                    <label>Pilih ICD-10 dari Master</label>
                    <select onchange="tambahIcdDariMaster(this)">
                        <option value="">Pilih ICD-10...</option>
                        <?php foreach ($icd10List as $icd): ?>
                            <option
                                value="<?= e($icd['kode'] ?? '') ?>"
                                data-kode="<?= e($icd['kode'] ?? '') ?>"
                                data-nama="<?= e($icd['diagnosis'] ?? '') ?>">
                                <?= e($icd['kode'] ?? '') ?> - <?= e($icd['diagnosis'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Kode ICD-10</label>
                    <input
                        type="text"
                        id="icd10_code"
                        name="icd10_code"
                        value="<?= e($selectedIcd) ?>"
                        placeholder="Contoh: K02.1, K04.0">
                </div>

                <div class="full">
                    <label>Diagnosa</label>
                    <textarea
                        id="diagnosa"
                        name="diagnosa"
                        rows="4"
                        placeholder="Diagnosa bisa dipilih dari master ICD-10 atau ditulis manual"><?= e($editData['diagnosa'] ?? '') ?></textarea>
                </div>

                <div>
                    <label>Dokter</label>
                    <input type="text" name="dokter" value="<?= e($editData['dokter'] ?? 'drg. Andreas Aryo Risky Prasetyo') ?>">
                </div>

                <div>
                    <label>Pilih Tindakan dari Master</label>
                    <select onchange="tambahTindakanDariMaster(this)">
                        <option value="">Pilih tindakan...</option>
                        <?php foreach ($tindakanList as $t): ?>
                            <?php
                            $namaT = $t['nama_tindakan'] ?? $t['nama'] ?? '';
                            $kategori = $t['kategori'] ?? '';
                            ?>
                            <option value="<?= (int)($t['id'] ?? 0) ?>" data-nama="<?= e($namaT) ?>">
                                <?= e($namaT) ?><?= $kategori ? ' - ' . e($kategori) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="full">
                    <label>Tindakan</label>
                    <textarea id="tindakan" name="tindakan" rows="4" placeholder="Bisa dipilih dari master tindakan atau ditulis manual"><?= e($selectedTindakanText) ?></textarea>
                </div>

                <div class="full">
                    <label>Catatan</label>
                    <textarea name="catatan" rows="3"><?= e($editData['catatan'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="help">
                ICD-10 sekarang dibuat seperti input tindakan: pilih dari master lalu masuk ke kolom <strong>Kode ICD-10</strong> dan <strong>Diagnosa</strong>. Kamu juga tetap bisa menulis manual bila diperlukan.
            </div>

            <div style="margin-top:16px" class="row">
                <button type="submit" style="width:auto">Simpan Kunjungan</button>
                <?php if ($editData): ?>
                    <a class="btn secondary" href="kunjungan.php?pasien_id=<?= (int)$pasienId ?>">Batal Edit</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="row" style="margin-bottom:12px">
            <h2 class="section-title" style="margin:0">Filter ICD-10 Master</h2>
            <form method="get" class="row" style="margin:0">
                <?php if ($pasienId > 0): ?>
                    <input type="hidden" name="pasien_id" value="<?= (int)$pasienId ?>">
                <?php endif; ?>
                <?php if ($editId > 0): ?>
                    <input type="hidden" name="edit" value="<?= (int)$editId ?>">
                <?php endif; ?>
                <input type="text" name="q_icd" value="<?= e($qIcd) ?>" placeholder="Cari kode / diagnosis ICD-10" style="min-width:280px">
                <button type="submit" style="width:auto">Cari ICD-10</button>
            </form>
        </div>
        <div class="small">Hasil pencarian ini dipakai untuk isi dropdown ICD-10 di form atas.</div>
    </div>

    <div class="card">
        <h2 class="section-title">Riwayat Kunjungan</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:170px">Tanggal</th>
                        <th>Pasien</th>
                        <th>Keluhan</th>
                        <th>Diagnosa</th>
                        <th>Tindakan</th>
                        <th style="width:320px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kunjunganList as $k): ?>
                        <tr>
                            <td><?= e($k['tanggal'] ?? '') ?></td>
                            <td>
                                <strong><?= e($k['no_rm'] ?? '') ?></strong><br>
                                <?= e($k['nama'] ?? '') ?>
                            </td>
                            <td><?= nl2br(e($k['keluhan'] ?? '')) ?></td>
                            <td>
                                <?= e($k['icd10_code'] ?? '') ?><br>
                                <strong><?= nl2br(e($k['diagnosa'] ?? '')) ?></strong>
                            </td>
                            <td><?= nl2br(e($k['tindakan'] ?? '')) ?></td>
                            <td class="actions">
                                <a href="kunjungan.php?edit=<?= (int)$k['id'] ?>">Edit</a>
                                <a href="odontogram.php?pasien_id=<?= (int)$k['pasien_id'] ?>&kunjungan_id=<?= (int)$k['id'] ?>">Odontogram</a>
                                <a href="invoice.php?pasien_id=<?= (int)$k['pasien_id'] ?>&kunjungan_id=<?= (int)$k['id'] ?>">Invoice</a>
                                <a href="resume_medis.php?kunjungan_id=<?= (int)$k['id'] ?>">Resume</a>
                                <a href="surat_sakit.php?kunjungan_id=<?= (int)$k['id'] ?>">Surat Sakit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$kunjunganList): ?>
                        <tr><td colspan="6">Belum ada riwayat kunjungan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
