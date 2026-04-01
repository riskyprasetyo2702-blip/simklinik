<?php
require_once 'config.php';

$conn = new mysqli("localhost","root","","simklinik");
if ($conn->connect_error) {
    die("Koneksi gagal");
}

$id = (int)($_GET['id'] ?? 0);

$inv = $conn->query("
    SELECT i.*, p.nama, p.no_rm
    FROM invoices i
    JOIN visits v ON v.id = i.visit_id
    JOIN patients p ON p.id = v.patient_id
    WHERE i.id = $id
")->fetch_assoc();

if (!$inv) {
    die("Invoice tidak ditemukan");
}

$items = $conn->query("
    SELECT ii.*, COALESCE(ii.nama_tindakan, t.nama_tindakan) AS nama_tindakan
    FROM invoice_items ii
    LEFT JOIN treatments t ON t.id = ii.treatment_id
    WHERE ii.invoice_id = $id
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Print Invoice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 24px;
            color: #000;
        }
        .header {
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 2px solid #222;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            border-radius: 10px;
        }
        .logo-fallback {
            width: 72px;
            height: 72px;
            border-radius: 10px;
            background: #1f2937;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            font-weight: bold;
        }
        .header-text h2 {
            margin: 0 0 4px 0;
        }
        .header-text .small {
            font-size: 13px;
            color: #444;
            line-height: 1.5;
        }
        .info {
            margin-bottom: 16px;
            line-height: 1.7;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table td, .table th {
            border: 1px solid #000;
            padding: 8px;
            font-size: 14px;
        }
        .text-right {
            text-align: right;
        }
        .footer {
            margin-top: 24px;
            font-size: 13px;
        }
        @media print {
            button { display: none; }
        }
    </style>
</head>
<body>

<button onclick="window.print()">🖨 Print</button>

<div class="header">
    <?php if (file_exists(LOGO_KLINIK)): ?>
        <img src="<?= LOGO_KLINIK ?>" class="logo">
    <?php else: ?>
        <div class="logo-fallback">A</div>
    <?php endif; ?>

    <div class="header-text">
        <h2><?= NAMA_KLINIK ?></h2>
        <div class="small">
            <?= TAGLINE_KLINIK ?><br>
            <?= ALAMAT_KLINIK ?><br>
            Telp: <?= TELP_KLINIK ?> | Email: <?= EMAIL_KLINIK ?>
        </div>
    </div>
</div>

<div class="info">
    <strong>Invoice:</strong> <?= htmlspecialchars($inv['nomor_invoice']) ?><br>
    <strong>Tanggal:</strong> <?= htmlspecialchars($inv['tanggal_invoice']) ?><br>
    <strong>No RM:</strong> <?= htmlspecialchars($inv['no_rm'] ?? '-') ?><br>
    <strong>Pasien:</strong> <?= htmlspecialchars($inv['nama']) ?><br>
    <strong>Status:</strong> <?= htmlspecialchars($inv['status_bayar']) ?><br>
    <strong>Metode Bayar:</strong> <?= htmlspecialchars($inv['metode_bayar'] ?? '-') ?><br>
    <strong>Catatan:</strong> <?= htmlspecialchars($inv['catatan'] ?? '-') ?>
</div>

<table class="table">
    <tr>
        <th>Tindakan</th>
        <th>Gigi</th>
        <th>Surface</th>
        <th>Qty</th>
        <th>Harga</th>
        <th>Total</th>
    </tr>

    <?php while($i = $items->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($i['nama_tindakan']) ?></td>
        <td><?= htmlspecialchars($i['tooth_number'] ?? '-') ?></td>
        <td><?= htmlspecialchars($i['surface_code'] ?? '-') ?></td>
        <td><?= htmlspecialchars($i['qty']) ?></td>
        <td class="text-right">Rp <?= number_format((float)$i['harga'],0,',','.') ?></td>
        <td class="text-right">Rp <?= number_format((float)$i['subtotal'],0,',','.') ?></td>
    </tr>
    <?php endwhile; ?>

    <tr>
        <td colspan="5"><strong>Subtotal</strong></td>
        <td class="text-right"><strong>Rp <?= number_format((float)$inv['subtotal'],0,',','.') ?></strong></td>
    </tr>
    <tr>
        <td colspan="5"><strong>Diskon</strong></td>
        <td class="text-right"><strong>Rp <?= number_format((float)$inv['diskon'],0,',','.') ?></strong></td>
    </tr>
    <tr>
        <td colspan="5"><strong>Total Akhir</strong></td>
        <td class="text-right"><strong>Rp <?= number_format((float)$inv['total'],0,',','.') ?></strong></td>
    </tr>
</table>

<div class="footer">
    Terima kasih atas kunjungan Anda.
</div>

</body>
</html>