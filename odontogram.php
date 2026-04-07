<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

$pasienId    = (int)($_GET['pasien_id'] ?? 0);
$kunjunganId = (int)($_GET['kunjungan_id'] ?? 0);

$pasien    = $pasienId > 0 ? db_fetch_one("SELECT * FROM pasien WHERE id=?", [$pasienId]) : null;
$kunjungan = $kunjunganId > 0 ? db_fetch_one("SELECT * FROM kunjungan WHERE id=?", [$kunjunganId]) : null;
$tindakanList = tindakan_options();

$existing = [];
if ($kunjunganId > 0 && table_exists($conn, 'odontogram_tindakan')) {
    $existing = db_fetch_all(
        "SELECT * FROM odontogram_tindakan WHERE kunjungan_id=? ORDER BY id DESC",
        [$kunjunganId]
    );
}

function surface_badge_class($surfaceCode) {
    $v = strtoupper((string)$surfaceCode);
    if (in_array($v, ['O','M','D','B','L','I'])) return 'tag-blue';
    return 'tag-slate';
}

$teethUpperLeft   = ['18','17','16','15','14','13','12','11'];
$teethUpperRight  = ['21','22','23','24','25','26','27','28'];
$teethLowerLeft   = ['48','47','46','45','44','43','42','41'];
$teethLowerRight  = ['31','32','33','34','35','36','37','38'];

