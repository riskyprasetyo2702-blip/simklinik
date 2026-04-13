<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

if (!table_exists($conn, 'invoice')) {
    die('Tabel invoice tidak ditemukan.');
}

$pasien_id    = (int)($_GET['pasien_id'] ?? 0);
$kunjungan_id = (int)($_GET['kunjungan_id'] ?? 0);
$edit_id      = (int)($_GET['edit'] ?? 0);

$hasInvoiceCicilan = table_exists($conn, 'invoice_cicilan');
$hasTipePembayaran = column_exists($conn, 'invoice', 'tipe_pembayaran');
$hasTenorBulan     = column_exists($conn, 'invoice', 'tenor_bulan');
$hasDp             = column_exists($conn, 'invoice', 'dp');
$hasSisaTagihan    = column_exists($conn, 'invoice', 'sisa_tagihan');
$hasCicilanBulanan = column_exists($conn, 'invoice', 'cicilan_per_bulan');
$hasStatusBayar    = column_exists($conn, 'invoice', 'status_bayar');
$hasMetodeBayar    = column_exists($conn, 'invoice', 'metode_bayar');
$hasCatatan        = column_exists($conn, 'invoice', 'catatan');
$hasTanggal        = column_exists($conn, 'invoice', 'tanggal');

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
        $cols = ['no_invoice', 'pasien_id', 'kunjungan_id'];
        $vals = ['?', '?', '?'];
        $params = [next_invoice_no(), $pasien_id, $kunjungan_id];

        if ($hasTanggal) {
            $cols[] = 'tanggal';
            $vals[] = 'NOW()';
        }
        if (column_exists($conn, 'invoice', 'subtotal')) {
            $cols[] = 'subtotal';
            $vals[] = '?';
            $params[] = 0;
        }
        if (column_exists($conn, 'invoice', 'diskon')) {
            $cols[] = 'diskon';
            $vals[] = '?';
            $params[] = 0;
        }
        if (column_exists($conn, 'invoice', 'total')) {
            $cols[] = 'total';
            $vals[] = '?';
            $params[] = 0;
        }
        if ($hasStatusBayar) {
            $cols[] = 'status_bayar';
            $vals[] = '?';
            $params[] = 'belum terbayar';
        }
        if ($hasMetodeBayar) {
            $cols[] = 'metode_bayar';
            $vals[] = '?';
            $params[] = 'tunai';
        }
        if ($hasCatatan) {
            $cols[] = 'catatan';
            $vals[] = '?';
            $params[] = '';
        }
        if ($hasTipePembayaran) {
            $cols[] = 'tipe_pembayaran';
            $vals[] = '?';
            $params[] = 'tunai';
        }
        if ($hasTenorBulan) {
            $cols[] = 'tenor_bulan';
            $vals[] = '?';
            $params[] = null;
        }
        if ($hasDp) {
            $cols[] = 'dp';
            $vals[] = '?';
            $params[] = 0;
        }
        if ($hasSisaTagihan) {
            $cols[] = 'sisa_tagihan';
            $vals[] = '?';
            $params[] = 0;
        }
        if ($hasCicilanBulanan) {
            $cols[] = 'cicilan_per_bulan';
            $vals[] = '?';
            $params[] = 0;
        }

        $newId = db_insert(
            "INSERT INTO invoice (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")",
            $params
        );

        if ($newId) {
            $invoice = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$newId]);
        }
    }
}

if (!$invoice) {
    die('Invoice tidak ditemukan. Buka dari pasien / kunjungan.');
}

$invoice_id = (int)$invoice['id'];
$pasien_id = (int)($invoice['pasien_id'] ?? 0);
$kunjungan_id = (int)($invoice['kunjungan_id'] ?? 0);

$pasien = null;
if ($pasien_id > 0 && table_exists($conn, 'pasien')) {
    $pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasien_id]);
}

$kunjungan = null;
if ($kunjungan_id > 0 && table_exists($conn, 'kunjungan')) {
    $kunjungan = db_fetch_one("SELECT * FROM kunjungan WHERE id = ?", [$kunjungan_id]);
}

