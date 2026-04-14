<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

$invoice_id = (int)($_GET['id'] ?? 0);
if ($invoice_id <= 0) {
    die('Invoice tidak valid.');
}

$invoice = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$invoice_id]);
if (!$invoice) {
    die('Data invoice tidak ditemukan.');
}

$pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [(int)($invoice['pasien_id'] ?? 0)]);
$kunjungan = db_fetch_one("SELECT * FROM kunjungan WHERE id = ?", [(int)($invoice['kunjungan_id'] ?? 0)]);
$items = table_exists($conn, 'invoice_items')
    ? db_fetch_all("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC", [$invoice_id])
    : [];

$klinik = function_exists('klinik_profile') ? klinik_profile() : [
    'nama_klinik' => KLINIK_NAMA,
    'alamat_klinik' => KLINIK_ALAMAT,
    'telepon_klinik' => KLINIK_TELP,
    'logo_path' => '',
    'qris_path' => '',
    'qris_payload' => ''
];

function pdf_item_name($row) {
    return $row['nama_tindakan'] ?? $row['nama_item'] ?? '-';
}

function pdf_item_tooth($row) {
    return $row['tooth_number'] ?? $row['nomor_gigi'] ?? '';
}

$subtotal = 0;
foreach ($items as $it) {
    $subtotal += (float)($it['subtotal'] ?? 0);
}

$diskon = (float)($invoice['diskon'] ?? 0);
$total = (float)($invoice['total'] ?? max(0, $subtotal - $diskon));
$metode = $invoice['metode_bayar'] ?? 'tunai';
$status = strtolower(trim((string)($invoice['status_bayar'] ?? 'belum terbayar')));

$tipePembayaran = strtolower(trim((string)($invoice['tipe_pembayaran'] ?? 'tunai')));
$dp = (float)($invoice['dp'] ?? 0);
$tenorBulan = (int)($invoice['tenor_bulan'] ?? 0);
$sisaTagihan = (float)($invoice['sisa_tagihan'] ?? 0);
$cicilanPerBulan = (float)($invoice['cicilan_per_bulan'] ?? 0);

if ($tipePembayaran !== 'cicilan') {
    $tipePembayaran = 'tunai';
    $dp = $total;
    $tenorBulan = 0;
    $sisaTagihan = 0;
    $cicilanPerBulan = 0;
}

