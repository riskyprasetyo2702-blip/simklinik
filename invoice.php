<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

$pasien_id    = (int)($_GET['pasien_id'] ?? 0);
$kunjungan_id = (int)($_GET['kunjungan_id'] ?? 0);
$edit_id      = (int)($_GET['edit'] ?? 0);

/*
|--------------------------------------------------------------------------
| Ambil invoice / buat baru
|--------------------------------------------------------------------------
*/
$invoice = null;

if ($edit_id > 0) {
    $invoice = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$edit_id]);
}

if (!$invoice && $pasien_id > 0 && $kunjungan_id > 0) {
    $invoice = db_fetch_one(
        "SELECT * FROM invoice WHERE pasien_id = ? AND kunjungan_id = ? ORDER BY id DESC LIMIT 1",
        [$pasien_id, $kunjungan_id]
    );

    if (!$invoice) {
        $new_id = db_insert("
            INSERT INTO invoice
            (no_invoice, pasien_id, kunjungan_id, tanggal, subtotal, diskon, total, status_bayar, metode_bayar)
            VALUES (?, ?, ?, NOW(), 0, 0, 0, 'belum terbayar', 'tunai')
        ", [
            next_invoice_no(),
            $pasien_id,
            $kunjungan_id
        ]);

        $invoice = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$new_id]);
    }
}

$invoice_id = (int)($invoice['id'] ?? 0);

/*
|--------------------------------------------------------------------------
| Tarik item dari odontogram (auto push)
|--------------------------------------------------------------------------
*/
if ($invoice_id > 0 && table_exists($conn, 'odontogram_tindakan')) {

    $odonto = db_fetch_all("
        SELECT * FROM odontogram_tindakan
        WHERE kunjungan_id = ?
    ", [$invoice['kunjungan_id']]);

    foreach ($odonto as $o) {

        $cek = db_fetch_one("
            SELECT id FROM invoice_items
            WHERE invoice_id = ?
            AND nama_tindakan = ?
            AND tooth_number = ?
        ", [
            $invoice_id,
            $o['nama_tindakan'],
            $o['nomor_gigi']
        ]);

        if (!$cek) {
            db_insert("
                INSERT INTO invoice_items
                (invoice_id, treatment_id, nama_tindakan, qty, harga, subtotal, tooth_number, surface_code, sumber)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'odontogram')
            ", [
                $invoice_id,
                $o['tindakan_id'],
                $o['nama_tindakan'],
                $o['qty'],
                $o['harga'],
                $o['subtotal'],
                $o['nomor_gigi'],
                $o['surface_code']
            ]);
        }
    }
}

/*
|--------------------------------------------------------------------------
| Ambil item invoice
|--------------------------------------------------------------------------
*/
$items = [];
if ($invoice_id > 0) {
    $items = db_fetch_all("SELECT * FROM invoice_items WHERE invoice_id = ?", [$invoice_id]);
}

/*
|--------------------------------------------------------------------------
| Hitung ulang total
|--------------------------------------------------------------------------
*/
$subtotal = 0;
foreach ($items as $it) {
    $subtotal += (float)$it['subtotal'];
}

$diskon = (float)($invoice['diskon'] ?? 0);
$total  = max(0, $subtotal - $diskon);

db_run("UPDATE invoice SET subtotal=?, total=? WHERE id=?", [$subtotal, $total, $invoice_id]);

/*
|--------------------------------------------------------------------------
| Data pasien
|--------------------------------------------------------------------------
*/
$pasien = null;
if ($invoice) {
    $pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$invoice['pasien_id']]);
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice</title>
<style>
body{font-family:Arial;background:#f4f7fb;margin:0}
.wrap{max-width:1100px;margin:20px auto;padding:0 16px}
.card{background:#fff;padding:20px;border-radius:16px;margin-bottom:16px}
input,select{padding:10px;border:1px solid #ccc;border-radius:8px;width:100%}
button{padding:10px 14px;border:none;border-radius:8px;background:#111;color:#fff;cursor:pointer}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;border-bottom:1px solid #ddd}
.right{text-align:right}
</style>
</head>
<body>

<div class="wrap">

<div class="card">
    <h2>Invoice</h2>
    <div>No: <?= e($invoice['no_invoice'] ?? '-') ?></div>
    <div>Pasien: <?= e($pasien['nama'] ?? '-') ?></div>
</div>

<div class="card">
    <h3>Tambah Item Manual</h3>
    <form method="post" action="simpan_invoice.php">
        <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

        <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:10px">
            <input type="text" name="nama_tindakan" placeholder="Nama tindakan">
            <input type="number" name="qty" value="1">
            <input type="number" name="harga" placeholder="Harga">
            <button type="submit" name="tambah_item">Tambah</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Item Invoice</h3>
    <table class="table">
        <tr>
            <th>Tindakan</th>
            <th>Qty</th>
            <th>Harga</th>
            <th>Subtotal</th>
        </tr>

        <?php foreach ($items as $it): ?>
        <tr>
            <td><?= e($it['nama_tindakan']) ?></td>
            <td><?= e($it['qty']) ?></td>
            <td><?= rupiah($it['harga']) ?></td>
            <td><?= rupiah($it['subtotal']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="card">
    <form method="post" action="simpan_invoice.php">
        <input type="hidden" name="invoice_id" value="<?= $invoice_id ?>">

        <div>Subtotal: <strong><?= rupiah($subtotal) ?></strong></div>

        <div style="margin-top:10px">
            Diskon:
            <input type="number" name="diskon" value="<?= $diskon ?>">
        </div>

        <div style="margin-top:10px">
            Status:
            <select name="status_bayar">
                <option <?= $invoice['status_bayar']=='belum terbayar'?'selected':'' ?>>belum terbayar</option>
                <option <?= $invoice['status_bayar']=='pending'?'selected':'' ?>>pending</option>
                <option <?= $invoice['status_bayar']=='lunas'?'selected':'' ?>>lunas</option>
            </select>
        </div>

        <div style="margin-top:10px">
            Metode:
            <select name="metode_bayar">
                <option>tunai</option>
                <option>transfer</option>
                <option>qris</option>
            </select>
        </div>

        <div style="margin-top:20px">
            <button type="submit" name="simpan_invoice">Simpan Invoice</button>
            <button type="button" onclick="window.print()">Print</button>
        </div>
    </form>
</div>

</div>
</body>
</html>