/*
|--------------------------------------------------------------------------
| Auto tarik item odontogram ke invoice_items
|--------------------------------------------------------------------------
*/
if ($invoice_id > 0 && table_exists($conn, 'odontogram_tindakan') && table_exists($conn, 'invoice_items')) {
    $odontoItems = db_fetch_all(
        "SELECT * FROM odontogram_tindakan WHERE kunjungan_id = ? ORDER BY id ASC",
        [$kunjungan_id]
    );

    foreach ($odontoItems as $o) {
        $namaOd = $o['nama_tindakan'] ?? '';
        $gigiOd = $o['nomor_gigi'] ?? '';

        $nameCol = column_exists($conn, 'invoice_items', 'nama_tindakan') ? 'nama_tindakan' : 'nama_item';
        $toothCol = column_exists($conn, 'invoice_items', 'tooth_number') ? 'tooth_number' : 'nomor_gigi';

        $cek = db_fetch_one(
            "SELECT id FROM invoice_items WHERE invoice_id = ? AND `$nameCol` = ? AND `$toothCol` = ? LIMIT 1",
            [$invoice_id, $namaOd, $gigiOd]
        );

        if (!$cek) {
            $data = [];

            if (column_exists($conn, 'invoice_items', 'invoice_id')) $data['invoice_id'] = $invoice_id;
            if (column_exists($conn, 'invoice_items', 'treatment_id')) $data['treatment_id'] = (int)($o['tindakan_id'] ?? 0);
            if (column_exists($conn, 'invoice_items', 'tindakan_id')) $data['tindakan_id'] = (int)($o['tindakan_id'] ?? 0);
            if (column_exists($conn, 'invoice_items', 'nama_tindakan')) $data['nama_tindakan'] = $namaOd;
            if (column_exists($conn, 'invoice_items', 'nama_item')) $data['nama_item'] = $namaOd;
            if (column_exists($conn, 'invoice_items', 'qty')) $data['qty'] = (float)($o['qty'] ?? 1);
            if (column_exists($conn, 'invoice_items', 'harga')) $data['harga'] = (float)($o['harga'] ?? 0);
            if (column_exists($conn, 'invoice_items', 'subtotal')) $data['subtotal'] = (float)($o['subtotal'] ?? 0);
            if (column_exists($conn, 'invoice_items', 'tooth_number')) $data['tooth_number'] = $gigiOd;
            if (column_exists($conn, 'invoice_items', 'nomor_gigi')) $data['nomor_gigi'] = $gigiOd;
            if (column_exists($conn, 'invoice_items', 'surface_code')) $data['surface_code'] = $o['surface_code'] ?? '';
            if (column_exists($conn, 'invoice_items', 'keterangan')) $data['keterangan'] = 'odontogram';
            if (column_exists($conn, 'invoice_items', 'sumber')) $data['sumber'] = 'odontogram';
            if (column_exists($conn, 'invoice_items', 'created_at')) $data['created_at'] = date('Y-m-d H:i:s');

            if (!empty($data)) {
                $cols = [];
                $holders = [];
                $params = [];
                foreach ($data as $col => $val) {
                    $cols[] = "`$col`";
                    $holders[] = "?";
                    $params[] = $val;
                }

                $sql = "INSERT INTO invoice_items (" . implode(',', $cols) . ") VALUES (" . implode(',', $holders) . ")";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $types = '';
                    foreach ($params as $p) {
                        if (is_int($p)) $types .= 'i';
                        elseif (is_float($p)) $types .= 'd';
                        else $types .= 's';
                    }
                    if ($params) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Hapus item
|--------------------------------------------------------------------------
*/
if (isset($_GET['hapus_item']) && table_exists($conn, 'invoice_items')) {
    $hapusId = (int)$_GET['hapus_item'];
    db_run("DELETE FROM invoice_items WHERE id = ? AND invoice_id = ?", [$hapusId, $invoice_id]);
    $_SESSION['success'] = 'Item invoice dihapus.';
    header('Location: invoice.php?edit=' . $invoice_id);
    exit;
}

$items = [];
if (table_exists($conn, 'invoice_items')) {
    $items = db_fetch_all("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC", [$invoice_id]);
}

$subtotal = 0;
foreach ($items as $it) {
    $subtotal += (float)($it['subtotal'] ?? 0);
}
$diskon = (float)($invoice['diskon'] ?? 0);
$total = max(0, $subtotal - $diskon);

$tipePembayaran = (string)($invoice['tipe_pembayaran'] ?? 'tunai');
$tenorBulan = max(2, (int)($invoice['tenor_bulan'] ?? 2));
$dp = max(0, (float)($invoice['dp'] ?? 0));
$dpEfektif = $tipePembayaran === 'cicilan' ? min($dp, $total) : $total;
$sisaTagihan = max(0, $total - $dpEfektif);
$cicilanPerBulan = $tipePembayaran === 'cicilan' && $tenorBulan >= 2 ? round($sisaTagihan / $tenorBulan, 2) : 0;

$updateParts = ['subtotal = ?', 'total = ?'];
$updateParams = [$subtotal, $total];
if ($hasSisaTagihan) {
    $updateParts[] = 'sisa_tagihan = ?';
    $updateParams[] = $sisaTagihan;
}
if ($hasCicilanBulanan) {
    $updateParts[] = 'cicilan_per_bulan = ?';
    $updateParams[] = $cicilanPerBulan;
}
$updateParams[] = $invoice_id;
db_run("UPDATE invoice SET " . implode(', ', $updateParts) . " WHERE id = ?", $updateParams);

$tindakanList = tindakan_options();
$cicilanRows = $hasInvoiceCicilan
    ? db_fetch_all("SELECT * FROM invoice_cicilan WHERE invoice_id = ? ORDER BY angsuran_ke ASC", [$invoice_id])
    : [];

function inv_item_name($row) {
    return $row['nama_tindakan'] ?? $row['nama_item'] ?? '-';
}

function inv_item_tooth($row) {
    return $row['tooth_number'] ?? $row['nomor_gigi'] ?? '';
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Billing & Invoice</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1250px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:20px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);margin-bottom:18px}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.grid{display:grid;grid-template-columns:1.3fr .7fr .7fr .7fr auto;gap:12px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
input,select,textarea,button{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px}
button,.btn{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;border:none;font-weight:700;cursor:pointer;padding:12px 16px;border-radius:12px}
.btn.secondary{background:#475569}
.btn.green{background:#166534}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#e2e8f0;font-size:12px}
.right{text-align:right}
.small{font-size:13px;color:#64748b}
.summary{max-width:520px;margin-left:auto}
.summary table{width:100%;border-collapse:collapse}
.summary td{padding:10px 8px;border-bottom:1px solid #e2e8f0}
.summary tr:last-child td{font-size:18px;font-weight:800;border-top:2px solid #111827}
.info-box{padding:14px 16px;border-radius:16px;background:#eff6ff;border:1px solid #bfdbfe;color:#1e3a8a}
.hidden{display:none}
@media(max-width:1000px){.grid,.grid2,.grid3{grid-template-columns:1fr}}
</style>
<script>
function isiMasterTindakan(sel){
    const opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) return;
    document.getElementById('treatment_id').value = opt.value || 0;
    document.getElementById('nama_tindakan').value = opt.dataset.nama || '';
    document.getElementById('harga').value = opt.dataset.harga || 0;
    hitungSubtotal();
}
function hitungSubtotal(){
    const qty = parseFloat(document.getElementById('qty').value || 0);
    const harga = parseFloat(document.getElementById('harga').value || 0);
    document.getElementById('subtotal_preview').value = (qty * harga).toFixed(2);
}
function updateSimulasiCicilan(){
    const total = parseFloat(document.getElementById('summary_total_value').dataset.total || '0');
    const tipe = document.getElementById('tipe_pembayaran');
    const dp = parseFloat(document.getElementById('dp')?.value || '0');
    const tenor = parseInt(document.getElementById('tenor_bulan')?.value || '2', 10);
    const box = document.getElementById('box_cicilan');
    const sisaEl = document.getElementById('simulasi_sisa');
    const bulanEl = document.getElementById('simulasi_bulanan');

    if (!tipe || !box || !sisaEl || !bulanEl) return;
    const isCicilan = tipe.value === 'cicilan';
    box.classList.toggle('hidden', !isCicilan);

    let sisa = isCicilan ? Math.max(0, total - Math.max(0, dp)) : 0;
    let bulanan = isCicilan ? (tenor >= 2 ? sisa / tenor : sisa) : 0;

    sisaEl.textContent = formatRupiah(sisa);
    bulanEl.textContent = formatRupiah(bulanan);
}
function formatRupiah(n){
    return 'Rp ' + Number(n || 0).toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0});
}
window.addEventListener('DOMContentLoaded', function(){
    hitungSubtotal();
    updateSimulasiCicilan();
    document.getElementById('tipe_pembayaran')?.addEventListener('change', updateSimulasiCicilan);
    document.getElementById('dp')?.addEventListener('input', updateSimulasiCicilan);
    document.getElementById('tenor_bulan')?.addEventListener('input', updateSimulasiCicilan);
});
</script>
</head>
<body>
<div class="wrap">

    <div class="row" style="margin-bottom:16px">
        <div>
            <h1 style="margin:0">Billing & Invoice</h1>
            <div class="small">No. Invoice: <?= e($invoice['no_invoice'] ?? '-') ?></div>
        </div>
        <div class="row">
            <a class="btn secondary" href="kunjungan.php?pasien_id=<?= (int)$pasien_id ?>">Kembali Kunjungan</a>
            <a class="btn" href="invoice_pdf.php?id=<?= (int)$invoice_id ?>" target="_blank">Print / PDF</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>
        <div class="grid2">
            <div>
                <strong>Pasien</strong><br>
                <?= e($pasien['no_rm'] ?? '') ?> - <?= e($pasien['nama'] ?? '-') ?>
            </div>
            <div>
                <strong>Kunjungan</strong><br>
                <?= e($kunjungan['tanggal'] ?? '-') ?>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Tambah Item Manual</h2>
        <form method="post" action="simpan_invoice.php">
            <input type="hidden" name="invoice_id" value="<?= (int)$invoice_id ?>">
            <input type="hidden" name="treatment_id" id="treatment_id" value="0">
            <div style="margin-bottom:12px">
                <label>Ambil dari Master Tindakan</label>
                <select onchange="isiMasterTindakan(this)">
                    <option value="">Pilih tindakan...</option>
                    <?php foreach ($tindakanList as $t): ?>
                        <?php $namaT = $t['nama_tindakan'] ?? $t['nama'] ?? ''; ?>
                        <option value="<?= (int)($t['id'] ?? 0) ?>"
                                data-nama="<?= e($namaT) ?>"
                                data-harga="<?= e((float)($t['harga'] ?? 0)) ?>">
                            <?= e($namaT) ?><?= !empty($t['kategori']) ? ' - ' . e($t['kategori']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid">
                <div>
                    <label>Nama Tindakan</label>
                    <input type="text" id="nama_tindakan" name="nama_tindakan" required>
                </div>
                <div>
                    <label>Qty</label>
                    <input type="number" step="0.01" id="qty" name="qty" value="1" oninput="hitungSubtotal()">
                </div>
                <div>
                    <label>Harga</label>
                    <input type="number" step="0.01" id="harga" name="harga" value="0" oninput="hitungSubtotal()">
                </div>
                <div>
                    <label>Preview Subtotal</label>
                    <input type="number" step="0.01" id="subtotal_preview" readonly value="0">
                </div>
                <div style="align-self:end">
                    <button type="submit" name="tambah_item" value="1">Tambah</button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Item Invoice</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tindakan</th>
                        <th>Gigi</th>
                        <th>Qty</th>
                        <th>Harga</th>
                        <th>Subtotal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e(inv_item_name($it)) ?></td>
                        <td><span class="badge"><?= e(inv_item_tooth($it)) ?></span></td>
                        <td><?= e($it['qty'] ?? 0) ?></td>
                        <td><?= rupiah($it['harga'] ?? 0) ?></td>
                        <td><?= rupiah($it['subtotal'] ?? 0) ?></td>
                        <td>
                            <a class="btn secondary" style="padding:8px 10px" href="invoice.php?edit=<?= (int)$invoice_id ?>&hapus_item=<?= (int)$it['id'] ?>" onclick="return confirm('Hapus item ini?')">Hapus</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$items): ?>
                    <tr><td colspan="6">Belum ada item invoice.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Simpan Invoice</h2>
        <form method="post" action="simpan_invoice.php">
            <input type="hidden" name="invoice_id" value="<?= (int)$invoice_id ?>">

            <div class="grid2">
                <div>
                    <label>Diskon</label>
                    <input type="number" step="0.01" name="diskon" value="<?= e($diskon) ?>">
                </div>
                <div>
                    <label>Status Bayar</label>
                    <select name="status_bayar">
                        <option value="belum terbayar" <?= (($invoice['status_bayar'] ?? '') === 'belum terbayar') ? 'selected' : '' ?>>belum terbayar</option>
                        <option value="pending" <?= (($invoice['status_bayar'] ?? '') === 'pending') ? 'selected' : '' ?>>pending</option>
                        <option value="lunas" <?= (($invoice['status_bayar'] ?? '') === 'lunas') ? 'selected' : '' ?>>lunas</option>
                        <?php if ($hasTipePembayaran): ?>
                        <option value="cicilan" <?= (($invoice['status_bayar'] ?? '') === 'cicilan') ? 'selected' : '' ?>>cicilan</option>
                        <?php endif; ?>
                    </select>
                </div>
                <div>
                    <label>Metode Bayar</label>
                    <select name="metode_bayar">
                        <option value="tunai" <?= (($invoice['metode_bayar'] ?? '') === 'tunai') ? 'selected' : '' ?>>tunai</option>
                        <option value="transfer" <?= (($invoice['metode_bayar'] ?? '') === 'transfer') ? 'selected' : '' ?>>transfer</option>
                        <option value="debit" <?= (($invoice['metode_bayar'] ?? '') === 'debit') ? 'selected' : '' ?>>debit</option>
                        <option value="kartu kredit" <?= (($invoice['metode_bayar'] ?? '') === 'kartu kredit') ? 'selected' : '' ?>>kartu kredit</option>
                        <option value="qris" <?= (($invoice['metode_bayar'] ?? '') === 'qris') ? 'selected' : '' ?>>qris</option>
                    </select>
                </div>
                <div>
                    <label>Tipe Pembayaran</label>
                    <select name="tipe_pembayaran" id="tipe_pembayaran">
                        <option value="tunai" <?= $tipePembayaran === 'tunai' ? 'selected' : '' ?>>tunai</option>
                        <option value="cicilan" <?= $tipePembayaran === 'cicilan' ? 'selected' : '' ?>>cicilan</option>
                    </select>
                </div>
            </div>

            <div id="box_cicilan" class="card <?= $tipePembayaran === 'cicilan' ? '' : 'hidden' ?>" style="margin-top:16px;background:#f8fafc;border:1px solid #e2e8f0;box-shadow:none;">
                <h3 style="margin-top:0">Pengaturan Cicilan</h3>
                <div class="grid3">
                    <div>
                        <label>DP</label>
                        <input type="number" step="0.01" min="0" id="dp" name="dp" value="<?= e($dp) ?>">
                    </div>
                    <div>
                        <label>Tenor (2 - 12 bulan)</label>
                        <input type="number" min="2" max="12" id="tenor_bulan" name="tenor_bulan" value="<?= e($tenorBulan) ?>">
                    </div>
                    <div>
                        <label>Tanggal Mulai Cicilan</label>
                        <input type="date" name="tanggal_mulai_cicilan" value="<?= e(date('Y-m-d', strtotime((string)($invoice['tanggal'] ?? date('Y-m-d'))))) ?>">
                    </div>
                </div>
                <div class="info-box" style="margin-top:16px;">
                    Estimasi sisa tagihan: <strong id="simulasi_sisa"><?= rupiah($sisaTagihan) ?></strong><br>
                    Estimasi cicilan per bulan: <strong id="simulasi_bulanan"><?= rupiah($cicilanPerBulan) ?></strong>
                </div>
            </div>

            <div style="margin-top:16px">
                <label>Catatan</label>
                <textarea name="catatan" rows="3"><?= e($invoice['catatan'] ?? '') ?></textarea>
            </div>

            <div class="summary" style="margin-top:18px">
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
                        <td class="right" id="summary_total_value" data-total="<?= e($total) ?>"><?= rupiah($total) ?></td>
                    </tr>
                    <tr>
                        <td>DP / Pembayaran Awal</td>
                        <td class="right"><?= rupiah($dpEfektif) ?></td>
                    </tr>
                    <tr>
                        <td>Sisa Tagihan</td>
                        <td class="right"><?= rupiah($sisaTagihan) ?></td>
                    </tr>
                    <tr>
                        <td>Cicilan / Bulan</td>
                        <td class="right"><?= rupiah($cicilanPerBulan) ?></td>
                    </tr>
                </table>
            </div>

            <div class="row" style="margin-top:18px">
                <button type="submit" name="simpan_invoice" value="1" style="width:auto">Simpan Invoice</button>
                <button type="submit" name="selesai_dashboard" value="1" class="btn green" style="width:auto">Selesai & Kembali Dashboard</button>
            </div>
        </form>
    </div>

    <?php if ($cicilanRows): ?>
    <div class="card">
        <h2 style="margin-top:0">Jadwal Cicilan</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Angsuran</th>
                        <th>Jatuh Tempo</th>
                        <th>Nominal</th>
                        <th>Status</th>
                        <th>Tanggal Bayar</th>
                        <th>Metode</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cicilanRows as $c): ?>
                    <tr>
                        <td>#<?= (int)($c['angsuran_ke'] ?? 0) ?></td>
                        <td><?= e($c['tanggal_jatuh_tempo'] ?? '') ?></td>
                        <td><?= rupiah($c['nominal'] ?? 0) ?></td>
                        <td><span class="badge"><?= e($c['status'] ?? 'belum_bayar') ?></span></td>
                        <td><?= e($c['tanggal_bayar'] ?? '-') ?></td>
                        <td><?= e($c['metode_bayar'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
