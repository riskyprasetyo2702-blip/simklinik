<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$pasienId    = (int)($_GET['pasien_id'] ?? 0);
$kunjunganId = (int)($_GET['kunjungan_id'] ?? 0);
$editId      = (int)($_GET['edit'] ?? 0);

$pasien    = $pasienId ? db_fetch_one("SELECT * FROM pasien WHERE id=?", [$pasienId]) : null;
$kunjungan = $kunjunganId ? db_fetch_one("SELECT * FROM kunjungan WHERE id=?", [$kunjunganId]) : null;

$editData  = null;
$editItems = [];

if ($editId > 0) {
    $editData = db_fetch_one("SELECT * FROM invoice WHERE id=?", [$editId]);

    if ($editData) {
        $pasienId    = (int)($editData['pasien_id'] ?? 0);
        $kunjunganId = (int)($editData['kunjungan_id'] ?? 0);

        $pasien    = $pasienId ? db_fetch_one("SELECT * FROM pasien WHERE id=?", [$pasienId]) : null;
        $kunjungan = $kunjunganId ? db_fetch_one("SELECT * FROM kunjungan WHERE id=?", [$kunjunganId]) : null;

        $editItems = db_fetch_all(
            "SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id ASC",
            [$editId]
        );
    }
}

$pasienList = pasien_options();

$kunjunganList = $pasienId > 0
    ? db_fetch_all(
        "SELECT id, tanggal, diagnosa, tindakan
         FROM kunjungan
         WHERE pasien_id=?
         ORDER BY tanggal DESC",
        [$pasienId]
    )
    : [];

$invoiceList = $pasienId > 0
    ? db_fetch_all(
        "SELECT i.*, p.no_rm, p.nama
         FROM invoice i
         JOIN pasien p ON p.id=i.pasien_id
         WHERE i.pasien_id=?
         ORDER BY i.tanggal DESC",
        [$pasienId]
    )
    : db_fetch_all(
        "SELECT i.*, p.no_rm, p.nama
         FROM invoice i
         JOIN pasien p ON p.id=i.pasien_id
         ORDER BY i.tanggal DESC
         LIMIT 150"
    );

