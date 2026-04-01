<?php
session_start();

if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "simklinik");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("ID resume tidak valid");
}

$q = mysqli_query($conn, "
    SELECT r.*, p.nama, p.no_rm
    FROM resume_medis r
    LEFT JOIN patients p ON p.id = r.pasien_id
    WHERE r.id = $id
");

if (!$q) {
    die("Query gagal: " . mysqli_error($conn));
}

$data = mysqli_fetch_assoc($q);
if (!$data) {
    die("Resume medis tidak ditemukan");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Resume Medis</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; color:#222; }
        .header { text-align:center; margin-bottom:20px; }
        .title { font-size:22px; font-weight:bold; }
        .box { border:1px solid #ccc; padding:10px; border-radius:8px; margin-bottom:12px; }
        .label { font-weight:bold; margin-bottom:5px; }
        .sign { margin-top:40px; width:300px; margin-left:auto; text-align:center; }
        @media print { .no-print { display:none; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">PRAKTEK MANDIRI DOKTER GIGI</div>
        <div>RESUME MEDIS</div>
    </div>

    <p><strong>No RM:</strong> <?= htmlspecialchars($data['no_rm'] ?? '-') ?></p>
    <p><strong>Nama Pasien:</strong> <?= htmlspecialchars($data['nama'] ?? '-') ?></p>
    <p><strong>Tanggal:</strong> <?= htmlspecialchars($data['created_at']) ?></p>

    <div class="label">Keluhan Utama</div>
    <div class="box"><?= nl2br(htmlspecialchars($data['keluhan_utama'])) ?></div>

    <div class="label">Anamnesis</div>
    <div class="box"><?= nl2br(htmlspecialchars($data['anamnesis'])) ?></div>

    <div class="label">Pemeriksaan</div>
    <div class="box"><?= nl2br(htmlspecialchars($data['pemeriksaan'])) ?></div>

    <div class="label">Diagnosa</div>
    <div class="box">
        <?= nl2br(htmlspecialchars($data['diagnosa'])) ?>
        <?php if (!empty($data['icd10_code'])): ?>
            <br><strong>ICD-10:</strong> <?= htmlspecialchars($data['icd10_code']) ?>
        <?php endif; ?>
    </div>

    <div class="label">Tindakan</div>
    <div class="box"><?= nl2br(htmlspecialchars($data['tindakan'])) ?></div>

    <div class="label">Terapi</div>
    <div class="box"><?= nl2br(htmlspecialchars($data['terapi'])) ?></div>

    <div class="label">Instruksi / Edukasi</div>
    <div class="box"><?= nl2br(htmlspecialchars($data['instruksi'])) ?></div>

    <div class="label">Catatan</div>
    <div class="box"><?= nl2br(htmlspecialchars($data['catatan'])) ?></div>

    <div class="sign">
        <p>Dokter Pemeriksa</p>
        <br><br><br>
        <strong><?= htmlspecialchars($data['dokter_nama']) ?></strong><br>
        SIP: <?= htmlspecialchars($data['dokter_sip'] ?: '-') ?>
    </div>

    <button class="no-print" onclick="window.print()">Print</button>
    <button class="no-print" onclick="window.location.href='kunjungan_detail.php?id=<?= (int)$data['kunjungan_id'] ?>'">Kembali</button>
</body>
</html>