$statusClass = 'belum';
if ($status === 'lunas') {
    $statusClass = 'lunas';
} elseif ($status === 'pending' || $status === 'cicilan') {
    $statusClass = 'pending';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice PDF</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#eef2f7;color:#111827}
.page{max-width:1000px;margin:24px auto;padding:0 16px}
.sheet{background:#fff;border-radius:18px;box-shadow:0 12px 28px rgba(15,23,42,.08);overflow:hidden}
.topbar{padding:18px 22px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center}
.btn{display:inline-block;text-decoration:none;background:#111827;color:#fff;padding:10px 14px;border-radius:10px;font-weight:700}
.header{padding:26px 28px;border-bottom:2px solid #111827}
.header-grid{display:grid;grid-template-columns:1.4fr 1fr;gap:18px}
.logo{max-height:70px;margin-bottom:10px}
.klinik{font-size:26px;font-weight:800;margin:0 0 8px}
.meta{font-size:14px;line-height:1.7;color:#374151}
.invoice-box{text-align:right}
.invoice-title{font-size:28px;font-weight:800;margin:0 0 8px}
.section{padding:22px 28px}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.info-card{
    border:1px solid #dbeafe;
    border-radius:16px;
    padding:16px 18px;
    background:linear-gradient(135deg,#eff6ff,#ffffff);
    box-shadow:0 8px 18px rgba(59,130,246,.08);
}

.label{
    font-size:12px;
    text-transform:uppercase;
    color:#2563eb;
    font-weight:700;
    margin-bottom:8px;
    letter-spacing:.4px;
}

.value{
    font-size:14px;
    line-height:1.7;
    color:#111827;
}

.table{
    width:100%;
    border-collapse:separate;
    border-spacing:0;
    overflow:hidden;
    border-radius:14px;
    box-shadow:0 8px 20px rgba(15,23,42,.06);
}

.table th,
.table td{
    padding:13px 12px;
    font-size:14px;
    border-bottom:1px solid #e5e7eb;
}

.table th{
    background:linear-gradient(135deg,#1d4ed8,#2563eb);
    color:#ffffff;
    font-weight:700;
    text-transform:uppercase;
    font-size:12px;
    letter-spacing:.4px;
}

.table tbody tr:nth-child(even){
    background:#f8fafc;
}

.table tbody tr:hover{
    background:#eff6ff;
}

.table tbody tr:last-child td{
    border-bottom:none;
}

.summary{
    margin-top:22px;
    margin-left:auto;
    max-width:460px;
    background:#ffffff;
    border:1px solid #dbeafe;
    border-radius:16px;
    padding:8px 14px;
    box-shadow:0 10px 24px rgba(59,130,246,.08);
}

.summary table{
    width:100%;
    border-collapse:collapse;
}

.summary td{
    padding:11px 8px;
    border-bottom:1px dashed #dbeafe;
    font-size:14px;
}

.summary tr:last-child td{
    font-size:18px;
    font-weight:800;
    color:#1d4ed8;
    border-top:2px solid #2563eb;
    border-bottom:none;
}
.status{display:inline-block;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:700}
.status.lunas{background:#dcfce7;color:#166534}
.status.pending{background:#fef3c7;color:#92400e}
.status.belum{background:#fee2e2;color:#991b1b}
.qris{margin-top:20px;border:1px dashed #9ca3af;border-radius:16px;padding:16px;text-align:center}
.qris img{max-width:240px;width:100%;height:auto}
.footer{padding:20px 28px 28px;color:#6b7280;font-size:13px}
@media print{
    body{background:#fff}
    .page{max-width:none;margin:0;padding:0}
    .sheet{box-shadow:none;border-radius:0}
    .topbar{display:none}
}
@media(max-width:768px){
    .header-grid,.info-grid{grid-template-columns:1fr}
    .invoice-box{text-align:left}
}
</style>
</head>
<body>
<div class="page">
<div class="sheet">

    <div class="topbar">
        <strong>Invoice Cetak</strong>
        <div>
            <a class="btn" href="#" onclick="window.print();return false;">Print / Save PDF</a>
        </div>
    </div>

    <div class="header">
        <div class="header-grid">
            <div>
                <?php if (!empty($klinik['logo_path'])): ?>
                    <img src="<?= e($klinik['logo_path']) ?>" alt="Logo Klinik" class="logo">
                <?php endif; ?>
                <h1 class="klinik"><?= e($klinik['nama_klinik']) ?></h1>
                <div class="meta">
                    <?= e($klinik['alamat_klinik']) ?><br>
                    <?= e($klinik['telepon_klinik']) ?>
                </div>
            </div>
            <div class="invoice-box">
                <div class="invoice-title">INVOICE</div>
                <div class="meta">
                    <strong>No:</strong> <?= e($invoice['no_invoice'] ?? '-') ?><br>
                    <strong>Tanggal:</strong> <?= e($invoice['tanggal'] ?? '-') ?><br>
                    <strong>Status:</strong>
                    <span class="status <?= $statusClass ?>">
                        <?= e($invoice['status_bayar'] ?? '-') ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="info-grid">
            <div class="info-card">
                <div class="label">Data Pasien</div>
                <div class="value">
                    <strong><?= e($pasien['nama'] ?? '-') ?></strong><br>
                    No. RM: <?= e($pasien['no_rm'] ?? '-') ?><br>
                    Telepon: <?= e($pasien['telepon'] ?? '-') ?><br>
                    Alamat: <?= e($pasien['alamat'] ?? '-') ?>
                </div>
            </div>
            <div class="info-card">
                <div class="label">Data Kunjungan</div>
                <div class="value">
                    Tanggal: <?= e($kunjungan['tanggal'] ?? '-') ?><br>
                    Dokter: <?= e($kunjungan['dokter'] ?? '-') ?><br>
                    Diagnosa: <?= e($kunjungan['diagnosa'] ?? '-') ?><br>
                    ICD-10: <?= e($kunjungan['icd10_code'] ?? '-') ?>
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Tindakan</th>
                    <th>Gigi</th>
                    <th>Qty</th>
                    <th class="right">Harga</th>
                    <th class="right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $i => $it): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e(pdf_item_name($it)) ?></td>
                        <td><?= e(pdf_item_tooth($it)) ?></td>
                        <td><?= e($it['qty'] ?? 0) ?></td>
                        <td class="right"><?= rupiah($it['harga'] ?? 0) ?></td>
                        <td class="right"><?= rupiah($it['subtotal'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                    <tr><td colspan="6">Belum ada item invoice.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="summary">
            <table>
                <tr><td>Subtotal</td><td class="right"><?= rupiah($subtotal) ?></td></tr>
                <tr><td>Diskon</td><td class="right"><?= rupiah($diskon) ?></td></tr>
                <tr><td>Total</td><td class="right"><?= rupiah($total) ?></td></tr>
                <tr><td>Tipe Pembayaran</td><td class="right"><?= strtoupper(e($tipePembayaran)) ?></td></tr>
                <tr><td>DP / Pembayaran Awal</td><td class="right"><?= rupiah($dp) ?></td></tr>
                <tr><td>Sisa Tagihan</td><td class="right"><?= rupiah($sisaTagihan) ?></td></tr>
                <tr><td>Cicilan / Bulan</td><td class="right"><?= rupiah($cicilanPerBulan) ?></td></tr>
                <tr><td>Tenor</td><td class="right"><?= $tenorBulan > 0 ? e($tenorBulan) . ' bulan' : '-' ?></td></tr>
                <tr><td>Metode Bayar</td><td class="right"><?= strtoupper(e($metode)) ?></td></tr>
            </table>
        </div>

        <?php if (strtolower($metode) === 'qris'): ?>
            <div class="qris">
                <h3 style="margin-top:0">Pembayaran QRIS</h3>
                <?php if (!empty($klinik['qris_path'])): ?>
                    <img src="<?= e($klinik['qris_path']) ?>" alt="QRIS">
                <?php else: ?>
                    <div>QRIS belum diupload.</div>
                <?php endif; ?>

                <?php if (!empty($klinik['qris_payload'])): ?>
                    <div style="margin-top:10px;font-size:12px;word-break:break-all;color:#6b7280">
                        <?= e($klinik['qris_payload']) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        Dicetak dari sistem <?= e($klinik['nama_klinik']) ?>.
    </div>

</div>
</div>
</body>
</html>
