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

if (!table_exists($conn, 'invoice')) {
    die('Tabel invoice tidak ditemukan.');
}

$invoice = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$invoice_id]);
if (!$invoice) {
    die('Data invoice tidak ditemukan.');
}

$pasien = null;
if (!empty($invoice['pasien_id']) && table_exists($conn, 'pasien')) {
    $pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [(int)$invoice['pasien_id']]);
}

$kunjungan = null;
if (!empty($invoice['kunjungan_id']) && table_exists($conn, 'kunjungan')) {
    $kunjungan = db_fetch_one("SELECT * FROM kunjungan WHERE id = ?", [(int)$invoice['kunjungan_id']]);
}

$items = [];
if (table_exists($conn, 'invoice_items')) {
    $items = db_fetch_all("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC", [$invoice_id]);
}

function item_nama($row) {
    return $row['nama_tindakan'] ?? $row['nama_item'] ?? '-';
}

function item_tooth($row) {
    return $row['tooth_number'] ?? $row['nomor_gigi'] ?? '';
}

function item_surface($row) {
    return $row['surface_code'] ?? '';
}

$subtotal = 0;
foreach ($items as $it) {
    $subtotal += (float)($it['subtotal'] ?? 0);
}

$diskon = (float)($invoice['diskon'] ?? 0);
$total  = (float)($invoice['total'] ?? max(0, $subtotal - $diskon));
$status = $invoice['status_bayar'] ?? 'belum terbayar';
$metode = $invoice['metode_bayar'] ?? 'tunai';
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?= e($invoice['no_invoice'] ?? '') ?></title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#eef2f7;color:#111827}
.page{max-width:1000px;margin:24px auto;padding:0 16px}
.sheet{
    background:#fff;
    border-radius:18px;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
    overflow:hidden;
}
.topbar{
    padding:18px 22px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
    border-bottom:1px solid #e5e7eb;
}
.btn{
    display:inline-block;
    text-decoration:none;
    background:#111827;
    color:#fff;
    padding:10px 14px;
    border-radius:10px;
    font-weight:700;
    border:none;
    cursor:pointer;
}
.btn.secondary{background:#475569}
.header{
    padding:28px 28px 18px;
    border-bottom:2px solid #111827;
}
.header-grid{
    display:grid;
    grid-template-columns:1.5fr 1fr;
    gap:18px;
}
.klinik{
    font-size:26px;
    font-weight:800;
    margin:0 0 8px;
}
.meta{
    font-size:14px;
    line-height:1.7;
    color:#374151;
}
.invoice-box{
    text-align:right;
}
.invoice-title{
    font-size:28px;
    font-weight:800;
    margin:0 0 8px;
}
.section{
    padding:22px 28px;
}
.info-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
}
.info-card{
    border:1px solid #e5e7eb;
    border-radius:14px;
    padding:14px 16px;
}
.label{
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
    color:#6b7280;
    margin-bottom:6px;
}
.value{
    font-size:14px;
    color:#111827;
    line-height:1.6;
}
.table-wrap{overflow:auto}
.table{
    width:100%;
    border-collapse:collapse;
}
.table th,.table td{
    padding:12px 10px;
    border-bottom:1px solid #e5e7eb;
    text-align:left;
    font-size:14px;
    vertical-align:top;
}
.table th{
    background:#f9fafb;
    font-size:13px;
}
.right{text-align:right}
.summary{
    margin-top:18px;
    margin-left:auto;
    max-width:380px;
}
.summary table{
    width:100%;
    border-collapse:collapse;
}
.summary td{
    padding:10px 8px;
    border-bottom:1px solid #e5e7eb;
}
.summary tr:last-child td{
    font-size:18px;
    font-weight:800;
    border-top:2px solid #111827;
}
.status{
    display:inline-block;
    padding:6px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:700;
}
.status.lunas{background:#dcfce7;color:#166534}
.status.pending{background:#fef3c7;color:#92400e}
.status.belum{background:#fee2e2;color:#991b1b}
.note{
    margin-top:20px;
    padding:14px 16px;
    border:1px solid #e5e7eb;
    border-radius:14px;
    background:#fafafa;
    font-size:14px;
    line-height:1.6;
}
.qris{
    margin-top:20px;
    border:1px dashed #9ca3af;
    border-radius:16px;
    padding:16px;
    text-align:center;
}
.footer{
    padding:20px 28px 28px;
    color:#6b7280;
    font-size:13px;
    line-height:1.6;
}
@media(max-width:768px){
    .header-grid,.info-grid{grid-template-columns:1fr}
    .invoice-box{text-align:left}
}
@media print{
    body{background:#fff}
    .page{max-width:none;margin:0;padding:0}
    .sheet{box-shadow:none;border-radius:0}
    .topbar{display:none}
}
</style>
</head>
<body>
<div class="page">
    <div class="sheet">

        <div class="topbar">
            <div><strong>Invoice Cetak</strong></div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <a class="btn secondary" href="invoice.php?edit=<?= (int)$invoice_id ?>">Kembali</a>
                <button class="btn" onclick="window.print()">Print / Save PDF</button>
            </div>
        </div>

        <div class="header">
            <div class="header-grid">
                <div>
                    <h1 class="klinik"><?= e(KLINIK_NAMA) ?></h1>
                    <div class="meta">
                        <?= e(KLINIK_ALAMAT) ?><br>
                        <?= e(KLINIK_TELP) ?>
                    </div>
                </div>
                <div class="invoice-box">
                    <div class="invoice-title">INVOICE</div>
                    <div class="meta">
                        <strong>No:</strong> <?= e($invoice['no_invoice'] ?? '-') ?><br>
                        <strong>Tanggal:</strong> <?= e($invoice['tanggal'] ?? '-') ?><br>
                        <strong>Status:</strong>
                        <?php
                        $statusClass = 'belum';
                        if (strtolower($status) === 'lunas') $statusClass = 'lunas';
                        elseif (strtolower($status) === 'pending') $statusClass = 'pending';
                        ?>
                        <span class="status <?= $statusClass ?>"><?= e($status) ?></span>
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
                        Tanggal Kunjungan: <?= e($kunjungan['tanggal'] ?? '-') ?><br>
                        Dokter: <?= e($kunjungan['dokter'] ?? '-') ?><br>
                        Diagnosa: <?= e($kunjungan['diagnosa'] ?? '-') ?><br>
                        ICD-10: <?= e($kunjungan['icd10_code'] ?? '-') ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="section">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:50px">No</th>
                            <th>Item / Tindakan</th>
                            <th style="width:90px">Gigi</th>
                            <th style="width:90px">Surface</th>
                            <th style="width:90px">Qty</th>
                            <th style="width:140px" class="right">Harga</th>
                            <th style="width:140px" class="right">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $i => $it): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td><?= e(item_nama($it)) ?></td>
                                <td><?= e(item_tooth($it)) ?></td>
                                <td><?= e(item_surface($it)) ?></td>
                                <td><?= e($it['qty'] ?? 0) ?></td>
                                <td class="right"><?= rupiah($it['harga'] ?? 0) ?></td>
                                <td class="right"><?= rupiah($it['subtotal'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$items): ?>
                            <tr>
                                <td colspan="7">Belum ada item invoice.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="summary">
                <table>
                    <tr>
                        <td>Subtotal</td>
                        <td class="right"><?= rupiah($subtotal) ?></td>
                    </tr>
                    <tr>
                        <td>Diskon</td>
                        <td class="right"><?= rupiah($diskon) ?></td>
                    </tr>
                    <tr>
                        <td>Total</td>
                        <td class="right"><?= rupiah($total) ?></td>
                    </tr>
                    <tr>
                        <td>Metode Bayar</td>
                        <td class="right"><?= strtoupper(e($metode)) ?></td>
                    </tr>
                </table>
            </div>

            <?php if (!empty($invoice['catatan'])): ?>
                <div class="note">
                    <strong>Catatan:</strong><br>
                    <?= nl2br(e($invoice['catatan'])) ?>
                </div>
            <?php endif; ?>

            <?php if (strtolower($metode) === 'qris'): ?>
                <div class="qris">
                    <h3 style="margin-top:0">Pembayaran QRIS</h3>

                    <?php if (defined('QRIS_IMAGE_URL') && QRIS_IMAGE_URL !== ''): ?>
                        <img src="<?= e(QRIS_IMAGE_URL) ?>" alt="QRIS" style="max-width:240px;width:100%;height:auto">
                    <?php else: ?>
                        <div style="padding:24px;border:1px solid #d1d5db;border-radius:12px;background:#fff">
                            QRIS belum diatur
                        </div>
                    <?php endif; ?>

                    <?php if (defined('QRIS_PAYLOAD') && QRIS_PAYLOAD !== ''): ?>
                        <div style="margin-top:12px;font-size:12px;word-break:break-all;color:#6b7280">
                            <?= e(QRIS_PAYLOAD) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            Invoice ini dicetak dari sistem SIMRS Klinik Gigi.<br>
            <?= e(KLINIK_NAMA) ?>
        </div>
    </div>
</div>
</body>
</html>
