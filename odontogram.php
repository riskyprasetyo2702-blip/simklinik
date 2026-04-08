<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

$pasien_id    = (int)($_GET['pasien_id'] ?? 0);
$kunjungan_id = (int)($_GET['kunjungan_id'] ?? 0);

$pasien = null;
$kunjungan = null;

if ($pasien_id > 0 && table_exists($conn, 'pasien')) {
    $pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasien_id]);
}

if ($kunjungan_id > 0 && table_exists($conn, 'kunjungan')) {
    $kunjungan = db_fetch_one("SELECT * FROM kunjungan WHERE id = ?", [$kunjungan_id]);
    if ($kunjungan && !$pasien_id) {
        $pasien_id = (int)($kunjungan['pasien_id'] ?? 0);
        if ($pasien_id > 0) {
            $pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasien_id]);
        }
    }
}

$tindakanList = tindakan_options();

$riwayat = [];
if ($kunjungan_id > 0 && table_exists($conn, 'odontogram_tindakan')) {
    $riwayat = db_fetch_all(
        "SELECT * FROM odontogram_tindakan WHERE kunjungan_id = ? ORDER BY id DESC",
        [$kunjungan_id]
    );
}

$nomorGigi = [
    18,17,16,15,14,13,12,11,
    21,22,23,24,25,26,27,28,
    48,47,46,45,44,43,42,41,
    31,32,33,34,35,36,37,38
];
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Odontogram Pro</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:#f4f7fb;color:#0f172a}
.wrap{max-width:1350px;margin:24px auto;padding:0 16px}
.card{background:#fff;border-radius:20px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);margin-bottom:18px}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.full{grid-column:1/-1}
input,select,textarea,button{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px}
button,.btn{background:#0f172a;color:#fff;text-decoration:none;display:inline-block;border:none;font-weight:700;cursor:pointer;padding:12px 16px;border-radius:12px}
.btn.secondary{background:#475569}
.small{font-size:13px;color:#64748b}
.tooth{
    background:#eff6ff;
    color:#1d4ed8;
    border:1px solid #bfdbfe;
    border-radius:14px;
    text-align:center;
    padding:12px 8px;
    font-weight:700;
    cursor:pointer;
    user-select:none;
}
.tooth:hover{background:#dbeafe}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#e2e8f0;font-size:12px}
.info{
    background:#eff6ff;
    color:#1e3a8a;
    border:1px solid #bfdbfe;
    padding:12px 14px;
    border-radius:12px;
    margin-top:12px;
}
@media(max-width:1000px){.grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:768px){.grid,.grid2{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.grid,.grid2{grid-template-columns:1fr}}
</style>
<script>
function pilihGigi(nomor){
    document.getElementById('nomor_gigi').value = nomor;
}

function isiTindakan(selectEl){
    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt || !opt.value) return;

    const nama = opt.getAttribute('data-nama') || '';
    const harga = parseFloat(opt.getAttribute('data-harga') || '0');
    const kategori = opt.getAttribute('data-kategori') || '';
    const satuan = opt.getAttribute('data-satuan') || 'per tindakan';

    document.getElementById('nama_tindakan').value = nama;
    document.getElementById('harga').value = harga.toFixed(2);
    document.getElementById('kategori').value = kategori;
    document.getElementById('satuan_harga').value = satuan;

    hitungSubtotal();
}

function hitungSubtotal(){
    const qty = parseFloat(document.getElementById('qty').value || '0');
    const harga = parseFloat(document.getElementById('harga').value || '0');
    const subtotal = qty * harga;
    document.getElementById('subtotal').value = subtotal.toFixed(2);
}

window.addEventListener('DOMContentLoaded', function(){
    hitungSubtotal();
});
</script>
</head>
<body>
<div class="wrap">

    <div class="row" style="margin-bottom:16px">
        <div>
            <h1 style="margin:0">Odontogram Pro</h1>
            <div class="small">
                <?= $pasien ? e($pasien['no_rm'] ?? '') . ' - ' . e($pasien['nama'] ?? '') : 'Pilih pasien dan kunjungan terlebih dahulu' ?>
            </div>
        </div>
        <div class="row">
            <a class="btn secondary" href="kunjungan.php<?= $pasien_id ? '?pasien_id=' . (int)$pasien_id : '' ?>">Kembali Kunjungan</a>
            <a class="btn" href="invoice.php<?= $pasien_id ? '?pasien_id=' . (int)$pasien_id : '' ?><?= $kunjungan_id ? '&kunjungan_id=' . (int)$kunjungan_id : '' ?>">Billing</a>
        </div>
    </div>

    <?php if (!$pasien_id || !$kunjungan_id): ?>
        <div class="card">
            <div class="info">
                Odontogram harus dibuka dari data kunjungan agar pasien dan kunjungan terisi otomatis.
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php flash_message(); ?>
        <h2 style="margin-top:0">Input Odontogram</h2>

        <form method="post" action="simpan_odontogram.php">
            <input type="hidden" name="pasien_id" value="<?= (int)$pasien_id ?>">
            <input type="hidden" name="kunjungan_id" value="<?= (int)$kunjungan_id ?>">

            <div class="grid2">
                <div>
                    <label>Pasien</label>
                    <input type="text" value="<?= e(($pasien['no_rm'] ?? '') . ' - ' . ($pasien['nama'] ?? '')) ?>" readonly>
                </div>
                <div>
                    <label>Kunjungan</label>
                    <input type="text" value="<?= e($kunjungan['tanggal'] ?? '') ?>" readonly>
                </div>
            </div>

            <div style="margin-top:18px">
                <label>Pilih Nomor Gigi</label>
                <div class="grid" style="margin-top:10px">
                    <?php foreach ($nomorGigi as $g): ?>
                        <div class="tooth" onclick="pilihGigi('<?= $g ?>')"><?= $g ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid2" style="margin-top:18px">
                <div>
                    <label>Nomor Gigi</label>
                    <input type="text" id="nomor_gigi" name="nomor_gigi" required>
                </div>
                <div>
                    <label>Surface</label>
                    <input type="text" name="surface_code" placeholder="O / M / D / B / L / I">
                </div>

                <div>
                    <label>Pilih Tindakan</label>
                    <select name="tindakan_id" onchange="isiTindakan(this)" required>
                        <option value="">Pilih tindakan</option>
                        <?php foreach ($tindakanList as $t): ?>
                            <?php
                            $namaT = $t['nama_tindakan'] ?? $t['nama'] ?? '';
                            $hargaT = (float)($t['harga'] ?? 0);
                            $kategoriT = $t['kategori'] ?? '';
                            $satuanT = $t['satuan_harga'] ?? 'per tindakan';
                            ?>
                            <option
                                value="<?= (int)($t['id'] ?? 0) ?>"
                                data-nama="<?= e($namaT) ?>"
                                data-harga="<?= e($hargaT) ?>"
                                data-kategori="<?= e($kategoriT) ?>"
                                data-satuan="<?= e($satuanT) ?>"
                            >
                                <?= e($namaT) ?><?= $kategoriT ? ' - ' . e($kategoriT) : '' ?> • <?= e(rupiah($hargaT)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Nama Tindakan</label>
                    <input type="text" id="nama_tindakan" name="nama_tindakan" required>
                </div>

                <div>
                    <label>Kategori</label>
                    <input type="text" id="kategori" name="kategori" readonly>
                </div>

                <div>
                    <label>Satuan Harga</label>
                    <input type="text" id="satuan_harga" name="satuan_harga" value="per tindakan">
                </div>

                <div>
                    <label>Harga</label>
                    <input type="number" step="0.01" id="harga" name="harga" oninput="hitungSubtotal()" required>
                </div>

                <div>
                    <label>Qty</label>
                    <input type="number" step="0.01" id="qty" name="qty" value="1" oninput="hitungSubtotal()">
                </div>

                <div>
                    <label>Subtotal</label>
                    <input type="number" step="0.01" id="subtotal" name="subtotal" readonly>
                </div>

                <div class="full">
                    <label>Catatan</label>
                    <textarea name="catatan" rows="3"></textarea>
                </div>
            </div>

            <div class="row" style="margin-top:16px">
                <button type="submit" style="width:auto">Simpan Odontogram</button>
            </div>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Riwayat Odontogram</h2>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Gigi</th>
                        <th>Surface</th>
                        <th>Tindakan</th>
                        <th>Kategori</th>
                        <th>Qty</th>
                        <th>Harga</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($riwayat as $r): ?>
                    <tr>
                        <td><span class="badge"><?= e($r['nomor_gigi'] ?? '') ?></span></td>
                        <td><?= e($r['surface_code'] ?? '') ?></td>
                        <td><?= e($r['nama_tindakan'] ?? '') ?></td>
                        <td><?= e($r['kategori'] ?? '') ?></td>
                        <td><?= e($r['qty'] ?? '') ?></td>
                        <td><?= rupiah($r['harga'] ?? 0) ?></td>
                        <td><?= rupiah($r['subtotal'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php if (!$riwayat): ?>
                    <tr>
                        <td colspan="7">Belum ada data odontogram untuk kunjungan ini.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
