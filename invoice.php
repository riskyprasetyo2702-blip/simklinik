<?php
require_once __DIR__ . '/bootstrap.php';

$pasienId = (int)($_GET['pasien_id'] ?? 0);
$kunjunganId = (int)($_GET['kunjungan_id'] ?? 0);
$editId = (int)($_GET['edit'] ?? 0);

$pasien = $pasienId > 0 ? db_fetch_one("SELECT * FROM pasien WHERE id=?", [$pasienId]) : null;
$kunjungan = $kunjunganId > 0 ? db_fetch_one("SELECT * FROM kunjungan WHERE id=?", [$kunjunganId]) : null;

$editData = null;
$editItems = [];
if ($editId > 0) {
    $editData = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$editId]);
    if ($editData) {
        $pasienId = (int)$editData['pasien_id'];
        $kunjunganId = (int)$editData['kunjungan_id'];
        $pasien = db_fetch_one("SELECT * FROM pasien WHERE id=?", [$pasienId]);
        if ($kunjunganId > 0) $kunjungan = db_fetch_one("SELECT * FROM kunjungan WHERE id=?", [$kunjunganId]);
        $editItems = db_fetch_all("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC", [$editId]);
    }
}

function next_invoice_no() {
    $date = date('Ymd');
    $row = db_fetch_one("SELECT no_invoice FROM invoice WHERE no_invoice LIKE ? ORDER BY id DESC LIMIT 1", ["INV-$date-%"]);
    $num = 1;
    if (!empty($row['no_invoice']) && preg_match('/-(\d+)$/', $row['no_invoice'], $m)) {
        $num = ((int)$m[1]) + 1;
    }
    return 'INV-' . $date . '-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
}

