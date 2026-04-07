<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

if (!function_exists('next_nomor_surat')) {
    function next_nomor_surat() {
        $conn = db();
        if (!$conn) return 'SS-' . date('Ymd') . '-0001';

        if (!function_exists('table_exists') || !table_exists($conn, 'surat_sakit')) {
            return 'SS-' . date('Ymd') . '-0001';
        }

        $prefix = 'SS-' . date('Ymd') . '-';
        $row = db_fetch_one(
            "SELECT nomor_surat FROM surat_sakit WHERE nomor_surat LIKE ? ORDER BY id DESC LIMIT 1",
            [$prefix . '%']
        );

        $num = 1;
        if (!empty($row['nomor_surat']) && preg_match('/-(\d+)$/', $row['nomor_surat'], $m)) {
            $num = ((int)$m[1]) + 1;
        }

        return $prefix . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
    }
}

if (function_exists('table_exists') && !table_exists($conn, 'surat_sakit')) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS surat_sakit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pasien_id INT NOT NULL,
            kunjungan_id INT NOT NULL,
            nomor_surat VARCHAR(100) NOT NULL,
            tanggal_surat DATE NOT NULL,
            tanggal_mulai DATE NOT NULL,
            tanggal_selesai DATE NOT NULL,
            lama_istirahat INT NOT NULL DEFAULT 1,
            diagnosis_singkat VARCHAR(255) DEFAULT NULL,
            keterangan TEXT DEFAULT NULL,
            dokter_nama VARCHAR(255) DEFAULT NULL,
            dokter_sip VARCHAR(100) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pasien_id (pasien_id),
            INDEX idx_kunjungan_id (kunjungan_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

$kunjungan_id = (int)($_GET['kunjungan_id'] ?? $_POST['kunjungan_id'] ?? 0);

if ($kunjungan_id <= 0) {
    $_SESSION['error'] = 'Surat sakit harus dibuka dari data kunjungan.';
    header('Location: kunjungan.php');
    exit;
}

$kunjungan = db_fetch_one("
    SELECT k.*, p.id AS pasien_id, p.no_rm, p.nama, p.jk, p.tanggal_lahir, p.alamat
    FROM kunjungan k
    JOIN pasien p ON p.id = k.pasien_id
    WHERE k.id = ?
", [$kunjungan_id]);

if (!$kunjungan) {
    $_SESSION['error'] = 'Data kunjungan tidak ditemukan.';
    header('Location: kunjungan.php');
    exit;
}

$pasien_id = (int)($kunjungan['pasien_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor_surat       = trim($_POST['nomor_surat'] ?? next_nomor_surat());
    $tanggal_surat     = trim($_POST['tanggal_surat'] ?? date('Y-m-d'));
    $tanggal_mulai     = trim($_POST['tanggal_mulai'] ?? date('Y-m-d'));
    $tanggal_selesai   = trim($_POST['tanggal_selesai'] ?? date('Y-m-d'));
    $lama_istirahat    = (int)($_POST['lama_istirahat'] ?? 1);
    $diagnosis_singkat = trim($_POST['diagnosis_singkat'] ?? '');
    $keterangan        = trim($_POST['keterangan'] ?? '');
    $dokter_nama       = trim($_POST['dokter_nama'] ?? 'drg. Andreas Aryo Risky Prasetyo');
    $dokter_sip        = trim($_POST['dokter_sip'] ?? '');

    if ($lama_istirahat <= 0) {
        $lama_istirahat = 1;
    }

    if ($diagnosis_singkat === '') {
        $diagnosis_singkat = trim((string)($kunjungan['diagnosa'] ?? ''));
    }

    if ($keterangan === '') {
        $keterangan = 'Pasien dianjurkan istirahat untuk pemulihan kondisi kesehatan.';
    }

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

if (!$data) {
    $data = [
        'nomor_surat' => next_nomor_surat(),
        'tanggal_surat' => date('Y-m-d'),
        'tanggal_mulai' => date('Y-m-d'),
        'tanggal_selesai' => date('Y-m-d'),
        'lama_istirahat' => 1,
        'diagnosis_singkat' => $kunjungan['diagnosa'] ?? '',
        'keterangan' => 'Pasien dianjurkan istirahat untuk pemulihan kondisi kesehatan.',
        'dokter_nama' => $kunjungan['dokter'] ?? 'drg. Andreas Aryo Risky Prasetyo',
        'dokter_sip' => ''
    ];
}

$tanggalSuratCetak   = !empty($data['tanggal_surat']) ? date('d-m-Y', strtotime($data['tanggal_surat'])) : date('d-m-Y');
$tanggalMulaiCetak   = !empty($data['tanggal_mulai']) ? date('d-m-Y', strtotime($data['tanggal_mulai'])) : date('d-m-Y');
$tanggalSelesaiCetak = !empty($data['tanggal_selesai']) ? date('d-m-Y', strtotime($data['tanggal_selesai'])) : date('d-m-Y');
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Surat Sakit</title>
<style>
*{box-sizing:border-box;font-family:Inter,Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1120px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:24px;padding:24px;box-shadow:0 14px 30px rgba(15,23,42,.08);margin-bottom:18px;border:1px solid #e2e8f0}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.full{grid-column:1/-1}
label{display:block;margin-bottom:8px;font-weight:800;color:#334155}
input,textarea,button{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:14px}
button,.btn{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;border:none;font-weight:800;cursor:pointer;padding:12px 16px;border-radius:14px}
.btn.secondary{background:#475569}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.small{font-size:13px;color:#64748b}
.print-area{line-height:1.75}
.print-title{text-align:center;margin-bottom:24px}
.print-title h2{margin:0 0 6px;font-size:24px}
.print-title h3{margin:14px 0 4px;font-size:22px;letter-spacing:.08em}
.info-table{width:100%;border-collapse:collapse}
.info-table td{padding:4px 0;vertical-align:top}
.signature{margin-top:48px;text-align:right}
.header-line{margin-top:10px;border:none;border-top:2px solid #0f172a}
@media print{
    .no-print{display:none!important}
    body{background:#fff}
    .card{box-shadow:none;border:none;padding:0}
    .wrap{max-width:100%;margin:0;padding:0 10mm}
}
@media(max-width:768px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
    <div class="row no-print" style="margin-bottom:16px">
        <div>
            <h1 style="margin:0">Surat Sakit</h1>
            <div class="small"><?= e($kunjungan['no_rm'] ?? '') ?> - <?= e($kunjungan['nama'] ?? '') ?></div>
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
                    <input type="text" name="nomor_surat" value="<?= e($data['nomor_surat'] ?? '') ?>">
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
                    <input type="number" min="1" name="lama_istirahat" value="<?= e($data['lama_istirahat'] ?? 1) ?>">
                </div>
                <div>
                    <label>Diagnosis Singkat</label>
                    <input type="text" name="diagnosis_singkat" value="<?= e($data['diagnosis_singkat'] ?? '') ?>">
                </div>
                <div class="full">
                    <label>Keterangan</label>
                    <textarea name="keterangan" rows="3"><?= e($data['keterangan'] ?? '') ?></textarea>
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
        <div class="print-title">
            <h2><?= e(KLINIK_NAMA) ?></h2>
            <div><?= e(KLINIK_ALAMAT) ?></div>
            <div><?= e(KLINIK_TELP) ?></div>
            <hr class="header-line">
            <h3>SURAT SAKIT</h3>
            <div>Nomor: <?= e($data['nomor_surat'] ?? '') ?></div>
        </div>

        <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>

        <table class="info-table">
            <tr><td style="width:180px">Nama</td><td>: <?= e($kunjungan['nama'] ?? '') ?></td></tr>
            <tr><td>No. RM</td><td>: <?= e($kunjungan['no_rm'] ?? '') ?></td></tr>
            <tr><td>Jenis Kelamin</td><td>: <?= e($kunjungan['jk'] ?? '') ?></td></tr>
            <tr><td>Alamat</td><td>: <?= e($kunjungan['alamat'] ?? '') ?></td></tr>
            <tr><td>Diagnosis</td><td>: <?= e($data['diagnosis_singkat'] ?? '-') ?></td></tr>
        </table>

        <p style="margin-top:18px">
            Memerlukan istirahat selama <strong><?= e($data['lama_istirahat'] ?? 1) ?> hari</strong>,
            terhitung mulai tanggal <strong><?= e($tanggalMulaiCetak) ?></strong>
            sampai dengan <strong><?= e($tanggalSelesaiCetak) ?></strong>.
        </p>

        <p><?= nl2br(e($data['keterangan'] ?? '')) ?></p>

        <p>Demikian surat keterangan ini dibuat untuk dapat dipergunakan sebagaimana mestinya.</p>

        <div class="signature">
            <div><?= e($tanggalSuratCetak) ?></div>
            <br><br><br>
            <strong><?= e($data['dokter_nama'] ?? 'drg. Andreas Aryo Risky Prasetyo') ?></strong><br>
            <span><?= e($data['dokter_sip'] ?? '') ?></span>
        </div>
    </div>
</div>
</body>
</html>
