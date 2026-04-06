<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$pasienId = (int)($_GET['pasien_id'] ?? 0);
$kunjunganId = (int)($_GET['kunjungan_id'] ?? 0);

$pasien = $pasienId ? db_fetch_one("SELECT * FROM pasien WHERE id=?", [$pasienId]) : null;
$kunjungan = $kunjunganId ? db_fetch_one("SELECT * FROM kunjungan WHERE id=?", [$kunjunganId]) : null;
$tindakanList = tindakan_options();

$existing = [];
if ($kunjunganId > 0) {
    $existing = db_fetch_all(
        "SELECT * FROM odontogram_tindakan WHERE kunjungan_id=? ORDER BY id DESC",
        [$kunjunganId]
    );
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Odontogram Pro</title>
<style>
*{box-sizing:border-box;font-family:Inter,Arial,sans-serif}
body{margin:0;background:#f8fbff;color:#0f172a}
.wrap{max-width:1380px;margin:0 auto;padding:24px}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:24px;padding:22px;box-shadow:0 14px 30px rgba(15,23,42,.06);margin-bottom:18px}
.row{display:flex;gap:12px;flex-wrap:wrap;justify-content:space-between;align-items:flex-start}
.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.tooth{background:#eff6ff;color:#1d4ed8;padding:10px;border-radius:14px;text-align:center;cursor:pointer;font-weight:700}
.tooth:hover{background:#dbeafe}
input,select,textarea,button{width:100%;padding:13px 14px;border:1px solid #cbd5e1;border-radius:14px}
.btn,button{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;text-align:center;border:none;font-weight:700;cursor:pointer}
.btn.secondary{background:#475569}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}
.table-wrap{overflow:auto}
.muted{color:#64748b;font-size:13px}
.notice{margin-top:8px;color:#92400e;background:#fff7ed;border:1px solid #fdba74;padding:10px 12px;border-radius:12px}
@media(max-width:860px){.grid,.grid2{grid-template-columns:1fr 1fr}}
@media(max-width:560px){.grid,.grid2{grid-template-columns:1fr}}
</style>
<script>
function setGigi(n){
    document.getElementById('nomor_gigi').value = n;
}
function setHarga(sel){
    const opt = sel.options[sel.selectedIndex];
    const harga = opt.dataset.harga || 0;
    const nama = opt.dataset.nama || '';
    const satuan = opt.dataset.satuan || 'per tindakan';
    document.getElementById('harga').value = harga;
    document.getElementById('nama_tindakan').value = nama;
    document.getElementById('satuan_harga').value = satuan;
    hitung();
}
function hitung(){
    const q = parseFloat(document.getElementById('qty').value || 0);
    const h = parseFloat(document.getElementById('harga').value || 0);
    document.getElementById('subtotal').value = (q * h).toFixed(2);
}
window.addEventListener('DOMContentLoaded', function(){
    hitung();
});
</script>
</head>
<body>
<div class="wrap">
    <div class="row">
        <div>
            <h1 style="margin:0 0 8px">Odontogram Pro</h1>
            <div class="muted">
                <?= $pasien ? e($pasien['no_rm']) . ' - ' . e($pasien['nama']) : 'Pilih pasien dan kunjungan terlebih dahulu' ?>
            </div>
            <?php if (!$pasienId || !$kunjunganId): ?>
                <div class="notice">Odontogram sebaiknya dibuka dari halaman kunjungan agar pasien dan kunjungan langsung terisi.</div>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn secondary" href="dashboard.php">Dashboard</a>
            <a class="btn secondary" href="kunjungan.php<?= $pasienId ? '?pasien_id='.(int)$pasienId : '' ?>">Kunjungan</a>
            <a class="btn" href="invoice.php?pasien_id=<?= (int)$pasienId ?>&kunjungan_id=<?= (int)$kunjunganId ?>">Billing</a>
        </div>
    </div>

    <div class="card">
        <?php flash_message(); ?>
        <form method="post" action="simpan_odontogram.php">
            <div class="grid2">
                <div>
                    <label>Pasien ID</label>
                    <input type="number" name="pasien_id" value="<?= (int)$pasienId ?>" required>
                </div>
                <div>
                    <label>Kunjungan ID</label>
                    <input type="number" name="kunjungan_id" value="<?= (int)$kunjunganId ?>" required>
                </div>
            </div>

            <div style="margin:16px 0">
                <div class="muted" style="margin-bottom:8px">Pilih nomor gigi cepat</div>
                <div class="grid">
                    <?php foreach([18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28,48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38] as $g): ?>
                        <div class="tooth" onclick="setGigi('<?= $g ?>')"><?= $g ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid2">
                <div>
                    <label>Nomor Gigi</label>
                    <input type="text" id="nomor_gigi" name="nomor_gigi" required>
                </div>
                <div>
                    <label>Surface</label>
                    <input type="text" name="surface_code" placeholder="O / M / D / B / L / I">
                </div>
                <div>
                    <label>Tindakan</label>
                    <select name="tindakan_id" onchange="setHarga(this)" required>
                        <option value="">Pilih tindakan</option>
                        <?php foreach ($tindakanList as $t): 
                            $namaTindakan = $t['nama_tindakan'] ?? $t['nama'] ?? 'Tindakan';
                            $harga = (float)($t['harga'] ?? 0);
                            $satuan = $t['satuan_harga'] ?? 'per tindakan';
                            $kategori = $t['kategori'] ?? '';
                        ?>
                            <option
                                value="<?= (int)($t['id'] ?? 0) ?>"
                                data-harga="<?= e($harga) ?>"
                                data-nama="<?= e($namaTindakan) ?>"
                                data-satuan="<?= e($satuan) ?>"
                            >
                                <?= e($namaTindakan) ?><?= $kategori ? ' - ' . e($kategori) : '' ?> • <?= e(rupiah($harga)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Nama Tindakan</label>
                    <input type="text" id="nama_tindakan" name="nama_tindakan" required>
                </div>
                <div>
                    <label>Harga</label>
                    <input type="number" step="0.01" id="harga" name="harga" oninput="hitung()" required>
                </div>
                <div>
                    <label>Qty</label>
                    <input type="number" step="0.01" id="qty" name="qty" value="1" oninput="hitung()">
                </div>
                <div>
                    <label>Subtotal</label>
                    <input type="number" step="0.01" id="subtotal" name="subtotal" readonly>
                </div>
                <div>
                    <label>Satuan Harga</label>
                    <input type="text" id="satuan_harga" name="satuan_harga" value="per tindakan">
                </div>
                <div style="grid-column:1/-1">
                    <label>Catatan</label>
                    <textarea name="catatan" rows="2"></textarea>
                </div>
            </div>

            <div style="margin-top:16px">
                <button type="submit" style="width:auto;padding:13px 18px">Simpan ke Odontogram & Billing</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Riwayat Odontogram Kunjungan</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Gigi</th>
                        <th>Surface</th>
                        <th>Tindakan</th>
                        <th>Harga</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existing as $o): ?>
                        <tr>
                            <td><?= e($o['nomor_gigi'] ?? '') ?></td>
                            <td><?= e($o['surface_code'] ?? '') ?></td>
                            <td><?= e($o['nama_tindakan'] ?? '') ?></td>
                            <td><?= e(rupiah($o['harga'] ?? 0)) ?></td>
                            <td><?= e($o['qty'] ?? 0) ?></td>
                            <td><?= e(rupiah($o['subtotal'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$existing): ?>
                        <tr>
                            <td colspan="6">Belum ada tindakan odontogram untuk kunjungan ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
