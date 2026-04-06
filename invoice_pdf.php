<?php
require_once __DIR__ . '/bootstrap.php';

$id = (int)($_GET['id'] ?? 0);
$invoice = db_fetch_one("SELECT i.*, p.no_rm, p.nama, p.alamat, p.telepon FROM invoice i JOIN pasien p ON p.id=i.pasien_id WHERE i.id=?", [$id]);
if (!$invoice) die('Invoice tidak ditemukan.');
$items = db_fetch_all("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id ASC", [$id]);
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><title>Cetak Invoice</title>
<style>
body{font-family:Arial,sans-serif;color:#111;margin:30px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:8px;font-size:13px}.no-border td{border:none;padding:3px 0}h1,h2,h3,p{margin:0}.text-right{text-align:right}.text-center{text-align:center}.mb{margin-bottom:20px}.badge{display:inline-block;padding:6px 10px;border-radius:10px;background:#eee;font-size:12px}
@media print {.no-print{display:none} body{margin:0;padding:16px}}
</style>
</head>
<body>
<div class="no-print mb"><button onclick="window.print()">Print / Save PDF</button></div>
<table class="no-border mb">
<tr>
<td>
    <h2>INVOICE KLINIK</h2>
    <p>No: <?= e($invoice['no_invoice']) ?></p>
    <p>Tanggal: <?= e($invoice['tanggal']) ?></p>
</td>
<td class="text-right">
    <span class="badge">Status: <?= e(strtoupper($invoice['status_bayar'])) ?></span>
</td>
</tr>
</table>

<table class="no-border mb">
<tr><td width="100"><strong>No RM</strong></td><td>: <?= e($invoice['no_rm']) ?></td></tr>
<tr><td><strong>Nama</strong></td><td>: <?= e($invoice['nama']) ?></td></tr>
<tr><td><strong>Telepon</strong></td><td>: <?= e($invoice['telepon']) ?></td></tr>
<tr><td><strong>Alamat</strong></td><td>: <?= e($invoice['alamat']) ?></td></tr>
</table>

<table class="mb">
<thead><tr><th>No</th><th>Item</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead>
<tbody>
<?php foreach ($items as $i => $it): ?>
<tr>
<td class="text-center"><?= $i+1 ?></td>
<td><?= e($it['nama_item']) ?><?= $it['keterangan'] ? '<br><small>' . e($it['keterangan']) . '</small>' : '' ?></td>
<td class="text-center"><?= e(rtrim(rtrim((string)$it['qty'], '0'), '.')) ?></td>
<td class="text-right">Rp <?= number_format((float)$it['harga'], 0, ',', '.') ?></td>
<td class="text-right">Rp <?= number_format((float)$it['subtotal'], 0, ',', '.') ?></td>
</tr>
<?php endforeach; ?>
</tbody>
<tfoot>
<tr><td colspan="4" class="text-right"><strong>Subtotal</strong></td><td class="text-right">Rp <?= number_format((float)$invoice['subtotal'], 0, ',', '.') ?></td></tr>
<tr><td colspan="4" class="text-right"><strong>Diskon</strong></td><td class="text-right">Rp <?= number_format((float)$invoice['diskon'], 0, ',', '.') ?></td></tr>
<tr><td colspan="4" class="text-right"><strong>Total</strong></td><td class="text-right"><strong>Rp <?= number_format((float)$invoice['total'], 0, ',', '.') ?></strong></td></tr>
</tfoot>
</table>

<p><strong>Metode Bayar:</strong> <?= e(strtoupper($invoice['metode_bayar'])) ?></p>
<?php if ($invoice['catatan']): ?><p><strong>Catatan:</strong> <?= nl2br(e($invoice['catatan'])) ?></p><?php endif; ?>
</body>
</html>
