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
    die("ID surat sakit tidak valid");
}

$q = mysqli_query($conn, "
    SELECT s.*, p.nama, p.no_rm
    FROM surat_sakit s
    LEFT JOIN patients p ON p.id = s.pasien_id
    WHERE s.id = $id
");

if (!$q) {
    die("Query gagal: " . mysqli_error($conn));
}

$data = mysqli_fetch_assoc($q);
if (!$data) {
    die("Surat sakit tidak ditemukan");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Surat Sakit</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color:#222; line-height:1.6; }
        .header { text-align:center; margin-bottom:25px; }
        .title { font-size:22px; font-weight:bold; text-decoration:underline; }
        .sign { margin-top:50px; width:300px; margin-left:auto; text-align:center; }
        @media print { .no-print { display:none; } }
    </style>
</head>
<body>
    <div class="header">
        <div style="font-size:22px; font-weight:bold;">PRAKTEK MANDIRI DOKTER GIGI</div>
        <div class="title">SURAT SAKIT</div>
        <div>Nomor: <?= htmlspecialchars($data['nomor_surat']) ?></div>
    </div>

    <p>Yang bertanda tangan di bawah ini menerangkan bahwa:</p>

    <p>
        <strong>No RM</strong> : <?= htmlspecialchars($data['no_rm'] ?? '-') ?><br>
        <strong>Nama</strong> : <?= htmlspecialchars($data['nama'] ?? '-') ?>
    </p>

    <p>
        Berdasarkan hasil pemeriksaan, pasien tersebut memerlukan istirahat selama
        <strong><?= (int)$data['lama_istirahat'] ?> hari</strong>,
        terhitung mulai tanggal <strong><?= htmlspecialchars($data['tanggal_mulai']) ?></strong>
        sampai dengan <strong><?= htmlspecialchars($data['tanggal_selesai']) ?></strong>.
    </p>

    <p><strong>Diagnosis Singkat:</strong> <?= htmlspecialchars($data['diagnosis_singkat'] ?: '-') ?></p>
    <p><strong>Keterangan:</strong><br><?= nl2br(htmlspecialchars($data['keterangan'] ?: '-')) ?></p>

    <p>Demikian surat ini dibuat untuk dipergunakan sebagaimana mestinya.</p>

    <div class="sign">
        <div><?= htmlspecialchars($data['tanggal_surat']) ?></div>
        <div>Dokter Pemeriksa</div>
        <br><br><br>
        <strong><?= htmlspecialchars($data['dokter_nama']) ?></strong><br>
        SIP: <?= htmlspecialchars($data['dokter_sip'] ?: '-') ?>
    </div>

    <button class="no-print" onclick="window.print()">Print</button>
    <button class="no-print" onclick="window.location.href='kunjungan_detail.php?id=<?= (int)$data['kunjungan_id'] ?>'">Kembali</button>
</body>
</html>