$pasienList = db_fetch_all("SELECT id, no_rm, nama FROM pasien ORDER BY nama ASC");
$kunjunganList = $pasienId > 0 ? db_fetch_all("SELECT id, tanggal, diagnosa, tindakan FROM kunjungan WHERE pasien_id = ? ORDER BY tanggal DESC", [$pasienId]) : [];
$invoiceList = $pasienId > 0
    ? db_fetch_all("SELECT i.*, p.no_rm, p.nama FROM invoice i JOIN pasien p ON p.id=i.pasien_id WHERE i.pasien_id=? ORDER BY i.tanggal DESC", [$pasienId])
    : db_fetch_all("SELECT i.*, p.no_rm, p.nama FROM invoice i JOIN pasien p ON p.id=i.pasien_id ORDER BY i.tanggal DESC LIMIT 100");
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice</title>
<style>
body{font-family:Arial,sans-serif;background:#f6f8fb;margin:0;color:#1f2937}.wrap{max-width:1280px;margin:24px auto;padding:0 16px}.card{background:#fff;border-radius:18px;padding:20px;box-shadow:0 8px 24px rgba(0,0,0,.06);margin-bottom:18px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}input,select,textarea,button{width:100%;padding:11px 12px;border:1px solid #d1d5db;border-radius:12px;box-sizing:border-box}button,.btn{background:#111827;color:#fff;border:none;text-decoration:none;display:inline-block;text-align:center;cursor:pointer}.table{width:100%;border-collapse:collapse}.table th,.table td{padding:10px;border-bottom:1px solid #e5e7eb;text-align:left;font-size:14px}.small{font-size:12px;color:#6b7280}.actions a{margin-right:6px;text-decoration:none;padding:7px 10px;border-radius:10px;background:#eef2ff;color:#1e3a8a;display:inline-block}.row{display:flex;gap:10px;flex-wrap:wrap}.item-row{display:grid;grid-template-columns:2fr .8fr 1fr 1fr auto;gap:10px;margin-bottom:10px}@media(max-width:900px){.grid,.item-row{grid-template-columns:1fr}}
</style>
<script>
function formatNumberInput(v){ return parseFloat(v||0) || 0; }
function hitungTotal(){
    let subtotal = 0;
    document.querySelectorAll('.item-row').forEach(function(row){
        const qty = formatNumberInput(row.querySelector('.qty').value);
        const harga = formatNumberInput(row.querySelector('.harga').value);
        const st = qty * harga;
        row.querySelector('.subtotal').value = st.toFixed(2);
        subtotal += st;
    });
    document.getElementById('subtotal').value = subtotal.toFixed(2);
    const diskon = formatNumberInput(document.getElementById('diskon').value);
    document.getElementById('total').value = Math.max(0, subtotal - diskon).toFixed(2);
}
function tambahItem(nama='', qty='1', harga='0', subtotal='0', ket=''){
    const wrap = document.getElementById('items-wrap');
    const div = document.createElement('div');
    div.className = 'item-row';
    div.innerHTML = `
        <input type="text" name="nama_item[]" placeholder="Nama tindakan / item" value="${nama}">
        <input type="number" step="0.01" class="qty" name="qty[]" value="${qty}" oninput="hitungTotal()">
        <input type="number" step="0.01" class="harga" name="harga[]" value="${harga}" oninput="hitungTotal()">
        <input type="number" step="0.01" class="subtotal" name="subtotal_item[]" value="${subtotal}" readonly>
        <button type="button" onclick="this.parentElement.remove();hitungTotal()" style="background:#dc2626">Hapus</button>
        <input type="text" name="keterangan_item[]" placeholder="Keterangan item" value="${ket}" style="grid-column:1/-1">
    `;
    wrap.appendChild(div);
    hitungTotal();
}
window.addEventListener('DOMContentLoaded', function(){
    if(document.querySelectorAll('.item-row').length===0){ tambahItem(); }
    hitungTotal();
});
</script>
</head>
<body>
<div class="wrap">
    <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:16px">
        <div>
            <h1 style="margin:0">Invoice</h1>
            <div class="small"><?= $pasien ? 'Pasien: ' . e($pasien['no_rm']) . ' - ' . e($pasien['nama']) : 'Semua invoice' ?></div>
        </div>
        <div class="row">
            <a class="btn" style="padding:11px 16px" href="pasien.php">Pasien</a>
            <a class="btn" style="padding:11px 16px;background:#4b5563" href="dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>
        <h2 style="margin-top:0"><?= $editData ? 'Edit Invoice' : 'Buat Invoice' ?></h2>
        <form method="post" action="simpan_invoice.php">
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">
            <div class="grid">
                <div>
                    <label>No Invoice</label>
                    <input type="text" name="no_invoice" required value="<?= e($editData['no_invoice'] ?? next_invoice_no()) ?>">
                </div>
                <div>
                    <label>Tanggal</label>
                    <input type="datetime-local" name="tanggal" required value="<?= e(isset($editData['tanggal']) ? date('Y-m-d\TH:i', strtotime($editData['tanggal'])) : date('Y-m-d\TH:i')) ?>">
                </div>
                <div>
                    <label>Pasien</label>
                    <select name="pasien_id" required onchange="window.location='invoice.php?pasien_id='+this.value">
                        <option value="">Pilih pasien</option>
                        <?php foreach ($pasienList as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= ((int)($editData['pasien_id'] ?? $pasienId) === (int)$p['id']) ? 'selected' : '' ?>><?= e($p['no_rm']) ?> - <?= e($p['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Kunjungan</label>
                    <select name="kunjungan_id">
                        <option value="">Tanpa kunjungan</option>
                        <?php foreach ($kunjunganList as $k): ?>
                            <option value="<?= (int)$k['id'] ?>" <?= ((int)($editData['kunjungan_id'] ?? $kunjunganId) === (int)$k['id']) ? 'selected' : '' ?>><?= e($k['tanggal']) ?> - <?= e($k['diagnosa'] ?: $k['tindakan']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Status Pembayaran</label>
                    <select name="status_bayar">
                        <?php $sb = $editData['status_bayar'] ?? 'belum terbayar'; foreach (['lunas','pending','belum terbayar'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $sb === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Metode Pembayaran</label>
                    <select name="metode_bayar">
                        <?php $mb = $editData['metode_bayar'] ?? 'tunai'; foreach (['tunai','transfer','debit','kartu kredit','qris'] as $m): ?>
                        <option value="<?= e($m) ?>" <?= $mb === $m ? 'selected' : '' ?>><?= strtoupper($m) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="card" style="margin-top:16px;background:#f9fafb">
                <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:12px">
                    <h3 style="margin:0">Item Invoice</h3>
                    <button type="button" onclick="tambahItem()" style="width:auto;padding:11px 16px">Tambah Item</button>
                </div>
                <div id="items-wrap">
                    <?php foreach ($editItems as $it): ?>
                    <div class="item-row">
                        <input type="text" name="nama_item[]" placeholder="Nama tindakan / item" value="<?= e($it['nama_item']) ?>">
                        <input type="number" step="0.01" class="qty" name="qty[]" value="<?= e($it['qty']) ?>" oninput="hitungTotal()">
                        <input type="number" step="0.01" class="harga" name="harga[]" value="<?= e($it['harga']) ?>" oninput="hitungTotal()">
                        <input type="number" step="0.01" class="subtotal" name="subtotal_item[]" value="<?= e($it['subtotal']) ?>" readonly>
                        <button type="button" onclick="this.parentElement.remove();hitungTotal()" style="background:#dc2626">Hapus</button>
                        <input type="text" name="keterangan_item[]" placeholder="Keterangan item" value="<?= e($it['keterangan']) ?>" style="grid-column:1/-1">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid">
                <div>
                    <label>Subtotal</label>
                    <input type="number" step="0.01" id="subtotal" name="subtotal" readonly value="<?= e($editData['subtotal'] ?? '0') ?>">
                </div>
                <div>
                    <label>Diskon</label>
                    <input type="number" step="0.01" id="diskon" name="diskon" oninput="hitungTotal()" value="<?= e($editData['diskon'] ?? '0') ?>">
                </div>
                <div>
                    <label>Total</label>
                    <input type="number" step="0.01" id="total" name="total" readonly value="<?= e($editData['total'] ?? '0') ?>">
                </div>
                <div class="full">
                    <label>Catatan</label>
                    <textarea name="catatan" rows="3"><?= e($editData['catatan'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="row" style="margin-top:16px">
                <button type="submit" style="width:auto;padding:11px 16px">Simpan Invoice</button>
                <?php if ($editData): ?><a class="btn" style="padding:11px 16px;width:auto;background:#2563eb" href="invoice_pdf.php?id=<?= (int)$editData['id'] ?>" target="_blank">Cetak PDF / Print</a><?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Riwayat Invoice</h2>
        <div style="overflow:auto">
            <table class="table">
                <thead><tr><th>No Invoice</th><th>Tanggal</th><th>Pasien</th><th>Total</th><th>Status</th><th>Metode</th><th>Aksi</th></tr></thead>
                <tbody>
                <?php foreach ($invoiceList as $inv): ?>
                    <tr>
                        <td><?= e($inv['no_invoice']) ?></td>
                        <td><?= e($inv['tanggal']) ?></td>
                        <td><strong><?= e($inv['no_rm']) ?></strong><br><?= e($inv['nama']) ?></td>
                        <td>Rp <?= number_format((float)$inv['total'], 0, ',', '.') ?></td>
                        <td><?= e($inv['status_bayar']) ?></td>
                        <td><?= e($inv['metode_bayar']) ?></td>
                        <td class="actions">
                            <a href="invoice.php?edit=<?= (int)$inv['id'] ?>">Edit</a>
                            <a href="invoice_pdf.php?id=<?= (int)$inv['id'] ?>" target="_blank">Print</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$invoiceList): ?><tr><td colspan="7">Belum ada invoice.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
