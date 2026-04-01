<?php
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
    <title>Struk Invoice</title>
    <style>
        body {
            font-family: monospace;
            width: 320px;
            margin: 0 auto;
            padding: 10px;
            color: #000;
        }
        .center {
            text-align: center;
        }
        .line {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }
        .item-name {
            font-weight: bold;
        }
        @media print {
            button { display: none; }
        }
    </style>
</head>
<body>

<button onclick="window.print()">🖨 Print</button>

<div class="center">
    <b>KLINIK GIGI</b><br>
    Struk Pembayaran
</div>

<div class="line"></div>

No Inv : <?= htmlspecialchars($inv['nomor_invoice']) ?><br>
Tgl    : <?= htmlspecialchars($inv['tanggal_invoice']) ?><br>
No RM  : <?= htmlspecialchars($inv['no_rm'] ?? '-') ?><br>
Pasien : <?= htmlspecialchars($inv['nama']) ?><br>
Status : <?= htmlspecialchars($inv['status_bayar']) ?><br>
Bayar  : <?= htmlspecialchars($inv['metode_bayar'] ?? '-') ?><br>

<div class="line"></div>

<?php while($i = $items->fetch_assoc()): ?>
    <div class="item-name"><?= htmlspecialchars($i['nama_tindakan']) ?></div>
    <div class="row">
        <span><?= htmlspecialchars($i['qty']) ?> x <?= number_format((float)$i['harga']) ?></span>
        <span><?= number_format((float)$i['subtotal']) ?></span>
    </div>
    <?php if (!empty($i['tooth_number']) || !empty($i['surface_code'])): ?>
        <div><?= htmlspecialchars(($i['tooth_number'] ?? '-') . ' / ' . ($i['surface_code'] ?? '-')) ?></div>
    <?php endif; ?>
    <br>
<?php endwhile; ?>

<div class="line"></div>

<div class="row">
    <span>Subtotal</span>
    <span><?= number_format((float)$inv['subtotal']) ?></span>
</div>
<div class="row">
    <span>Diskon</span>
    <span><?= number_format((float)$inv['diskon']) ?></span>
</div>
<div class="row">
    <b>Total</b>
    <b><?= number_format((float)$inv['total']) ?></b>
</div>

<div class="line"></div>

<div class="center">
    Terima kasih 🙏
</div>

</body>
</html>