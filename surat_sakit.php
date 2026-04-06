<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

$kunjungan_id = (int)($_GET['kunjungan_id'] ?? $_POST['kunjungan_id'] ?? 0);

if ($kunjungan_id <= 0) {
    die('Surat sakit harus dibuka dari data kunjungan.');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor_surat      = trim($_POST['nomor_surat'] ?? next_nomor_surat());
    $tanggal_surat    = trim($_POST['tanggal_surat'] ?? date('Y-m-d'));
    $tanggal_mulai    = trim($_POST['tanggal_mulai'] ?? date('Y-m-d'));
    $tanggal_selesai  = trim($_POST['tanggal_selesai'] ?? date('Y-m-d'));
    $lama_istirahat   = (int)($_POST['lama_istirahat'] ?? 1);
    $diagnosis_singkat= trim($_POST['diagnosis_singkat'] ?? '');
    $keterangan       = trim($_POST['keterangan'] ?? '');
    $dokter_nama      = trim($_POST['dokter_nama'] ?? 'drg. Andreas Aryo Risky Prasetyo');
    $dokter_sip       = trim($_POST['dokter_sip'] ?? '');

    $existing = db_fetch_one("SELECT id FROM surat_sakit WHERE kunjungan_id = ?", [$kunjungan_id]);

    if ($existing) {
        $ok = db_run("
            UPDATE surat_sakit SET
                pasien_id = ?,
                nomor_surat = ?,
                tanggal_surat = ?,
                tanggal_mulai = ?,
                tanggal_selesai = ?,
                lama_istirahat = ?,
                diagnosis_singkat = ?,
                keterangan = ?,
                dokter_nama = ?,
                dokter_sip = ?,
                updated_at = NOW()
            WHERE kunjungan_id = ?
        ", [
            $pasien_id,
            $nomor_surat,
            $tanggal_surat,
            $tanggal_mulai,
            $tanggal_selesai,
            $lama_istirahat,
            $diagnosis_singkat,
            $keterangan,
            $dokter_nama,
            $dokter_sip,
            $kunjungan_id
        ]);
    } else {
        $ok = db_insert("
            INSERT INTO surat_sakit
            (pasien_id, kunjungan_id, nomor_surat, tanggal_surat, tanggal_mulai, tanggal_selesai, lama_istirahat, diagnosis_singkat, keterangan, dokter_nama, dokter_sip)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $pasien_id,
            $kunjungan_id,
            $nomor_surat,
            $tanggal_surat,
            $tanggal_mulai,
            $tanggal_selesai,
            $lama_istirahat,
            $diagnosis_singkat,
            $keterangan,
            $dokter_nama,
            $dokter_sip
        ]);
    }

    if ($ok) {
        $_SESSION['success'] = 'Surat sakit berhasil disimpan.';
    } else {
        $_SESSION['error'] = 'Gagal menyimpan surat sakit.';
    }

    header('Location: surat_sakit.php?kunjungan_id=' . $kunjungan_id);
    exit;
}

