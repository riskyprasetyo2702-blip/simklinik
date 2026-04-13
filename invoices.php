<?php
ini_set('display_errors',1);
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

if (!table_exists($conn, 'invoices')) {
    die('Tabel invoices tidak ditemukan.');
}

$pasien_id    = (int)($_GET['pasien_id'] ?? 0);
$kunjungan_id = (int)($_GET['kunjungan_id'] ?? 0);
$edit_id      = (int)($_GET['edit'] ?? 0);

$invoice = null;

if ($edit_id > 0) {
    $invoice = db_fetch_one("SELECT * FROM invoices WHERE id = ?", [$edit_id]);
}

if (!$invoice && $pasien_id > 0 && $kunjungan_id > 0) {
    $invoice = db_fetch_one(
        "SELECT * FROM invoices WHERE pasien_id = ? AND kunjungan_id = ? ORDER BY id DESC LIMIT 1",
        [$pasien_id, $kunjungan_id]
    );

    if (!$invoice) {
        $insertCols = [];
        $insertVals = [];
        $insertParams = [];

        if (column_exists($conn, 'invoices', 'no_invoice')) {
            $insertCols[] = '`no_invoice`';
            $insertVals[] = '?';
            $insertParams[] = next_invoice_no();
        }
        if (column_exists($conn, 'invoices', 'pasien_id')) {
            $insertCols[] = '`pasien_id`';
            $insertVals[] = '?';
            $insertParams[] = $pasien_id;
        }
        if (column_exists($conn, 'invoices', 'kunjungan_id')) {
            $insertCols[] = '`kunjungan_id`';
            $insertVals[] = '?';
            $insertParams[] = $kunjungan_id;
        }
        if (column_exists($conn, 'invoices', 'tanggal')) {
            $insertCols[] = '`tanggal`';
            $insertVals[] = 'NOW()';
        }
        if (column_exists($conn, 'invoices', 'subtotal')) {
            $insertCols[] = '`subtotal`';
            $insertVals[] = '?';
            $insertParams[] = 0;
        }
        if (column_exists($conn, 'invoices', 'diskon')) {
            $insertCols[] = '`diskon`';
            $insertVals[] = '?';
            $insertParams[] = 0;
        }
        if (column_exists($conn, 'invoices', 'total')) {
            $insertCols[] = '`total`';
            $insertVals[] = '?';
            $insertParams[] = 0;
        }
        if (column_exists($conn, 'invoices', 'status_bayar')) {
            $insertCols[] = '`status_bayar`';
            $insertVals[] = '?';
            $insertParams[] = 'belum terbayar';
        }
        if (column_exists($conn, 'invoices', 'metode_bayar')) {
            $insertCols[] = '`metode_bayar`';
            $insertVals[] = '?';
            $insertParams[] = 'tunai';
        }
        if (column_exists($conn, 'invoices', 'catatan')) {
            $insertCols[] = '`catatan`';
            $insertVals[] = '?';
            $insertParams[] = '';
        }
        if (column_exists($conn, 'invoices', 'tipe_pembayaran')) {
            $insertCols[] = '`tipe_pembayaran`';
            $insertVals[] = '?';
            $insertParams[] = 'tunai';
        }
        if (column_exists($conn, 'invoices', 'tenor_bulan')) {
            $insertCols[] = '`tenor_bulan`';
            $insertVals[] = '?';
            $insertParams[] = null;
        }
        if (column_exists($conn, 'invoices', 'dp')) {
            $insertCols[] = '`dp`';
            $insertVals[] = '?';
            $insertParams[] = 0;
        }
        if (column_exists($conn, 'invoices', 'sisa_tagihan')) {
            $insertCols[] = '`sisa_tagihan`';
            $insertVals[] = '?';
            $insertParams[] = 0;
        }
        if (column_exists($conn, 'invoices', 'cicilan_per_bulan')) {
            $insertCols[] = '`cicilan_per_bulan`';
            $insertVals[] = '?';
            $insertParams[] = 0;
        }

        $sql = "INSERT INTO invoices (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $insertVals) . ")";
        $newId = db_insert($sql, $insertParams);

        if ($newId) {
            $invoice = db_fetch_one("SELECT * FROM invoices WHERE id = ?", [$newId]);
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

if (isset($_GET['hapus_item']) && table_exists($conn, 'invoice_items')) {
    $hapusId = (int)$_GET['hapus_item'];
    db_run("DELETE FROM invoice_items WHERE id = ? AND invoice_id = ?", [$hapusId, $invoice_id]);
    $_SESSION['success'] = 'Item invoice dihapus.';
    header('Location: invoices.php?edit=' . $invoice_id);
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
$tenorBulan = (int)($invoice['tenor_bulan'] ?? 0);
$dp = (float)($invoice['dp'] ?? 0);
$dpEfektif = $tipePembayaran === 'cicilan' ? $dp : $total;
$sisaTagihan = max(0, $total - $dpEfektif);
$cicilanPerBulan = ($tipePembayaran === 'cicilan' && $tenorBulan >= 2)
    ? round($sisaTagihan / $tenorBulan, 2)
    : 0;

$updateCols = [];
$updateParams = [];
if (column_exists($conn, 'invoices', 'subtotal')) {
    $updateCols[] = 'subtotal = ?';
    $updateParams[] = $subtotal;
}
if (column_exists($conn, 'invoices', 'total')) {
    $updateCols[] = 'total = ?';
    $updateParams[] = $total;
}
if (column_exists($conn, 'invoices', 'sisa_tagihan')) {
    $updateCols[] = 'sisa_tagihan = ?';
    $updateParams[] = $sisaTagihan;
}
if (column_exists($conn, 'invoices', 'cicilan_per_bulan')) {
    $updateCols[] = 'cicilan_per_bulan = ?';
    $updateParams[] = $cicilanPerBulan;
}
if ($updateCols) {
    $updateParams[] = $invoice_id;
    db_run("UPDATE invoices SET " . implode(', ', $updateCols) . " WHERE id = ?", $updateParams);
    $invoice = db_fetch_one("SELECT * FROM invoices WHERE id = ?", [$invoice_id]) ?: $invoice;
}

$tindakanList = tindakan_options();
$cicilanRows = table_exists($conn, 'invoice_cicilan')
    ? db_fetch_all("SELECT * FROM invoice_cicilan WHERE invoice_id = ? ORDER BY angsuran_ke ASC", [$invoice_id])
    : [];

function inv_item_name(array $row): string {
    return (string)($row['nama_tindakan'] ?? $row['nama_item'] ?? '-');
}

function inv_item_tooth(array $row): string {
    return (string)($row['tooth_number'] ?? $row['nomor_gigi'] ?? '');
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Billing & Invoices</title>
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
    document.getElementById('nama_tindakan').value = opt.dataset.nama || '';
    document.getElementById('harga').value = opt.dataset.harga || 0;
    hitungSubtotal();
}
function hitungSubtotal(){
    const qty = parseFloat(document.getElementById('qty').value || 0);
    const harga = parseFloat(document.getElementById('harga').value || 0);
    document.getElementById('subtotal_preview').value = (qty * harga).toFixed(2);
}
function toggleCicilan(){
    const tipe = document.getElementById('tipe_pembayaran');
    const box = document.getElementById('box_cicilan');
    if (!tipe || !box) return;
    box.classList.toggle('hidden', tipe.value !== 'cicilan');
}
window.addEventListener('DOMContentLoaded', function(){
    toggleCicilan();
    hitungSubtotal();
});
</script>
</head>
<body>
<div class="wrap">

    <div class="row" style="margin-bottom:16px">
        <div>
            <h1 style="margin:0">Billing & Invoices</h1>
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
            <input type="hidden" name="table_mode" value="invoices">

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
                            <a class="btn secondary" style="padding:8px 10px" href="invoices.php?edit=<?= (int)$invoice_id ?>&hapus_item=<?= (int)$it['id'] ?>" onclick="return confirm('Hapus item ini?')">Hapus</a>
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
            <input type="hidden" name="table_mode" value="invoices">

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
                        <option value="cicilan" <?= (($invoice['status_bayar'] ?? '') === 'cicilan') ? 'selected' : '' ?>>cicilan</option>
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
                    <select name="tipe_pembayaran" id="tipe_pembayaran" onchange="toggleCicilan()">
                        <option value="tunai" <?= $tipePembayaran === 'tunai' ? 'selected' : '' ?>>tunai</option>
                        <option value="cicilan" <?= $tipePembayaran === 'cicilan' ? 'selected' : '' ?>>cicilan</option>
                    </select>
                </div>
            </div>

            <div id="box_cicilan" class="card hidden" style="margin-top:16px;background:#f8fafc;border:1px solid #e2e8f0;box-shadow:none;">
                <h3 style="margin-top:0">Pengaturan Cicilan</h3>
                <div class="grid3">
                    <div>
                        <label>DP</label>
                        <input type="number" step="0.01" name="dp" value="<?= e((float)($invoice['dp'] ?? 0)) ?>">
                    </div>
                    <div>
                        <label>Tenor (2 - 12 bulan)</label>
                        <input type="number" min="2" max="12" name="tenor_bulan" value="<?= e($tenorBulan > 0 ? $tenorBulan : 2) ?>">
                    </div>
                    <div>
                        <label>Tanggal Mulai Cicilan</label>
                        <input type="date" name="tanggal_mulai_cicilan" value="<?= e(date('Y-m-d', strtotime((string)($invoice['tanggal'] ?? date('Y-m-d'))))) ?>">
                    </div>
                </div>
                <div class="info-box" style="margin-top:16px;">
                    Estimasi sisa tagihan: <strong><?= rupiah($sisaTagihan) ?></strong><br>
                    Estimasi cicilan per bulan: <strong><?= rupiah($cicilanPerBulan) ?></strong>
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
                        <td class="right"><?= rupiah($total) ?></td>
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
