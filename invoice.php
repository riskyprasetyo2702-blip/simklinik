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

        $editItems = db_fetch_all("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id ASC", [$editId]);
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
         LIMIT 200"
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
        * { box-sizing: border-box; font-family: Inter, Arial, sans-serif; }
        body {
            margin: 0;
            background: linear-gradient(180deg, #f8fbff 0%, #eef5fb 100%);
            color: #0f172a;
        }
        .wrap {
            max-width: 1450px;
            margin: 0 auto;
            padding: 24px;
        }
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            padding: 22px;
            box-shadow: 0 14px 30px rgba(15, 23, 42, .06);
            margin-bottom: 18px;
        }
        .head, .row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: flex-start;
            flex-wrap: wrap;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .full { grid-column: 1 / -1; }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 700;
            color: #334155;
        }
        input, select, textarea, button {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            font-size: 14px;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, .12);
        }
        .btn, button {
            background: #0f172a;
            color: #fff;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            border: none;
            font-weight: 700;
            cursor: pointer;
        }
        .btn.secondary { background: #475569; }
        .btn.blue { background: #2563eb; }
        .btn.green { background: #059669; }
        .btn.red { background: #dc2626; }
        .table-wrap { overflow: auto; }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            white-space: nowrap;
        }
        .table th {
            background: #f8fafc;
            color: #334155;
            text-align: left;
            font-size: 14px;
        }
        .item-row {
            display: grid;
            grid-template-columns: 2fr .9fr 1fr 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 12px;
            align-items: start;
            padding: 14px;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            background: #fcfdff;
        }
        .small { color: #64748b; font-size: 13px; }
        .badge {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .lunas { background: #dcfce7; color: #166534; }
        .pending { background: #fef3c7; color: #92400e; }
        .belum { background: #fee2e2; color: #991b1b; }
        .section-title {
            margin: 0 0 8px;
            font-size: 22px;
        }
        .qris-box {
            padding: 16px;
            border: 1px dashed #94a3b8;
            border-radius: 18px;
            background: #f8fafc;
        }
        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .summary-box {
            background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
            border: 1px solid #dbeafe;
            border-radius: 18px;
            padding: 16px;
        }
        .odonto-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            max-width: 920px;
        }
        .tooth-btn {
            width: 48px;
            height: 48px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #fff;
            cursor: pointer;
            font-weight: 700;
            transition: .15s ease;
        }
        .tooth-btn:hover {
            background: #dbeafe;
            border-color: #93c5fd;
        }
        @media (max-width: 980px) {
            .grid, .item-row {
                grid-template-columns: 1fr;
            }
            .table th, .table td {
                white-space: normal;
            }
        }
    </style>

    <script>
        const tindakanHarga = {
            <?php foreach ($tindakanList as $t): ?>
            <?= json_encode((string)$t['id']) ?>: <?= json_encode((float)$t['harga']) ?>,
            <?php endforeach; ?>
        };

        const tindakanNama = {
            <?php foreach ($tindakanList as $t): ?>
            <?= json_encode((string)$t['id']) ?>: <?= json_encode((string)$t['nama_tindakan']) ?>,
            <?php endforeach; ?>
        };

        function n(v) {
            return parseFloat(v || 0) || 0;
        }

        function hitungTotal() {
            let subtotal = 0;

            document.querySelectorAll('.item-row').forEach(function(row) {
                let qtyInput = row.querySelector('.qty');
                let hargaInput = row.querySelector('.harga');
                let subtotalInput = row.querySelector('.subtotal');

                let qty = n(qtyInput ? qtyInput.value : 0);
                let harga = n(hargaInput ? hargaInput.value : 0);
                let st = qty * harga;

                if (subtotalInput) subtotalInput.value = st.toFixed(2);
                subtotal += st;
            });

            let subtotalField = document.getElementById('subtotal');
            let diskonField = document.getElementById('diskon');
            let totalField = document.getElementById('total');

            if (subtotalField) subtotalField.value = subtotal.toFixed(2);

            let diskon = n(diskonField ? diskonField.value : 0);
            let total = subtotal - diskon;
            if (total < 0) total = 0;

            if (totalField) totalField.value = total.toFixed(2);
        }

        function tambahItem(nama = '', qty = '1', harga = '0', subtotal = '0', ket = '', gigi = '', tid = '0') {
            const wrap = document.getElementById('items-wrap');
            const div = document.createElement('div');
            div.className = 'item-row';

            div.innerHTML = `
                <select class="tindakan_select" onchange="pilihTindakan(this)">
                    <option value="">Katalog tindakan</option>
                    <?php foreach ($tindakanList as $t): ?>
                        <option value="<?= (int)$t['id'] ?>">
                            <?= e($t['nama_tindakan']) ?> • <?= e(rupiah($t['harga'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="nama_item[]" placeholder="Nama tindakan / item" value="${nama}">
                <input type="number" step="0.01" class="qty" name="qty[]" value="${qty}" oninput="hitungTotal()">
                <input type="number" step="0.01" class="harga" name="harga[]" value="${harga}" oninput="hitungTotal()">
                <input type="number" step="0.01" class="subtotal" name="subtotal_item[]" value="${subtotal}" readonly>
                <button type="button" onclick="this.parentElement.remove();hitungTotal()" style="background:#dc2626">Hapus</button>

                <input type="hidden" name="tindakan_id[]" value="${tid}">
                <input type="text" name="nomor_gigi[]" placeholder="Nomor gigi" value="${gigi}" style="grid-column:1/2">
                <input type="text" name="keterangan_item[]" placeholder="Keterangan item" value="${ket}" style="grid-column:2/-1">
            `;

            wrap.appendChild(div);
            hitungTotal();
        }

        function pilihTindakan(sel) {
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

        function pilihGigi(no) {
            let tindakan = prompt("Masukkan tindakan untuk gigi " + no + "\\nContoh: Tambal, Cabut, Scaling");
            if (!tindakan || tindakan.trim() === '') return;

            tambahItemOdontogram(no, tindakan.trim());
        }

        function tambahItemOdontogram(no, tindakan) {
            const wrap = document.getElementById('items-wrap');
            if (!wrap) {
                alert('Bagian item invoice tidak ditemukan.');
                return;
            }

            const div = document.createElement('div');
            div.className = 'item-row';

            div.innerHTML = `
                <select class="tindakan_select" onchange="pilihTindakan(this)">
                    <option value="">Katalog tindakan</option>
                    <?php foreach ($tindakanList as $t): ?>
                        <option value="<?= (int)$t['id'] ?>">
                            <?= e($t['nama_tindakan']) ?> • <?= e(rupiah($t['harga'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="text" name="nama_item[]" value="${tindakan}" placeholder="Nama tindakan / item">
                <input type="number" step="0.01" class="qty" name="qty[]" value="1" oninput="hitungTotal()">
                <input type="number" step="0.01" class="harga" name="harga[]" value="0" oninput="hitungTotal()">
                <input type="number" step="0.01" class="subtotal" name="subtotal_item[]" value="0" readonly>
                <button type="button" onclick="this.parentElement.remove();hitungTotal()" style="background:#dc2626">Hapus</button>

                <input type="hidden" name="tindakan_id[]" value="0">
                <input type="text" name="nomor_gigi[]" value="${no}" placeholder="Nomor gigi" style="grid-column:1/2">
                <input type="text" name="keterangan_item[]" value="Odontogram gigi ${no}" placeholder="Keterangan item" style="grid-column:2/-1">
            `;

            wrap.appendChild(div);
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

    <div class="head">
        <div>
            <h1 class="section-title">Billing & Invoice</h1>
            <div class="small">
                <?= $pasien ? 'Pasien: ' . e($pasien['no_rm']) . ' - ' . e($pasien['nama']) : 'Seluruh invoice pasien' ?>
            </div>
        </div>

        <div class="toolbar">
            <a class="btn secondary" href="dashboard.php" style="padding:13px 18px">Dashboard</a>
            <a class="btn" href="pasien.php" style="padding:13px 18px">Data Pasien</a>
            <a class="btn secondary" href="keuangan.php" style="padding:13px 18px">Keuangan</a>
            <a class="btn secondary" href="laporan_keuangan.php" style="padding:13px 18px">Laporan</a>
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
                    <input
                        type="datetime-local"
                        name="tanggal"
                        required
                        value="<?= e(isset($editData['tanggal']) ? date('Y-m-d\TH:i', strtotime($editData['tanggal'])) : date('Y-m-d\TH:i')) ?>"
                    >
                </div>

                <div>
                    <label>Pasien</label>
                    <select name="pasien_id" required onchange="window.location='invoice.php?pasien_id='+this.value">
                        <option value="">Pilih pasien</option>
                        <?php foreach ($pasienList as $p): ?>
                            <option
                                value="<?= (int)$p['id'] ?>"
                                <?= ((int)($editData['pasien_id'] ?? $pasienId) === (int)$p['id']) ? 'selected' : '' ?>
                            >
                                <?= e($p['no_rm']) ?> - <?= e($p['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Kunjungan</label>
                    <select name="kunjungan_id">
                        <option value="">Tanpa kunjungan</option>
                        <?php foreach ($kunjunganList as $k): ?>
                            <option
                                value="<?= (int)$k['id'] ?>"
                                <?= ((int)($editData['kunjungan_id'] ?? $kunjunganId) === (int)$k['id']) ? 'selected' : '' ?>
                            >
                                <?= e($k['tanggal']) ?> - <?= e($k['diagnosa'] ?: $k['tindakan']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
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

                <div>
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

            <div class="card" style="margin-top:16px;background:#f8fbff">
                <div class="row">
                    <h3 style="margin:0">Item Invoice</h3>
                    <div class="toolbar">
                        <button type="button" onclick="tambahItem()" style="width:auto;padding:13px 18px">Tambah Item</button>
                    </div>
                </div>

                <div id="items-wrap" style="margin-top:12px">
                    <?php foreach ($editItems as $it): ?>
                        <?php
                        $itemNama = $it['nama_item'] ?? $it['item'] ?? $it['deskripsi'] ?? $it['nama_tindakan'] ?? $it['tindakan'] ?? '';
                        $itemHarga = $it['harga'] ?? $it['price'] ?? 0;
                        $itemSubtotal = $it['subtotal'] ?? $it['total'] ?? 0;
                        $itemNomorGigi = $it['nomor_gigi'] ?? $it['tooth_number'] ?? '';
                        $itemKet = $it['keterangan'] ?? $it['notes'] ?? '';
                        $itemTindakanId = $it['tindakan_id'] ?? $it['treatment_id'] ?? $it['service_id'] ?? $it['procedure_id'] ?? $it['item_id'] ?? 0;
                        ?>
                        <div class="item-row">
                            <select class="tindakan_select" onchange="pilihTindakan(this)">
                                <option value="">Katalog tindakan</option>
                                <?php foreach ($tindakanList as $t): ?>
                                    <option
                                        value="<?= (int)$t['id'] ?>"
                                        <?= ((int)$itemTindakanId === (int)$t['id']) ? 'selected' : '' ?>
                                    >
                                        <?= e($t['nama_tindakan']) ?> • <?= e(rupiah($t['harga'])) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="text" name="nama_item[]" value="<?= e($itemNama) ?>">
                            <input type="number" step="0.01" class="qty" name="qty[]" value="<?= e($it['qty'] ?? 1) ?>" oninput="hitungTotal()">
                            <input type="number" step="0.01" class="harga" name="harga[]" value="<?= e($itemHarga) ?>" oninput="hitungTotal()">
                            <input type="number" step="0.01" class="subtotal" name="subtotal_item[]" value="<?= e($itemSubtotal) ?>" readonly>
                            <button type="button" onclick="this.parentElement.remove();hitungTotal()" style="background:#dc2626">Hapus</button>

                            <input type="hidden" name="tindakan_id[]" value="<?= (int)$itemTindakanId ?>">
                            <input type="text" name="nomor_gigi[]" placeholder="Nomor gigi" value="<?= e($itemNomorGigi) ?>" style="grid-column:1/2">
                            <input type="text" name="keterangan_item[]" value="<?= e($itemKet) ?>" style="grid-column:2/-1">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card" style="margin-top:16px;background:#f8fbff">
                <div class="row">
                    <h3 style="margin:0">Odontogram Sederhana</h3>
                    <div class="small">Klik nomor gigi untuk menambahkan tindakan ke invoice</div>
                </div>

                <div style="margin-top:16px">
                    <div style="font-weight:700;margin-bottom:8px">Rahang Atas</div>
                    <div class="odonto-grid">
                        <?php
                        $gigiAtas = [18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28];
                        foreach ($gigiAtas as $g):
                        ?>
                            <button type="button" class="tooth-btn" onclick="pilihGigi(<?= $g ?>)"><?= $g ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div style="margin-top:18px">
                    <div style="font-weight:700;margin-bottom:8px">Rahang Bawah</div>
                    <div class="odonto-grid">
                        <?php
                        $gigiBawah = [48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38];
                        foreach ($gigiBawah as $g):
                        ?>
                            <button type="button" class="tooth-btn" onclick="pilihGigi(<?= $g ?>)"><?= $g ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="grid">
                <div class="summary-box">
                    <label>Subtotal</label>
                    <input type="number" step="0.01" id="subtotal" name="subtotal" readonly value="<?= e($editData['subtotal'] ?? '0') ?>">
                </div>

                <div class="summary-box">
                    <label>Diskon</label>
                    <input type="number" step="0.01" id="diskon" name="diskon" value="<?= e($editData['diskon'] ?? '0') ?>" oninput="hitungTotal()">
                </div>

                <div class="summary-box">
                    <label>Total</label>
                    <input type="number" step="0.01" id="total" name="total" readonly value="<?= e($editData['total'] ?? '0') ?>">
                </div>

                <div class="qris-box">
                    <strong>QRIS</strong>
                    <div class="small" style="margin-top:8px">
                        Metode QRIS selalu tersedia. Jika klinik punya gambar QRIS statis, isi <code>QRIS_IMAGE_URL</code> di <code>bootstrap.php</code>.
                    </div>

                    <?php if (defined('QRIS_IMAGE_URL') && QRIS_IMAGE_URL): ?>
                        <div style="margin-top:10px">
                            <img src="<?= e(QRIS_IMAGE_URL) ?>" alt="QRIS" style="max-width:180px;border-radius:16px">
                        </div>
                    <?php else: ?>
                        <div style="margin-top:10px;padding:12px;border-radius:12px;background:#fff;border:1px dashed #cbd5e1">
                            QRIS siap dipakai. Tambahkan URL gambar QRIS di bootstrap.php untuk menampilkan kode QR.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="full">
                    <label>Catatan</label>
                    <textarea name="catatan" rows="3"><?= e($editData['catatan'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="toolbar" style="margin-top:16px">
                <button type="submit" style="width:auto;padding:13px 18px">Simpan Invoice</button>

                <?php if ($editData): ?>
                    <a class="btn blue" style="padding:13px 18px" href="invoice_pdf.php?id=<?= (int)$editData['id'] ?>" target="_blank">
                        Print / Save PDF
                    </a>
                <?php endif; ?>

                <?php if ($pasienId > 0): ?>
                    <a class="btn green" style="padding:13px 18px" href="riwayat_transaksi_pasien.php?pasien_id=<?= (int)$pasienId ?>">
                        Riwayat Transaksi Pasien
                    </a>
                <?php endif; ?>

                <a class="btn secondary" style="padding:13px 18px" href="dashboard.php">Kembali Dashboard</a>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Riwayat Invoice</h2>

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
                            <td><?= e($inv['no_invoice']) ?></td>
                            <td><?= e($inv['tanggal']) ?></td>
                            <td>
                                <strong><?= e($inv['no_rm']) ?></strong>
                                <div class="small"><?= e($inv['nama']) ?></div>
                            </td>
                            <td><?= e(rupiah($inv['total'])) ?></td>
                            <td>
                                <span class="badge <?= e($cls) ?>"><?= e($inv['status_bayar']) ?></span>
                            </td>
                            <td><?= e(strtoupper($inv['metode_bayar'])) ?></td>
                            <td>
                                <div class="toolbar">
                                    <a class="btn" style="padding:9px 12px" href="invoice.php?edit=<?= (int)$inv['id'] ?>">Edit</a>
                                    <a class="btn secondary" style="padding:9px 12px" href="invoice_pdf.php?id=<?= (int)$inv['id'] ?>" target="_blank">Print</a>
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