$data = db_fetch_one("SELECT * FROM surat_sakit WHERE kunjungan_id = ?", [$kunjungan_id]);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Surat Sakit</title>
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
.print-area{line-height:1.7}
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
            <h1 style="margin:0">Surat Sakit</h1>
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
                    <label>Nomor Surat</label>
                    <input type="text" name="nomor_surat" value="<?= e($data['nomor_surat'] ?? next_nomor_surat()) ?>">
                </div>
                <div>
                    <label>Tanggal Surat</label>
                    <input type="date" name="tanggal_surat" value="<?= e($data['tanggal_surat'] ?? date('Y-m-d')) ?>">
                </div>
                <div>
                    <label>Tanggal Mulai Istirahat</label>
                    <input type="date" name="tanggal_mulai" value="<?= e($data['tanggal_mulai'] ?? date('Y-m-d')) ?>">
                </div>
                <div>
                    <label>Tanggal Selesai Istirahat</label>
                    <input type="date" name="tanggal_selesai" value="<?= e($data['tanggal_selesai'] ?? date('Y-m-d')) ?>">
                </div>
                <div>
                    <label>Lama Istirahat (hari)</label>
                    <input type="number" name="lama_istirahat" value="<?= e($data['lama_istirahat'] ?? 1) ?>">
                </div>
                <div>
                    <label>Diagnosis Singkat</label>
                    <input type="text" name="diagnosis_singkat" value="<?= e($data['diagnosis_singkat'] ?? $kunjungan['diagnosa'] ?? '') ?>">
                </div>
                <div class="full">
                    <label>Keterangan</label>
                    <textarea name="keterangan" rows="3"><?= e($data['keterangan'] ?? 'Pasien dianjurkan istirahat untuk pemulihan kondisi kesehatan.') ?></textarea>
                </div>
                <div>
                    <label>Nama Dokter</label>
                    <input type="text" name="dokter_nama" value="<?= e($data['dokter_nama'] ?? 'drg. Andreas Aryo Risky Prasetyo') ?>">
                </div>
                <div>
                    <label>SIP Dokter</label>
                    <input type="text" name="dokter_sip" value="<?= e($data['dokter_sip'] ?? '') ?>">
                </div>
            </div>
            <div style="margin-top:16px">
                <button type="submit">Simpan Surat Sakit</button>
            </div>
        </form>
    </div>

    <div class="card print-area">
        <div style="text-align:center">
            <h2 style="margin:0"><?= e(KLINIK_NAMA) ?></h2>
            <div><?= e(KLINIK_ALAMAT) ?></div>
            <div><?= e(KLINIK_TELP) ?></div>
            <hr>
            <h3 style="margin:0">SURAT SAKIT</h3>
            <div>Nomor: <?= e($data['nomor_surat'] ?? next_nomor_surat()) ?></div>
        </div>

        <p style="margin-top:24px">
            Yang bertanda tangan di bawah ini menerangkan bahwa:
        </p>

        <table style="width:100%;border-collapse:collapse">
            <tr><td style="width:180px;padding:4px 0">Nama</td><td>: <?= e($kunjungan['nama']) ?></td></tr>
            <tr><td style="padding:4px 0">No. RM</td><td>: <?= e($kunjungan['no_rm']) ?></td></tr>
            <tr><td style="padding:4px 0">Jenis Kelamin</td><td>: <?= e($kunjungan['jk']) ?></td></tr>
            <tr><td style="padding:4px 0">Alamat</td><td>: <?= e($kunjungan['alamat']) ?></td></tr>
            <tr><td style="padding:4px 0">Diagnosis</td><td>: <?= e($data['diagnosis_singkat'] ?? $kunjungan['diagnosa'] ?? '-') ?></td></tr>
        </table>

        <p>
            Memerlukan istirahat selama <strong><?= e($data['lama_istirahat'] ?? 1) ?> hari</strong>,
            terhitung mulai tanggal <strong><?= e($data['tanggal_mulai'] ?? date('Y-m-d')) ?></strong>
            sampai dengan <strong><?= e($data['tanggal_selesai'] ?? date('Y-m-d')) ?></strong>.
        </p>

        <p>
            <?= nl2br(e($data['keterangan'] ?? 'Pasien dianjurkan istirahat untuk pemulihan kondisi kesehatan.')) ?>
        </p>

        <p>
            Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.
        </p>

        <div style="margin-top:40px;text-align:right">
            <div><?= e($data['tanggal_surat'] ?? date('Y-m-d')) ?></div>
            <br><br><br>
            <strong><?= e($data['dokter_nama'] ?? 'drg. Andreas Aryo Risky Prasetyo') ?></strong><br>
            <span><?= e($data['dokter_sip'] ?? '') ?></span>
        </div>
    </div>
</div>
</body>
</html>
