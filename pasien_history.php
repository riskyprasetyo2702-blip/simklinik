<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

$pasien_id = (int)($_GET['pasien_id'] ?? 0);

if ($pasien_id <= 0) {
    die('Pasien tidak valid.');
}

if (!table_exists($conn, 'pasien')) {
    die('Tabel pasien tidak ditemukan.');
}

$pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasien_id]);
if (!$pasien) {
    die('Data pasien tidak ditemukan.');
}

/*
|--------------------------------------------------------------------------
| Riwayat kunjungan
|--------------------------------------------------------------------------
*/
$kunjunganList = [];
if (table_exists($conn, 'kunjungan')) {
    $kunjunganList = db_fetch_all("
        SELECT *
        FROM kunjungan
        WHERE pasien_id = ?
        ORDER BY tanggal DESC, id DESC
    ", [$pasien_id]);
}

/*
|--------------------------------------------------------------------------
| Riwayat invoice
|--------------------------------------------------------------------------
*/
$invoiceList = [];
if (table_exists($conn, 'invoice')) {
    $invoiceList = db_fetch_all("
        SELECT *
        FROM invoice
        WHERE pasien_id = ?
        ORDER BY tanggal DESC, id DESC
    ", [$pasien_id]);
}

/*
|--------------------------------------------------------------------------
| Riwayat odontogram
|--------------------------------------------------------------------------
*/
$odontogramList = [];
if (table_exists($conn, 'odontogram_tindakan')) {
    $odontogramList = db_fetch_all("
        SELECT *
        FROM odontogram_tindakan
        WHERE pasien_id = ?
        ORDER BY created_at DESC, id DESC
    ", [$pasien_id]);
}

function hitung_umur_pasien($tanggal_lahir)
{
    if (!$tanggal_lahir) return '-';
    try {
        $lahir = new DateTime($tanggal_lahir);
        $hariIni = new DateTime();
        return $hariIni->diff($lahir)->y . ' tahun';
    } catch (Throwable $e) {
        return '-';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Riwayat Pasien</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1350px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:20px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);margin-bottom:18px}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.info-box{
    background:linear-gradient(135deg,#0f172a,#1d4ed8);
    color:#fff;
    border-radius:18px;
    padding:18px;
}
.info-box .label{font-size:13px;opacity:.9;margin-bottom:8px}
.info-box .value{font-size:18px;font-weight:700}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
.table th{background:#f8fafc}
.btn{background:#0f172a;color:#fff;text-decoration:none;padding:10px 14px;border-radius:10px;display:inline-block;font-weight:700}
.btn.secondary{background:#475569}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#e2e8f0;font-size:12px}
.actions a{margin-right:6px;margin-bottom:6px;text-decoration:none;padding:8px 10px;border-radius:10px;background:#eff6ff;color:#1d4ed8;display:inline-block;font-size:13px}
.small{font-size:13px;color:#64748b}
@media(max-width:1000px){.grid{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">

    <div class="row" style="margin-bottom:16px">
        <div>
            <h1 style="margin:0">Riwayat Pasien</h1>
            <div class="small"><?= e($pasien['no_rm'] ?? '') ?> - <?= e($pasien['nama'] ?? '') ?></div>
        </div>
        <div class="row">
            <a class="btn secondary" href="pasien.php">Data Pasien</a>
            <a class="btn" href="kunjungan.php?pasien_id=<?= (int)$pasien_id ?>">Kunjungan</a>
        </div>
    </div>

    <div class="grid">
        <div class="info-box">
            <div class="label">No. RM</div>
            <div class="value"><?= e($pasien['no_rm'] ?? '-') ?></div>
        </div>
        <div class="info-box">
            <div class="label">Nama Pasien</div>
            <div class="value"><?= e($pasien['nama'] ?? '-') ?></div>
        </div>
        <div class="info-box">
            <div class="label">Jenis Kelamin / Umur</div>
            <div class="value"><?= e($pasien['jk'] ?? '-') ?> / <?= e(hitung_umur_pasien($pasien['tanggal_lahir'] ?? '')) ?></div>
        </div>
        <div class="info-box">
            <div class="label">Telepon</div>
            <div class="value"><?= e($pasien['telepon'] ?? '-') ?></div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Identitas Pasien</h2>
        <table class="table">
            <tr><th style="width:220px">No. RM</th><td><?= e($pasien['no_rm'] ?? '-') ?></td></tr>
            <tr><th>NIK</th><td><?= e($pasien['nik'] ?? '-') ?></td></tr>
            <tr><th>Nama</th><td><?= e($pasien['nama'] ?? '-') ?></td></tr>
            <tr><th>Jenis Kelamin</th><td><?= e($pasien['jk'] ?? '-') ?></td></tr>
            <tr><th>Tempat, Tanggal Lahir</th><td><?= e($pasien['tempat_lahir'] ?? '-') ?>, <?= e($pasien['tanggal_lahir'] ?? '-') ?></td></tr>
            <tr><th>Telepon</th><td><?= e($pasien['telepon'] ?? '-') ?></td></tr>
            <tr><th>Alamat</th><td><?= e($pasien['alamat'] ?? '-') ?></td></tr>
            <tr><th>Alergi</th><td><?= nl2br(e($pasien['alergi'] ?? '-')) ?></td></tr>
        </table>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Riwayat Kunjungan</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Keluhan</th>
                        <th>Diagnosa</th>
                        <th>Tindakan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($kunjunganList as $k): ?>
                        <tr>
                            <td><?= e($k['tanggal'] ?? '-') ?></td>
                            <td><?= nl2br(e($k['keluhan'] ?? '-')) ?></td>
                            <td>
                                <span class="badge"><?= e($k['icd10_code'] ?? '-') ?></span><br>
                                <?= e($k['diagnosa'] ?? '-') ?>
                            </td>
                            <td><?= nl2br(e($k['tindakan'] ?? '-')) ?></td>
                            <td class="actions">
                                <a href="kunjungan.php?edit=<?= (int)$k['id'] ?>">Edit</a>
                                <a href="odontogram.php?pasien_id=<?= (int)$pasien_id ?>&kunjungan_id=<?= (int)$k['id'] ?>">Odontogram</a>
                                <a href="invoice.php?pasien_id=<?= (int)$pasien_id ?>&kunjungan_id=<?= (int)$k['id'] ?>">Invoice</a>
                                <a href="resume_medis.php?kunjungan_id=<?= (int)$k['id'] ?>">Resume</a>
                                <a href="surat_sakit.php?kunjungan_id=<?= (int)$k['id'] ?>">Surat Sakit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$kunjunganList): ?>
                        <tr><td colspan="5">Belum ada riwayat kunjungan.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Riwayat Odontogram</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Gigi</th>
                        <th>Surface</th>
                        <th>Tindakan</th>
                        <th>Kategori</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($odontogramList as $o): ?>
                        <tr>
                            <td><?= e($o['created_at'] ?? '-') ?></td>
                            <td><span class="badge"><?= e($o['nomor_gigi'] ?? '-') ?></span></td>
                            <td><?= e($o['surface_code'] ?? '-') ?></td>
                            <td><?= e($o['nama_tindakan'] ?? '-') ?></td>
                            <td><?= e($o['kategori'] ?? '-') ?></td>
                            <td><?= rupiah($o['subtotal'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$odontogramList): ?>
                        <tr><td colspan="6">Belum ada riwayat odontogram.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Riwayat Invoice</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No. Invoice</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Metode</th>
                        <th>Total</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoiceList as $inv): ?>
                        <tr>
                            <td><span class="badge"><?= e($inv['no_invoice'] ?? '-') ?></span></td>
                            <td><?= e($inv['tanggal'] ?? '-') ?></td>
                            <td><?= e($inv['status_bayar'] ?? '-') ?></td>
                            <td><?= strtoupper(e($inv['metode_bayar'] ?? '-')) ?></td>
                            <td><?= rupiah($inv['total'] ?? 0) ?></td>
                            <td class="actions">
                                <a href="invoice.php?edit=<?= (int)$inv['id'] ?>">Buka</a>
                                <a href="invoice_pdf.php?id=<?= (int)$inv['id'] ?>" target="_blank">Print</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$invoiceList): ?>
                        <tr><td colspan="6">Belum ada riwayat invoice.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
