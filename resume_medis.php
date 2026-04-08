<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

if (!table_exists($conn, 'resume_medis')) {
    die('Tabel resume_medis tidak ditemukan.');
}

$kunjungan_id = (int)($_GET['kunjungan_id'] ?? $_POST['kunjungan_id'] ?? 0);

if ($kunjungan_id <= 0) {
    die('Resume medis harus dibuka dari data kunjungan.');
}

$kunjungan = db_fetch_one("
    SELECT k.*, p.id AS pasien_id, p.no_rm, p.nama, p.jk, p.tanggal_lahir, p.alamat
    FROM kunjungan k
    JOIN pasien p ON p.id = k.pasien_id
    WHERE k.id = ?
", [$kunjungan_id]);

if (!$kunjungan) {
    die('Data kunjungan tidak ditemukan.');
}

$pasien_id = (int)$kunjungan['pasien_id'];

/*
|--------------------------------------------------------------------------
| Simpan / update
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keluhan_utama = trim($_POST['keluhan_utama'] ?? '');
    $pemeriksaan   = trim($_POST['pemeriksaan'] ?? '');
    $diagnosa      = trim($_POST['diagnosa'] ?? '');
    $icd10_code    = trim($_POST['icd10_code'] ?? '');
    $tindakan      = trim($_POST['tindakan'] ?? '');
    $terapi        = trim($_POST['terapi'] ?? '');
    $instruksi     = trim($_POST['instruksi'] ?? '');
    $catatan       = trim($_POST['catatan'] ?? '');
    $dokter_nama   = trim($_POST['dokter_nama'] ?? 'drg. Andreas Aryo Risky Prasetyo');
    $dokter_sip    = trim($_POST['dokter_sip'] ?? '');

    $existing = db_fetch_one("SELECT id FROM resume_medis WHERE kunjungan_id = ?", [$kunjungan_id]);

    if ($existing) {
        $ok = db_run("
            UPDATE resume_medis SET
                pasien_id = ?,
                keluhan_utama = ?,
                pemeriksaan = ?,
                diagnosa = ?,
                icd10_code = ?,
                tindakan = ?,
                terapi = ?,
                instruksi = ?,
                catatan = ?,
                dokter_nama = ?,
                dokter_sip = ?,
                updated_at = NOW()
            WHERE kunjungan_id = ?
        ", [
            $pasien_id,
            $keluhan_utama,
            $pemeriksaan,
            $diagnosa,
            $icd10_code,
            $tindakan,
            $terapi,
            $instruksi,
            $catatan,
            $dokter_nama,
            $dokter_sip,
            $kunjungan_id
        ]);
    } else {
        $ok = db_insert("
            INSERT INTO resume_medis
            (pasien_id, kunjungan_id, keluhan_utama, pemeriksaan, diagnosa, icd10_code, tindakan, terapi, instruksi, catatan, dokter_nama, dokter_sip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $pasien_id,
            $kunjungan_id,
            $keluhan_utama,
            $pemeriksaan,
            $diagnosa,
            $icd10_code,
            $tindakan,
            $terapi,
            $instruksi,
            $catatan,
            $dokter_nama,
            $dokter_sip
        ]);
    }

    if ($ok) {
        $_SESSION['success'] = 'Resume medis berhasil disimpan.';
    } else {
        $_SESSION['error'] = 'Gagal menyimpan resume medis.';
    }

    header('Location: resume_medis.php?kunjungan_id=' . $kunjungan_id);
    exit;
}

$data = db_fetch_one("SELECT * FROM resume_medis WHERE kunjungan_id = ?", [$kunjungan_id]);

function hitung_umur($tanggal_lahir)
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
<title>Resume Medis</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1100px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:20px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);margin-bottom:18px}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.full{grid-column:1/-1}
input,textarea,button{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px}
button,.btn{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;border:none;font-weight:700;cursor:pointer;padding:12px 16px;border-radius:12px}
.btn.secondary{background:#475569}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.small{font-size:13px;color:#64748b}
.print-area{line-height:1.6}
@media print{
    .no-print{display:none!important}
    body{background:#fff}
    .card{box-shadow:none;border:none}
}
@media(max-width:768px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">

    <div class="row no-print" style="margin-bottom:16px">
        <div>
            <h1 style="margin:0">Resume Medis</h1>
            <div class="small"><?= e($kunjungan['no_rm']) ?> - <?= e($kunjungan['nama']) ?></div>
        </div>
        <div class="row">
            <a class="btn secondary" href="kunjungan.php?pasien_id=<?= (int)$pasien_id ?>">Kembali Kunjungan</a>
            <button type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <div class="card no-print">
        <?php flash_message(); ?>

        <form method="post">
            <input type="hidden" name="kunjungan_id" value="<?= (int)$kunjungan_id ?>">

            <div class="grid">
                <div>
                    <label>Keluhan Utama</label>
                    <textarea name="keluhan_utama" rows="3"><?= e($data['keluhan_utama'] ?? $kunjungan['keluhan'] ?? '') ?></textarea>
                </div>

                <div>
                    <label>Pemeriksaan</label>
                    <textarea name="pemeriksaan" rows="3"><?= e($data['pemeriksaan'] ?? '') ?></textarea>
                </div>

                <div>
                    <label>Diagnosa</label>
                    <input type="text" name="diagnosa" value="<?= e($data['diagnosa'] ?? $kunjungan['diagnosa'] ?? '') ?>">
                </div>

                <div>
                    <label>ICD-10</label>
                    <input type="text" name="icd10_code" value="<?= e($data['icd10_code'] ?? $kunjungan['icd10_code'] ?? '') ?>">
                </div>

                <div>
                    <label>Tindakan</label>
                    <textarea name="tindakan" rows="3"><?= e($data['tindakan'] ?? $kunjungan['tindakan'] ?? '') ?></textarea>
                </div>

                <div>
                    <label>Terapi</label>
                    <textarea name="terapi" rows="3"><?= e($data['terapi'] ?? '') ?></textarea>
                </div>

                <div>
                    <label>Instruksi</label>
                    <textarea name="instruksi" rows="3"><?= e($data['instruksi'] ?? '') ?></textarea>
                </div>

                <div>
                    <label>Catatan</label>
                    <textarea name="catatan" rows="3"><?= e($data['catatan'] ?? $kunjungan['catatan'] ?? '') ?></textarea>
                </div>

                <div>
                    <label>Nama Dokter</label>
                    <input type="text" name="dokter_nama" value="<?= e($data['dokter_nama'] ?? $kunjungan['dokter'] ?? 'drg. Andreas Aryo Risky Prasetyo') ?>">
                </div>

                <div>
                    <label>SIP Dokter</label>
                    <input type="text" name="dokter_sip" value="<?= e($data['dokter_sip'] ?? '') ?>">
                </div>
            </div>

            <div style="margin-top:16px">
                <button type="submit">Simpan Resume Medis</button>
            </div>
        </form>
    </div>

    <div class="card print-area">
        <h2 style="text-align:center;margin-top:0">RESUME MEDIS</h2>
        <p><strong>Nama Klinik:</strong> <?= e(KLINIK_NAMA) ?></p>
        <hr>

        <div class="grid">
            <div><strong>No. RM:</strong> <?= e($kunjungan['no_rm']) ?></div>
            <div><strong>Tanggal Kunjungan:</strong> <?= e($kunjungan['tanggal']) ?></div>
            <div><strong>Nama Pasien:</strong> <?= e($kunjungan['nama']) ?></div>
            <div><strong>Jenis Kelamin:</strong> <?= e($kunjungan['jk']) ?></div>
            <div><strong>Tanggal Lahir:</strong> <?= e($kunjungan['tanggal_lahir']) ?></div>
            <div><strong>Umur:</strong> <?= e(hitung_umur($kunjungan['tanggal_lahir'])) ?></div>
            <div class="full"><strong>Alamat:</strong> <?= e($kunjungan['alamat']) ?></div>
        </div>

        <hr>

        <p><strong>Keluhan Utama:</strong><br><?= nl2br(e($data['keluhan_utama'] ?? $kunjungan['keluhan'] ?? '-')) ?></p>
        <p><strong>Pemeriksaan:</strong><br><?= nl2br(e($data['pemeriksaan'] ?? '-')) ?></p>
        <p><strong>Diagnosa:</strong><br><?= nl2br(e($data['diagnosa'] ?? $kunjungan['diagnosa'] ?? '-')) ?></p>
        <p><strong>ICD-10:</strong><br><?= nl2br(e($data['icd10_code'] ?? $kunjungan['icd10_code'] ?? '-')) ?></p>
        <p><strong>Tindakan:</strong><br><?= nl2br(e($data['tindakan'] ?? $kunjungan['tindakan'] ?? '-')) ?></p>
        <p><strong>Terapi:</strong><br><?= nl2br(e($data['terapi'] ?? '-')) ?></p>
        <p><strong>Instruksi:</strong><br><?= nl2br(e($data['instruksi'] ?? '-')) ?></p>
        <p><strong>Catatan:</strong><br><?= nl2br(e($data['catatan'] ?? $kunjungan['catatan'] ?? '-')) ?></p>

        <div style="margin-top:40px;text-align:right">
            <div><?= date('d-m-Y') ?></div>
            <br><br><br>
            <strong><?= e($data['dokter_nama'] ?? $kunjungan['dokter'] ?? 'drg. Andreas Aryo Risky Prasetyo') ?></strong><br>
            <span><?= e($data['dokter_sip'] ?? '') ?></span>
        </div>
    </div>

</div>
</body>
</html>