$tindakanList = tindakan_options();
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Billing & Invoice</title>
    <style>
        *{box-sizing:border-box;font-family:Inter,Arial,sans-serif}
        body{
            margin:0;
            background:linear-gradient(180deg,#f7fbff 0%,#edf4fb 100%);
            color:#0f172a;
        }
        .wrap{
            max-width:1480px;
            margin:0 auto;
            padding:26px;
        }
        .topbar{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:16px;
            flex-wrap:wrap;
            margin-bottom:20px;
        }
        .hero-title{
            margin:0;
            font-size:34px;
            font-weight:900;
            letter-spacing:-.03em;
        }
        .hero-sub{
            margin-top:8px;
            color:#64748b;
            font-size:14px;
        }
        .toolbar{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }
        .btn,button{
            border:none;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
            padding:13px 18px;
            border-radius:16px;
            font-weight:800;
            cursor:pointer;
            transition:.15s ease;
        }
        .btn:hover,button:hover{transform:translateY(-1px)}
        .btn-dark,button{background:#0f172a;color:#fff}
        .btn-secondary{background:#475569;color:#fff}
        .btn-blue{background:#2563eb;color:#fff}
        .btn-green{background:#059669;color:#fff}

        .card{
            background:#fff;
            border:1px solid #e2e8f0;
            border-radius:28px;
            padding:22px;
            box-shadow:0 18px 38px rgba(15,23,42,.06);
            margin-bottom:18px;
        }
        .section-title{
            margin:0 0 16px;
            font-size:22px;
            font-weight:900;
            letter-spacing:-.02em;
        }
        .section-sub{
            color:#64748b;
            font-size:13px;
            margin-top:6px;
        }
        .grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:16px;
        }
        .full{grid-column:1/-1}
        .field label{
            display:block;
            margin-bottom:8px;
            font-weight:800;
            font-size:14px;
            color:#334155;
        }
        .field input,
        .field select,
        .field textarea{
            width:100%;
            border:1px solid #cbd5e1;
            border-radius:16px;
            padding:13px 14px;
            font-size:14px;
            background:#fff;
        }
        .field input:focus,
        .field select:focus,
        .field textarea:focus{
            outline:none;
            border-color:#93c5fd;
            box-shadow:0 0 0 .2rem rgba(59,130,246,.12);
        }

        .meta-grid{
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:14px;
            margin-top:18px;
        }
        .meta-box{
            border:1px solid #e2e8f0;
            background:linear-gradient(180deg,#fbfdff 0%,#ffffff 100%);
            border-radius:20px;
            padding:16px;
        }
        .meta-box .label{
            font-size:13px;
            color:#64748b;
            margin-bottom:6px;
            font-weight:700;
        }
        .meta-box .value{
            font-size:18px;
            font-weight:900;
            color:#0f172a;
        }

        .item-card{
            background:#f8fbff;
            border:1px dashed #cbd5e1;
            border-radius:24px;
            padding:18px;
            margin-top:18px;
        }
        .item-header{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
            margin-bottom:14px;
        }
        .item-row{
            display:grid;
            grid-template-columns:2.2fr 1fr 1fr 1fr auto;
            gap:10px;
            align-items:start;
            margin-bottom:12px;
            padding:14px;
            border:1px solid #e2e8f0;
            border-radius:20px;
            background:#ffffff;
        }

        .summary-grid{
            display:grid;
            grid-template-columns:repeat(4,minmax(0,1fr));
            gap:16px;
            margin-top:18px;
        }
        .summary-box{
            border:1px solid #dbeafe;
            background:linear-gradient(180deg,#f8fbff 0%,#ffffff 100%);
            border-radius:20px;
            padding:16px;
        }
        .summary-box .label{
            font-size:13px;
            color:#64748b;
            margin-bottom:6px;
            font-weight:700;
        }
        .summary-box .value{
            font-size:20px;
            font-weight:900;
            color:#0f172a;
        }
        .soft-box{
            background:#f8fbff;
            border:1px dashed #cbd5e1;
            border-radius:20px;
            padding:16px;
        }
        .small{
            color:#64748b;
            font-size:13px;
        }

        .table-wrap{overflow:auto}
        .table{
            width:100%;
            border-collapse:collapse;
        }
        .table th,.table td{
            padding:13px 12px;
            border-bottom:1px solid #e2e8f0;
            text-align:left;
            vertical-align:top;
        }
        .table th{
            background:#f8fafc;
            color:#334155;
            font-size:14px;
        }
        .badge{
            display:inline-block;
            padding:7px 12px;
            border-radius:999px;
            font-size:12px;
            font-weight:800;
        }
        .badge.lunas{background:#dcfce7;color:#166534}
        .badge.pending{background:#fef3c7;color:#92400e}
        .badge.belum{background:#fee2e2;color:#991b1b}
        .actions{
            display:flex;
            gap:8px;
            flex-wrap:wrap;
        }

        @media(max-width:1100px){
            .meta-grid,.summary-grid{grid-template-columns:1fr 1fr}
        }
        @media(max-width:860px){
            .grid,.item-row,.meta-grid,.summary-grid{grid-template-columns:1fr}
        }
    </style>

    <script>
        const tindakanHarga = {
            <?php foreach ($tindakanList as $t): ?>
            <?= json_encode((string)($t['id'] ?? '')) ?>: <?= json_encode((float)($t['harga'] ?? 0)) ?>,
            <?php endforeach; ?>
        };

        const tindakanNama = {
            <?php foreach ($tindakanList as $t): ?>
            <?= json_encode((string)($t['id'] ?? '')) ?>: <?= json_encode((string)($t['nama_tindakan'] ?? $t['nama'] ?? '')) ?>,
            <?php endforeach; ?>
        };

        function n(v){
            return parseFloat(v || 0) || 0;
        }

        function hitungTotal(){
            let subtotal = 0;

            document.querySelectorAll('.item-row').forEach(function(row){
                const qtyInput = row.querySelector('.qty');
                const hargaInput = row.querySelector('.harga');
                const subtotalInput = row.querySelector('.subtotal');

                const qty = n(qtyInput ? qtyInput.value : 0);
                const harga = n(hargaInput ? hargaInput.value : 0);
                const st = qty * harga;

                if (subtotalInput) subtotalInput.value = st.toFixed(2);
                subtotal += st;
            });

            const subtotalField = document.getElementById('subtotal');
            const diskonField = document.getElementById('diskon');
            const totalField = document.getElementById('total');

            if (subtotalField) subtotalField.value = subtotal.toFixed(2);

            const diskon = n(diskonField ? diskonField.value : 0);
            let total = subtotal - diskon;
            if (total < 0) total = 0;

            if (totalField) totalField.value = total.toFixed(2);
        }

        function tambahItem(nama='', qty='1', harga='0', subtotal='0', tid='0'){
            const wrap = document.getElementById('items-wrap');
            const div = document.createElement('div');
            div.className = 'item-row';

            div.innerHTML = `
                <select class="tindakan_select" onchange="pilihTindakan(this)">
                    <option value="">Katalog tindakan</option>
                    <?php foreach ($tindakanList as $t): ?>
                        <option value="<?= (int)($t['id'] ?? 0) ?>">
                            <?= e($t['nama_tindakan'] ?? $t['nama'] ?? '') ?> • <?= e(rupiah($t['harga'] ?? 0)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="nama_item[]" placeholder="Nama tindakan / item" value="${nama}">
                <input type="number" step="0.01" class="qty" name="qty[]" value="${qty}" oninput="hitungTotal()">
                <input type="number" step="0.01" class="harga" name="harga[]" value="${harga}" oninput="hitungTotal()">
                <input type="number" step="0.01" class="subtotal" name="subtotal_item[]" value="${subtotal}" readonly>
                <button type="button" onclick="this.parentElement.remove();hitungTotal()" style="background:#dc2626;padding:12px 14px">Hapus</button>

                <input type="hidden" name="tindakan_id[]" value="${tid}">
            `;

            wrap.appendChild(div);
            hitungTotal();
        }

        function pilihTindakan(sel){
            const row = sel.parentElement;
            const id = sel.value;
            if (!id) return;

            const tindakanIdInput = row.querySelector('input[name="tindakan_id[]"]');
            const namaInput = row.querySelector('input[name="nama_item[]"]');
            const hargaInput = row.querySelector('.harga');

            if (tindakanIdInput) tindakanIdInput.value = id;
            if (namaInput) namaInput.value = tindakanNama[id] || '';
            if (hargaInput) hargaInput.value = tindakanHarga[id] || 0;

            hitungTotal();
        }

        window.addEventListener('DOMContentLoaded', () => {
            if (document.querySelectorAll('.item-row').length === 0) {
                tambahItem();
            }
            hitungTotal();
        });
    </script>
</head>
<body>
<div class="wrap">

    <div class="topbar">
        <div>
            <h1 class="hero-title">Billing & Invoice</h1>
            <div class="hero-sub">
                <?= $pasien ? 'Pasien: ' . e($pasien['no_rm'] ?? '') . ' - ' . e($pasien['nama'] ?? '') : 'Kelola seluruh invoice pasien dengan tampilan billing modern' ?>
            </div>
        </div>

        <div class="toolbar">
            <a class="btn btn-secondary" href="dashboard.php">Dashboard</a>
            <a class="btn btn-dark" href="pasien.php">Data Pasien</a>
            <a class="btn btn-secondary" href="laporan_keuangan.php">Keuangan</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>

        <div class="item-header">
            <div>
                <h2 class="section-title" style="margin:0"><?= $editData ? 'Edit Invoice' : 'Buat Invoice Baru' ?></h2>
                <div class="section-sub">Billing difokuskan ke transaksi, tanpa integrasi odontogram</div>
            </div>
        </div>

        <form method="post" action="simpan_invoice.php">
            <input type="hidden" name="id" value="<?= (int)($editData['id'] ?? 0) ?>">

            <div class="grid">
                <div class="field">
                    <label>No Invoice</label>
                    <input type="text" name="no_invoice" required value="<?= e($editData['no_invoice'] ?? next_invoice_no()) ?>">
                </div>

                <div class="field">
                    <label>Tanggal</label>
                    <input
                        type="datetime-local"
                        name="tanggal"
                        required
                        value="<?= e(isset($editData['tanggal']) ? date('Y-m-d\TH:i', strtotime($editData['tanggal'])) : date('Y-m-d\TH:i')) ?>">
                </div>

                <div class="field">
                    <label>Pasien</label>
                    <select name="pasien_id" required onchange="window.location='invoice.php?pasien_id='+this.value">
                        <option value="">Pilih pasien</option>
                        <?php foreach ($pasienList as $p): ?>
                            <option value="<?= (int)($p['id'] ?? 0) ?>"
                                <?= ((int)($editData['pasien_id'] ?? $pasienId) === (int)($p['id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= e($p['no_rm'] ?? '') ?> - <?= e($p['nama'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Kunjungan</label>
                    <select name="kunjungan_id">
                        <option value="">Tanpa kunjungan</option>
                        <?php foreach ($kunjunganList as $k): ?>
                            <option value="<?= (int)($k['id'] ?? 0) ?>"
                                <?= ((int)($editData['kunjungan_id'] ?? $kunjunganId) === (int)($k['id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= e($k['tanggal'] ?? '') ?> - <?= e(($k['diagnosa'] ?: $k['tindakan']) ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Status Pembayaran</label>
                    <?php $sb = $editData['status_bayar'] ?? 'pending'; ?>
                    <select name="status_bayar">
                        <?php foreach (['lunas', 'pending', 'belum terbayar'] as $s): ?>
                            <option value="<?= e($s) ?>" <?= $sb === $s ? 'selected' : '' ?>>
                                <?= ucfirst($s) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Metode Pembayaran</label>
                    <?php $mb = $editData['metode_bayar'] ?? 'qris'; ?>
                    <select name="metode_bayar">
                        <?php foreach (['qris', 'tunai', 'transfer', 'debit', 'kartu kredit'] as $m): ?>
                            <option value="<?= e($m) ?>" <?= $mb === $m ? 'selected' : '' ?>>
                                <?= strtoupper($m) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="meta-grid">
                <div class="meta-box">
                    <div class="label">Pasien Aktif</div>
                    <div class="value"><?= $pasien ? e($pasien['nama'] ?? '-') : '-' ?></div>
                </div>
                <div class="meta-box">
                    <div class="label">Tanggal Kunjungan</div>
                    <div class="value"><?= $kunjungan ? e($kunjungan['tanggal'] ?? '-') : 'Belum dipilih' ?></div>
                </div>
                <div class="meta-box">
                    <div class="label">Mode Billing</div>
                    <div class="value">Tanpa Odontogram</div>
                </div>
            </div>

            <div class="item-card">
                <div class="item-header">
                    <div>
                        <h3 style="margin:0;font-size:20px;font-weight:900;">Item Invoice</h3>
                        <div class="small">Tambahkan tindakan atau item billing secara manual dari katalog tindakan</div>
                    </div>
                    <button type="button" onclick="tambahItem()" style="width:auto">+ Tambah Item</button>
                </div>

                <div id="items-wrap">
                    <?php foreach ($editItems as $it): ?>
                        <?php
                        $itemNama = $it['nama_item'] ?? $it['item'] ?? $it['deskripsi'] ?? $it['nama_tindakan'] ?? $it['tindakan'] ?? '';
                        $itemHarga = $it['harga'] ?? $it['price'] ?? 0;
                        $itemSubtotal = $it['subtotal'] ?? $it['total'] ?? 0;
                        $itemTindakanId = $it['tindakan_id'] ?? $it['treatment_id'] ?? $it['service_id'] ?? $it['procedure_id'] ?? $it['item_id'] ?? 0;
                        ?>
                        <div class="item-row">
                            <select class="tindakan_select" onchange="pilihTindakan(this)">
                                <option value="">Katalog tindakan</option>
                                <?php foreach ($tindakanList as $t): ?>
                                    <option value="<?= (int)($t['id'] ?? 0) ?>"
                                        <?= ((int)$itemTindakanId === (int)($t['id'] ?? 0)) ? 'selected' : '' ?>>
                                        <?= e($t['nama_tindakan'] ?? $t['nama'] ?? '') ?> • <?= e(rupiah($t['harga'] ?? 0)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="text" name="nama_item[]" value="<?= e($itemNama) ?>" placeholder="Nama tindakan / item">
                            <input type="number" step="0.01" class="qty" name="qty[]" value="<?= e($it['qty'] ?? 1) ?>" oninput="hitungTotal()">
                            <input type="number" step="0.01" class="harga" name="harga[]" value="<?= e($itemHarga) ?>" oninput="hitungTotal()">
                            <input type="number" step="0.01" class="subtotal" name="subtotal_item[]" value="<?= e($itemSubtotal) ?>" readonly>
                            <button type="button" onclick="this.parentElement.remove();hitungTotal()" style="background:#dc2626;padding:12px 14px">Hapus</button>

                            <input type="hidden" name="tindakan_id[]" value="<?= (int)$itemTindakanId ?>">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-box">
                    <div class="label">Subtotal</div>
                    <input type="number" step="0.01" id="subtotal" name="subtotal" readonly value="<?= e($editData['subtotal'] ?? '0') ?>">
                </div>

                <div class="summary-box">
                    <div class="label">Diskon</div>
                    <input type="number" step="0.01" id="diskon" name="diskon" value="<?= e($editData['diskon'] ?? '0') ?>" oninput="hitungTotal()">
                </div>

                <div class="summary-box">
                    <div class="label">Total</div>
                    <input type="number" step="0.01" id="total" name="total" readonly value="<?= e($editData['total'] ?? '0') ?>">
                </div>

                <div class="soft-box">
                    <strong>Pembayaran</strong>
                    <div class="small" style="margin-top:8px">
                        Invoice ini difokuskan untuk billing klinik. Semua elemen odontogram dan nomor gigi sudah dihapus dari halaman ini.
                    </div>
                </div>
            </div>

            <div class="field full" style="margin-top:18px;">
                <label>Catatan</label>
                <textarea name="catatan" rows="3"><?= e($editData['catatan'] ?? '') ?></textarea>
            </div>

            <div class="toolbar" style="margin-top:18px">
                <button type="submit">Simpan Invoice</button>

                <?php if ($editData): ?>
                    <a class="btn btn-blue" href="invoice_pdf.php?id=<?= (int)$editData['id'] ?>" target="_blank">Print / Save PDF</a>
                <?php endif; ?>

                <?php if ($pasienId > 0): ?>
                    <a class="btn btn-green" href="riwayat_transaksi_pasien.php?pasien_id=<?= (int)$pasienId ?>">Riwayat Transaksi</a>
                <?php endif; ?>

                <a class="btn btn-secondary" href="dashboard.php">Kembali Dashboard</a>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="item-header">
            <div>
                <h2 class="section-title" style="margin:0">Riwayat Invoice</h2>
                <div class="section-sub">Daftar invoice terbaru pasien</div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>No Invoice</th>
                        <th>Tanggal</th>
                        <th>Pasien</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Metode</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoiceList as $inv): ?>
                        <?php
                        $st = strtolower((string)($inv['status_bayar'] ?? ''));
                        $cls = $st === 'lunas' ? 'lunas' : ($st === 'pending' ? 'pending' : 'belum');
                        ?>
                        <tr>
                            <td><?= e($inv['no_invoice'] ?? '') ?></td>
                            <td><?= e($inv['tanggal'] ?? '') ?></td>
                            <td>
                                <strong><?= e($inv['no_rm'] ?? '') ?></strong>
                                <div class="small"><?= e($inv['nama'] ?? '') ?></div>
                            </td>
                            <td><?= e(rupiah($inv['total'] ?? 0)) ?></td>
                            <td><span class="badge <?= e($cls) ?>"><?= e($inv['status_bayar'] ?? '') ?></span></td>
                            <td><?= e(strtoupper($inv['metode_bayar'] ?? '')) ?></td>
                            <td>
                                <div class="actions">
                                    <a class="btn btn-dark" style="padding:9px 12px" href="invoice.php?edit=<?= (int)($inv['id'] ?? 0) ?>">Edit</a>
                                    <a class="btn btn-secondary" style="padding:9px 12px" href="invoice_pdf.php?id=<?= (int)($inv['id'] ?? 0) ?>" target="_blank">Print</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (!$invoiceList): ?>
                        <tr>
                            <td colspan="7">Belum ada invoice.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