function renderTooth($tooth) {
    ?>
    <div class="tooth-card">
        <div class="tooth-label"><?= htmlspecialchars($tooth) ?></div>
        <div class="surface-grid">
            <button type="button" class="surface surface-b" data-tooth="<?= htmlspecialchars($tooth) ?>" data-surface="B">B</button>
            <button type="button" class="surface surface-m" data-tooth="<?= htmlspecialchars($tooth) ?>" data-surface="M">M</button>
            <button type="button" class="surface surface-o" data-tooth="<?= htmlspecialchars($tooth) ?>" data-surface="O">O</button>
            <button type="button" class="surface surface-d" data-tooth="<?= htmlspecialchars($tooth) ?>" data-surface="D">D</button>
            <button type="button" class="surface surface-l" data-tooth="<?= htmlspecialchars($tooth) ?>" data-surface="L">L</button>
        </div>
    </div>
    <?php
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Odontogram Modern</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{
    margin:0;
    background:linear-gradient(180deg,#f5f8fc 0%,#edf4fb 100%);
    color:#0f172a;
    font-family:Inter,Arial,sans-serif
}
.page{max-width:1460px;margin:0 auto;padding:24px}
.topbar{
    display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:18px
}
.brand h1{margin:0;font-size:42px;line-height:1.02;font-weight:900;color:#0f172a}
.brand p{margin:8px 0 0;color:#64748b;font-size:16px}
.navbar-mini{display:flex;gap:10px;flex-wrap:wrap}
.navbar-mini a{
    text-decoration:none;padding:12px 16px;border-radius:16px;background:#ffffff;border:1px solid #dde7f0;
    color:#1e293b;font-weight:700;box-shadow:0 8px 24px rgba(15,23,42,.05)
}
.cardx{
    background:#fff;border:1px solid #e2e8f0;border-radius:28px;padding:22px;
    box-shadow:0 18px 38px rgba(15,23,42,.06);margin-bottom:18px
}
.flash-danger{
    background:#fee2e2;color:#b91c1c;border:1px solid #fecaca;padding:14px 16px;border-radius:16px;margin-bottom:16px
}
.grid2{display:grid;grid-template-columns:1.15fr .85fr;gap:18px}
.grid-info{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}
.info-box{
    background:linear-gradient(180deg,#f8fbff 0%,#ffffff 100%);
    border:1px solid #dbe8f5;border-radius:20px;padding:16px 18px
}
.info-box .label{font-size:13px;color:#64748b;margin-bottom:6px}
.info-box .value{font-size:18px;font-weight:800}
.legend{display:flex;gap:12px;flex-wrap:wrap;margin-top:8px}
.legend-item{
    display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:999px;border:1px solid #e2e8f0;background:#f8fafc;font-size:13px
}
.dot{width:14px;height:14px;border-radius:4px}
.dot-blue{background:#3b82f6}
.dot-green{background:#16a34a}
.dot-rose{background:#e11d48}
.dot-slate{background:#64748b}

.odonto-shell{
    background:linear-gradient(180deg,#f8fbff 0%,#f2f7fd 100%);
    border:1px solid #dce8f5;border-radius:28px;padding:18px
}
.arch-title{
    font-size:16px;font-weight:800;color:#334155;margin:0 0 12px
}
.arch{
    background:#ffffff;border:2px solid #cddaea;border-radius:28px;padding:18px 16px;margin-bottom:16px;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.6)
}
.arch-row{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
.tooth-card{
    width:84px;background:#fff;border:1px solid #dbe4ee;border-radius:18px;padding:10px 8px;
    box-shadow:0 8px 20px rgba(15,23,42,.05)
}
.tooth-label{
    text-align:center;font-size:12px;font-weight:800;color:#334155;margin-bottom:8px
}
.surface-grid{
    display:grid;grid-template-columns:20px 20px 20px;grid-template-rows:20px 20px 20px;gap:4px;justify-content:center
}
.surface{
    border:1px solid #cfd8e3;border-radius:6px;background:#f8fafc;
    font-size:10px;font-weight:800;color:#334155;display:flex;align-items:center;justify-content:center;
    cursor:pointer;transition:.15s ease
}
.surface:hover{transform:translateY(-1px);background:#dbeafe;border-color:#93c5fd}
.surface.active-selected{outline:3px solid #2563eb;outline-offset:1px;background:#dbeafe}
.surface-b{grid-column:2;grid-row:1}
.surface-m{grid-column:1;grid-row:2}
.surface-o{grid-column:2;grid-row:2}
.surface-d{grid-column:3;grid-row:2}
.surface-l{grid-column:2;grid-row:3}

.form-modern .form-label{font-weight:800;color:#334155}
.form-modern .form-control,
.form-modern .form-select{
    border-radius:16px;padding:12px 14px;border:1px solid #cfd8e3
}
.form-modern .form-control:focus,
.form-modern .form-select:focus{
    border-color:#93c5fd;box-shadow:0 0 0 .2rem rgba(59,130,246,.15)
}
.price-note{
    font-size:12px;color:#64748b;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:10px 12px
}
.cta-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.btn-modern{
    border:none;border-radius:16px;padding:13px 18px;font-weight:800
}
.btn-save{background:linear-gradient(135deg,#0f766e,#14b8a6);color:#fff}
.btn-back{background:#475569;color:#fff}
.table-wrap{overflow:auto}
.table-modern{width:100%;border-collapse:collapse}
.table-modern th,.table-modern td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top}
.table-modern th{background:#f8fafc;color:#334155}
.tag{
    display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800
}
.tag-blue{background:#dbeafe;color:#1d4ed8}
.tag-slate{background:#e2e8f0;color:#334155}
.text-muted-small{font-size:12px;color:#64748b}
@media (max-width:1100px){.grid2{grid-template-columns:1fr}.grid-info{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="page">
    <div class="topbar">
        <div class="brand">
            <h1>Odontogram Modern</h1>
            <p>
                <?= $pasien ? e($pasien['no_rm'] ?? '') . ' - ' . e($pasien['nama'] ?? '') : 'Pilih pasien dan kunjungan terlebih dahulu' ?>
            </p>
        </div>
        <div class="navbar-mini">
            <a href="dashboard.php">Dashboard</a>
            <a href="kunjungan.php<?= $pasienId ? '?pasien_id=' . (int)$pasienId : '' ?>">Kunjungan</a>
            <a href="invoice.php?pasien_id=<?= (int)$pasienId ?>&kunjungan_id=<?= (int)$kunjunganId ?>">Billing</a>
        </div>
    </div>

    <?php flash_message(); ?>

    <div class="grid-info">
        <div class="info-box">
            <div class="label">Pasien</div>
            <div class="value"><?= $pasien ? e($pasien['nama'] ?? '-') : '-' ?></div>
            <div class="text-muted-small">No. RM: <?= $pasien ? e($pasien['no_rm'] ?? '-') : '-' ?></div>
        </div>
        <div class="info-box">
            <div class="label">Kunjungan</div>
            <div class="value"><?= $kunjungan ? e($kunjungan['tanggal'] ?? '-') : '-' ?></div>
            <div class="text-muted-small">ID kunjungan: <?= (int)$kunjunganId ?></div>
        </div>
        <div class="info-box">
            <div class="label">Status</div>
            <div class="value"><?= ($pasienId > 0 && $kunjunganId > 0) ? 'Siap input tindakan' : 'Belum lengkap' ?></div>
            <div class="text-muted-small">Buka dari halaman kunjungan agar otomatis terhubung</div>
        </div>
    </div>

    <div class="legend">
        <div class="legend-item"><span class="dot dot-blue"></span>Pilih permukaan</div>
        <div class="legend-item"><span class="dot dot-green"></span>Tindakan perawatan</div>
        <div class="legend-item"><span class="dot dot-rose"></span>Perlu tindakan khusus</div>
        <div class="legend-item"><span class="dot dot-slate"></span>Riwayat tersimpan</div>
    </div>

    <div class="grid2" style="margin-top:18px;">
        <div class="cardx">
            <div class="odonto-shell">
                <div class="arch-title">Rahang Atas</div>
                <div class="arch">
                    <div class="arch-row">
                        <?php foreach ($teethUpperLeft as $t) renderTooth($t); ?>
                        <?php foreach ($teethUpperRight as $t) renderTooth($t); ?>
                    </div>
                </div>

                <div class="arch-title">Rahang Bawah</div>
                <div class="arch">
                    <div class="arch-row">
                        <?php foreach ($teethLowerLeft as $t) renderTooth($t); ?>
                        <?php foreach ($teethLowerRight as $t) renderTooth($t); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="cardx">
            <form class="form-modern" id="odontogramForm" onsubmit="return false;">
                <h4 style="margin-top:0;font-weight:900;">Input Tindakan Odontogram</h4>

                <div class="mb-3">
                    <label class="form-label">Target Gigi</label>
                    <input type="text" id="modal_target" class="form-control" readonly placeholder="Klik permukaan gigi dulu">
                </div>

                <div class="mb-3">
                    <label class="form-label">Pasien</label>
                    <input type="text" class="form-control" value="<?= $pasien ? e($pasien['nama'] ?? '') : '' ?>" readonly>
                    <input type="hidden" id="patient_id" value="<?= (int)$pasienId ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Kunjungan</label>
                    <input type="text" class="form-control" value="<?= $kunjungan ? e($kunjungan['tanggal'] ?? '') : '' ?>" readonly>
                    <input type="hidden" id="visit_id" value="<?= (int)$kunjunganId ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Tindakan</label>
                    <select id="condition_code" class="form-select">
                        <option value="">Pilih tindakan</option>
                        <?php foreach ($tindakanList as $t):
                            $namaTindakan = $t['nama_tindakan'] ?? $t['nama'] ?? 'Tindakan';
                            $harga = (float)($t['harga'] ?? 0);
                            $satuan = $t['satuan_harga'] ?? 'per tindakan';
                            $kategori = $t['kategori'] ?? '';
                        ?>
                            <option
                                value="<?= e($t['kode'] ?? ($t['id'] ?? 0)) ?>"
                                data-id="<?= (int)($t['id'] ?? 0) ?>"
                                data-kode="<?= e($t['kode'] ?? ($t['id'] ?? 0)) ?>"
                                data-nama="<?= e($namaTindakan) ?>"
                                data-kategori="<?= e($kategori) ?>"
                                data-harga="<?= e($harga) ?>"
                                data-satuan="<?= e($satuan) ?>"
                            >
                                <?= e($namaTindakan) ?><?= $kategori ? ' - ' . e($kategori) : '' ?> • <?= e(rupiah($harga)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Harga</label>
                        <input type="number" id="modal_harga" class="form-control" value="0" min="0">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Qty</label>
                        <input type="number" id="modal_qty" class="form-control" value="1" min="1">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Subtotal</label>
                    <input type="text" id="modal_subtotal_text" class="form-control" value="Rp 0" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Satuan Harga</label>
                    <input type="text" id="modal_satuan" class="form-control" readonly value="-">
                </div>

                <div class="mb-3">
                    <label class="form-label">Catatan</label>
                    <textarea id="modal_catatan" class="form-control" rows="3" placeholder="Catatan tambahan"></textarea>
                </div>

                <div class="mb-3">
                    <div class="price-note" id="modal_price_note">Harga akan otomatis terisi dari master tindakan.</div>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="send_to_billing" checked>
                    <label class="form-check-label" for="send_to_billing">Hubungkan ke billing otomatis</label>
                </div>

                <div class="cta-row">
                    <button type="button" class="btn-modern btn-save" id="saveSurfaceBtn">Simpan Tindakan</button>
                    <a href="invoice.php?pasien_id=<?= (int)$pasienId ?>&kunjungan_id=<?= (int)$kunjunganId ?>" class="btn-modern btn-back">Buka Billing</a>
                </div>
            </form>
        </div>
    </div>

    <div class="cardx">
        <h4 style="margin-top:0;font-weight:900;">Riwayat Tindakan Odontogram</h4>
        <div class="table-wrap">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Gigi</th>
                        <th>Permukaan</th>
                        <th>Tindakan</th>
                        <th>Harga</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($existing): ?>
                        <?php foreach ($existing as $o): ?>
                            <tr>
                                <td><?= e($o['nomor_gigi'] ?? '-') ?></td>
                                <td><span class="tag <?= surface_badge_class($o['surface_code'] ?? '') ?>"><?= e($o['surface_code'] ?? '-') ?></span></td>
                                <td><?= e($o['nama_tindakan'] ?? '-') ?></td>
                                <td><?= e(rupiah($o['harga'] ?? 0)) ?></td>
                                <td><?= e($o['qty'] ?? 0) ?></td>
                                <td><?= e(rupiah($o['subtotal'] ?? 0)) ?></td>
                                <td><?= e($o['catatan'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">Belum ada tindakan odontogram untuk kunjungan ini.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let selectedEl = null;
const tindakanSelect = document.getElementById('condition_code');
const hargaInput = document.getElementById('modal_harga');
const qtyInput = document.getElementById('modal_qty');
const subtotalText = document.getElementById('modal_subtotal_text');
const satuanInput = document.getElementById('modal_satuan');
const noteBox = document.getElementById('modal_price_note');

function formatRupiah(number) {
    return 'Rp ' + Number(number || 0).toLocaleString('id-ID');
}

function updateSubtotal() {
    const harga = parseInt(hargaInput.value || 0, 10);
    const qty = parseInt(qtyInput.value || 1, 10);
    subtotalText.value = formatRupiah(harga * qty);
}

function applyTreatmentMeta() {
    const selected = tindakanSelect.options[tindakanSelect.selectedIndex];
    if (!selected || !selected.value) {
        hargaInput.value = 0;
        satuanInput.value = '-';
        noteBox.textContent = 'Harga akan otomatis terisi dari master tindakan.';
        updateSubtotal();
        return;
    }

    const harga = parseInt(selected.dataset.harga || '0', 10);
    const satuan = selected.dataset.satuan || 'per tindakan';
    const kategori = selected.dataset.kategori || '';

    hargaInput.value = harga;
    satuanInput.value = satuan;
    noteBox.textContent = 'Kategori: ' + (kategori || '-') + ' | Harga standar: ' + formatRupiah(harga);
    updateSubtotal();
}

document.querySelectorAll('.surface').forEach(el => {
    el.addEventListener('click', function () {
        document.querySelectorAll('.surface').forEach(s => s.classList.remove('active-selected'));
        this.classList.add('active-selected');
        selectedEl = this;
        document.getElementById('modal_target').value = this.dataset.tooth + ' / ' + this.dataset.surface;
    });
});

tindakanSelect.addEventListener('change', applyTreatmentMeta);
hargaInput.addEventListener('input', updateSubtotal);
qtyInput.addEventListener('input', updateSubtotal);
updateSubtotal();

document.getElementById('saveSurfaceBtn').addEventListener('click', function () {
    if (!selectedEl) {
        alert('Pilih permukaan gigi terlebih dahulu.');
        return;
    }

    const selectedOption = tindakanSelect.options[tindakanSelect.selectedIndex];
    const tindakanId = parseInt(selectedOption?.dataset?.id || '0', 10);
    const conditionCode = selectedOption?.dataset?.kode || tindakanSelect.value || '';
    const visitId = parseInt(document.getElementById('visit_id').value || '0', 10);
    const patientId = parseInt(document.getElementById('patient_id').value || '0', 10);
    const tooth = selectedEl.dataset.tooth;
    const surface = selectedEl.dataset.surface;
    const harga = parseInt(hargaInput.value || '0', 10);
    const qty = parseInt(qtyInput.value || '1', 10);
    const satuan = selectedOption?.dataset?.satuan || 'per tindakan';
    const catatan = document.getElementById('modal_catatan').value || '';
    const sendToBilling = document.getElementById('send_to_billing').checked ? '1' : '0';

    if (patientId <= 0 || visitId <= 0) {
        alert('Pasien dan kunjungan belum valid.');
        return;
    }

    if (!conditionCode || tindakanId <= 0) {
        alert('Pilih tindakan terlebih dahulu.');
        return;
    }

    const formData = new URLSearchParams();
    formData.append('patient_id', patientId);
    formData.append('visit_id', visitId);
    formData.append('tooth_number', tooth);
    formData.append('surface_code', surface);
    formData.append('condition_code', conditionCode);
    formData.append('status_type', 'completed');
    formData.append('send_to_billing', sendToBilling);
    formData.append('tindakan_id', tindakanId);
    formData.append('nama_tindakan', selectedOption?.dataset?.nama || '');
    formData.append('harga', harga);
    formData.append('qty', qty);
    formData.append('subtotal', harga * qty);
    formData.append('satuan_harga', satuan);
    formData.append('catatan', catatan);

    fetch('save_surface.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: formData.toString()
    })
    .then(res => res.text())
    .then(text => {
        alert(text);
        location.reload();
    })
    .catch(() => {
        alert('Gagal menyimpan tindakan odontogram.');
    });
});
</script>
</body>
</html>
