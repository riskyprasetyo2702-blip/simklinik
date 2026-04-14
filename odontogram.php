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

$adultUpperLeft  = [18,17,16,15,14,13,12,11];
$adultUpperRight = [21,22,23,24,25,26,27,28];
$adultLowerLeft  = [48,47,46,45,44,43,42,41];
$adultLowerRight = [31,32,33,34,35,36,37,38];

$childUpperLeft  = [55,54,53,52,51];
$childUpperRight = [61,62,63,64,65];
$childLowerLeft  = [85,84,83,82,81];
$childLowerRight = [71,72,73,74,75];

$riwayatMap = [];
foreach ($riwayat as $r) {
    $gigi = (string)($r['nomor_gigi'] ?? '');
    $surface = strtoupper(trim((string)($r['surface_code'] ?? '')));
    if ($gigi === '') continue;
    if (!isset($riwayatMap[$gigi])) {
        $riwayatMap[$gigi] = [];
    }
    if ($surface !== '') {
        $parts = preg_split('/\s*,\s*|\s*\/\s*|\s+/', $surface);
        foreach ($parts as $p) {
            $p = strtoupper(trim($p));
            if ($p !== '') $riwayatMap[$gigi][$p] = true;
        }
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Odontogram Pro Plus</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:linear-gradient(135deg,#eff6ff 0%,#f8fafc 55%,#eef2ff 100%);color:#0f172a}
.wrap{max-width:1480px;margin:24px auto;padding:0 16px}
.hero{background:linear-gradient(135deg,#0f172a,#1d4ed8);color:#fff;border-radius:28px;padding:26px 28px;box-shadow:0 18px 40px rgba(15,23,42,.18);margin-bottom:18px}
.hero-top{display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:flex-start}
.hero h1{margin:0 0 8px;font-size:34px}
.hero p{margin:0;color:rgba(255,255,255,.86)}
.hero-meta{display:flex;gap:12px;flex-wrap:wrap;margin-top:18px}
.hero-pill{background:rgba(255,255,255,.12);padding:12px 16px;border-radius:16px;min-width:180px;backdrop-filter:blur(8px)}
.hero-pill strong{display:block;font-size:22px}
.card{background:#fff;border-radius:24px;padding:22px;box-shadow:0 14px 30px rgba(15,23,42,.08);margin-bottom:18px;border:1px solid rgba(148,163,184,.14)}
.row{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.full{grid-column:1/-1}
input,select,textarea,button{width:100%;padding:12px 14px;border:1px solid #cbd5e1;border-radius:14px}
textarea{resize:vertical}
button,.btn{background:linear-gradient(135deg,#0f172a,#1d4ed8);color:#fff;text-decoration:none;display:inline-block;border:none;font-weight:700;cursor:pointer;padding:12px 16px;border-radius:14px;box-shadow:0 10px 20px rgba(29,78,216,.16)}
.btn.secondary{background:#475569;box-shadow:none}
.small{font-size:13px;color:#64748b}
.info{background:#eff6ff;color:#1e3a8a;border:1px solid #bfdbfe;padding:12px 14px;border-radius:14px}
.tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px}
.tab-btn{width:auto;padding:10px 16px;border-radius:999px;border:1px solid #bfdbfe;background:#eff6ff;color:#1d4ed8;font-weight:700;cursor:pointer}
.tab-btn.active{background:linear-gradient(135deg,#1d4ed8,#2563eb);color:#fff;border-color:#2563eb}
.palette{display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 4px}
.palette-item{display:flex;align-items:center;gap:8px;background:#f8fafc;border:1px solid #e2e8f0;padding:8px 12px;border-radius:999px;cursor:pointer}
.palette-dot{width:16px;height:16px;border-radius:999px;border:2px solid rgba(15,23,42,.08)}
.palette-item.active{border-color:#2563eb;background:#eff6ff}
.odonto-board{display:grid;gap:18px}
.arch{display:grid;grid-template-columns:1fr 1fr;gap:18px}
.quadrant{background:linear-gradient(135deg,#ffffff,#f8fbff);border:1px solid #dbeafe;border-radius:20px;padding:16px;box-shadow:0 10px 18px rgba(59,130,246,.07)}
.quadrant-title{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;color:#1d4ed8;font-weight:700}
.tooth-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(72px,1fr));gap:12px}
.tooth-card{background:#fff;border:1px solid #dbeafe;border-radius:18px;padding:8px;box-shadow:0 8px 18px rgba(59,130,246,.08);position:relative;transition:.18s ease}
.tooth-card:hover{transform:translateY(-2px);box-shadow:0 12px 22px rgba(59,130,246,.14)}
.tooth-card.active{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.18),0 12px 24px rgba(59,130,246,.18)}
.tooth-no{text-align:center;font-size:13px;font-weight:800;margin-bottom:8px;color:#1e3a8a}
.tooth-shape{display:grid;grid-template-columns:18px 1fr 18px;grid-template-rows:18px 1fr 18px;gap:4px;height:74px}
.surface{border:1px solid #cbd5e1;background:#f8fafc;border-radius:8px;cursor:pointer;transition:.18s ease}
.surface.top{grid-column:2;grid-row:1}
.surface.left{grid-column:1;grid-row:2}
.surface.center{grid-column:2;grid-row:2;border-radius:10px;border:2px solid #cbd5e1;background:#fff}
.surface.right{grid-column:3;grid-row:2}
.surface.bottom{grid-column:2;grid-row:3}
.surface.active{outline:3px solid rgba(37,99,235,.18);border-color:#2563eb}
.surface.has-history{box-shadow:inset 0 0 0 2px rgba(15,23,42,.06)}
.history-dot{position:absolute;top:10px;right:10px;width:10px;height:10px;border-radius:999px;background:#22c55e;box-shadow:0 0 0 3px rgba(34,197,94,.15)}
.form-shell{background:linear-gradient(135deg,#ffffff,#f8fbff);border:1px solid #dbeafe}
.table-wrap{overflow:auto}
.table{width:100%;border-collapse:separate;border-spacing:0;overflow:hidden;border-radius:18px}
.table th,.table td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;vertical-align:top;background:#fff}
.table th{background:linear-gradient(135deg,#dbeafe,#eff6ff);color:#1e3a8a;font-size:12px;text-transform:uppercase;letter-spacing:.4px}
.table tbody tr:nth-child(even) td{background:#f8fbff}
.badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#e2e8f0;font-size:12px}
.summary-box{background:#eff6ff;border:1px solid #bfdbfe;border-radius:16px;padding:14px;color:#1e3a8a}
.hidden{display:none !important}
@media(max-width:1100px){.arch{grid-template-columns:1fr}.grid4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:760px){.grid2,.grid3,.grid4{grid-template-columns:1fr}.hero h1{font-size:28px}.hero-pill{min-width:unset;width:100%}}
</style>
<script>
const historyMap = <?= json_encode($riwayatMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const surfaceMap = {
    top: 'O/I',
    left: 'M',
    center: 'O/I',
    right: 'D',
    bottom: 'B/L'
};
let currentMode = 'adult';
let activeCondition = { label: 'Normal', color: '#f8fafc' };

function setMode(mode){
    currentMode = mode;
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.toggle('active', btn.dataset.mode === mode));
    document.querySelectorAll('.mode-panel').forEach(panel => panel.classList.toggle('hidden', panel.dataset.mode !== mode));
    clearSelection();
}

function setCondition(el, label, color){
    activeCondition = {label, color};
    document.querySelectorAll('.palette-item').forEach(x => x.classList.remove('active'));
    el.classList.add('active');
    const info = document.getElementById('selected_condition_label');
    if (info) info.textContent = label;
}

function clearSelection(){
    document.querySelectorAll('.tooth-card').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.surface').forEach(el => el.classList.remove('active'));
    const nomor = document.getElementById('nomor_gigi');
    const surface = document.getElementById('surface_code');
    if (nomor) nomor.value = '';
    if (surface) surface.value = '';
}

function selectToothSurface(toothNumber, surfaceKey, el){
    document.querySelectorAll('.tooth-card').forEach(card => card.classList.remove('active'));
    document.querySelectorAll('.surface').forEach(s => s.classList.remove('active'));

    const card = el.closest('.tooth-card');
    if (card) card.classList.add('active');
    el.classList.add('active');
    el.style.background = activeCondition.color;

    document.getElementById('nomor_gigi').value = toothNumber;
    document.getElementById('surface_code').value = surfaceMap[surfaceKey] || surfaceKey;

    const statusBox = document.getElementById('surface_info');
    if (statusBox) {
        statusBox.innerHTML = 'Gigi <strong>' + toothNumber + '</strong> • Surface <strong>' + (surfaceMap[surfaceKey] || surfaceKey) + '</strong> • Kondisi visual <strong>' + activeCondition.label + '</strong>';
    }
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

function applyHistoryColors(){
    Object.keys(historyMap).forEach(function(toothNo){
        const surfaces = historyMap[toothNo] || {};
        const card = document.querySelector('.tooth-card[data-tooth="' + toothNo + '"]');
        if (!card) return;
        const dot = card.querySelector('.history-dot');
        if (dot) dot.classList.remove('hidden');

        Object.keys(surfaces).forEach(function(surfaceCode){
            let key = null;
            if (surfaceCode === 'M') key = 'left';
            else if (surfaceCode === 'D') key = 'right';
            else if (surfaceCode === 'B' || surfaceCode === 'L' || surfaceCode === 'F') key = 'bottom';
            else if (surfaceCode === 'O' || surfaceCode === 'I') key = 'top';
            if (!key) return;
            const el = card.querySelector('.surface.' + key);
            if (el) {
                el.classList.add('has-history');
                if (!el.dataset.locked) {
                    el.style.background = '#bfdbfe';
                }
            }
        });
    });
}

window.addEventListener('DOMContentLoaded', function(){
    hitungSubtotal();
    setMode('adult');
    applyHistoryColors();
});
</script>
</head>
<body>
<div class="wrap">

    <div class="hero">
        <div class="hero-top">
            <div>
                <h1>Odontogram Pro Plus</h1>
                <p>Desain multi-surface, warna kondisi, mode dewasa dan anak, tetap kompatibel dengan sistem tindakan dan billing yang sudah berjalan.</p>
            </div>
            <div class="row">
                <a class="btn secondary" href="kunjungan.php<?= $pasien_id ? '?pasien_id=' . (int)$pasien_id : '' ?>">Kembali Kunjungan</a>
                <a class="btn" href="invoice.php<?= $pasien_id ? '?pasien_id=' . (int)$pasien_id : '' ?><?= $kunjungan_id ? '&kunjungan_id=' . (int)$kunjungan_id : '' ?>">Billing</a>
            </div>
        </div>
        <div class="hero-meta">
            <div class="hero-pill"><span>Pasien</span><strong><?= $pasien ? e($pasien['nama'] ?? '-') : '-' ?></strong></div>
            <div class="hero-pill"><span>No. RM</span><strong><?= $pasien ? e($pasien['no_rm'] ?? '-') : '-' ?></strong></div>
            <div class="hero-pill"><span>Kunjungan</span><strong><?= $kunjungan ? e(substr((string)($kunjungan['tanggal'] ?? '-'), 0, 10)) : '-' ?></strong></div>
        </div>
    </div>

    <?php if (!$pasien_id || !$kunjungan_id): ?>
        <div class="card">
            <div class="info">Odontogram harus dibuka dari data kunjungan agar pasien dan kunjungan terisi otomatis.</div>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php flash_message(); ?>
        <div class="row" style="margin-bottom:14px">
            <div>
                <h2 style="margin:0">Peta Odontogram Interaktif</h2>
                <div class="small">Klik gigi lalu klik surface yang ingin dipilih. Warna hanya untuk visual klinis di layar dan tidak mengubah struktur tabel lama.</div>
            </div>
            <div class="summary-box" id="surface_info">Belum ada surface yang dipilih.</div>
        </div>

        <div class="tabs">
            <button type="button" class="tab-btn active" data-mode="adult" onclick="setMode('adult')">Odontogram Dewasa</button>
            <button type="button" class="tab-btn" data-mode="child" onclick="setMode('child')">Odontogram Anak</button>
        </div>

        <div class="palette">
            <div class="palette-item active" onclick="setCondition(this,'Normal','#f8fafc')"><span class="palette-dot" style="background:#f8fafc"></span>Normal</div>
            <div class="palette-item" onclick="setCondition(this,'Karies','#fecaca')"><span class="palette-dot" style="background:#fecaca"></span>Karies</div>
            <div class="palette-item" onclick="setCondition(this,'Tambalan','#bfdbfe')"><span class="palette-dot" style="background:#bfdbfe"></span>Tambalan</div>
            <div class="palette-item" onclick="setCondition(this,'Sealant','#bbf7d0')"><span class="palette-dot" style="background:#bbf7d0"></span>Sealant</div>
            <div class="palette-item" onclick="setCondition(this,'Crown','#ddd6fe')"><span class="palette-dot" style="background:#ddd6fe"></span>Crown</div>
            <div class="palette-item" onclick="setCondition(this,'Missing','#e5e7eb')"><span class="palette-dot" style="background:#e5e7eb"></span>Missing</div>
            <div class="palette-item" onclick="setCondition(this,'Observasi','#fde68a')"><span class="palette-dot" style="background:#fde68a"></span>Observasi</div>
        </div>
        <div class="small">Kondisi visual terpilih: <strong id="selected_condition_label">Normal</strong></div>

        <div class="odonto-board mode-panel" data-mode="adult" style="margin-top:18px;">
            <div class="arch">
                <div class="quadrant">
                    <div class="quadrant-title"><span>Kuadran 1</span><span>Dewasa Atas Kanan</span></div>
                    <div class="tooth-grid">
                        <?php foreach ($adultUpperLeft as $g): ?>
                            <div class="tooth-card" data-tooth="<?= $g ?>">
                                <div class="history-dot hidden"></div>
                                <div class="tooth-no"><?= $g ?></div>
                                <div class="tooth-shape">
                                    <div class="surface top" onclick="selectToothSurface('<?= $g ?>','top',this)"></div>
                                    <div class="surface left" onclick="selectToothSurface('<?= $g ?>','left',this)"></div>
                                    <div class="surface center" onclick="selectToothSurface('<?= $g ?>','center',this)"></div>
                                    <div class="surface right" onclick="selectToothSurface('<?= $g ?>','right',this)"></div>
                                    <div class="surface bottom" onclick="selectToothSurface('<?= $g ?>','bottom',this)"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="quadrant">
                    <div class="quadrant-title"><span>Kuadran 2</span><span>Dewasa Atas Kiri</span></div>
                    <div class="tooth-grid">
                        <?php foreach ($adultUpperRight as $g): ?>
                            <div class="tooth-card" data-tooth="<?= $g ?>">
                                <div class="history-dot hidden"></div>
                                <div class="tooth-no"><?= $g ?></div>
                                <div class="tooth-shape">
                                    <div class="surface top" onclick="selectToothSurface('<?= $g ?>','top',this)"></div>
                                    <div class="surface left" onclick="selectToothSurface('<?= $g ?>','left',this)"></div>
                                    <div class="surface center" onclick="selectToothSurface('<?= $g ?>','center',this)"></div>
                                    <div class="surface right" onclick="selectToothSurface('<?= $g ?>','right',this)"></div>
                                    <div class="surface bottom" onclick="selectToothSurface('<?= $g ?>','bottom',this)"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="quadrant">
                    <div class="quadrant-title"><span>Kuadran 4</span><span>Dewasa Bawah Kanan</span></div>
                    <div class="tooth-grid">
                        <?php foreach ($adultLowerLeft as $g): ?>
                            <div class="tooth-card" data-tooth="<?= $g ?>">
                                <div class="history-dot hidden"></div>
                                <div class="tooth-no"><?= $g ?></div>
                                <div class="tooth-shape">
                                    <div class="surface top" onclick="selectToothSurface('<?= $g ?>','top',this)"></div>
                                    <div class="surface left" onclick="selectToothSurface('<?= $g ?>','left',this)"></div>
                                    <div class="surface center" onclick="selectToothSurface('<?= $g ?>','center',this)"></div>
                                    <div class="surface right" onclick="selectToothSurface('<?= $g ?>','right',this)"></div>
                                    <div class="surface bottom" onclick="selectToothSurface('<?= $g ?>','bottom',this)"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="quadrant">
                    <div class="quadrant-title"><span>Kuadran 3</span><span>Dewasa Bawah Kiri</span></div>
                    <div class="tooth-grid">
                        <?php foreach ($adultLowerRight as $g): ?>
                            <div class="tooth-card" data-tooth="<?= $g ?>">
                                <div class="history-dot hidden"></div>
                                <div class="tooth-no"><?= $g ?></div>
                                <div class="tooth-shape">
                                    <div class="surface top" onclick="selectToothSurface('<?= $g ?>','top',this)"></div>
                                    <div class="surface left" onclick="selectToothSurface('<?= $g ?>','left',this)"></div>
                                    <div class="surface center" onclick="selectToothSurface('<?= $g ?>','center',this)"></div>
                                    <div class="surface right" onclick="selectToothSurface('<?= $g ?>','right',this)"></div>
                                    <div class="surface bottom" onclick="selectToothSurface('<?= $g ?>','bottom',this)"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="odonto-board mode-panel hidden" data-mode="child" style="margin-top:18px;">
            <div class="arch">
                <div class="quadrant">
                    <div class="quadrant-title"><span>Kuadran 5</span><span>Anak Atas Kanan</span></div>
                    <div class="tooth-grid">
                        <?php foreach ($childUpperLeft as $g): ?>
                            <div class="tooth-card" data-tooth="<?= $g ?>">
                                <div class="history-dot hidden"></div>
                                <div class="tooth-no"><?= $g ?></div>
                                <div class="tooth-shape">
                                    <div class="surface top" onclick="selectToothSurface('<?= $g ?>','top',this)"></div>
                                    <div class="surface left" onclick="selectToothSurface('<?= $g ?>','left',this)"></div>
                                    <div class="surface center" onclick="selectToothSurface('<?= $g ?>','center',this)"></div>
                                    <div class="surface right" onclick="selectToothSurface('<?= $g ?>','right',this)"></div>
                                    <div class="surface bottom" onclick="selectToothSurface('<?= $g ?>','bottom',this)"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="quadrant">
                    <div class="quadrant-title"><span>Kuadran 6</span><span>Anak Atas Kiri</span></div>
                    <div class="tooth-grid">
                        <?php foreach ($childUpperRight as $g): ?>
                            <div class="tooth-card" data-tooth="<?= $g ?>">
                                <div class="history-dot hidden"></div>
                                <div class="tooth-no"><?= $g ?></div>
                                <div class="tooth-shape">
                                    <div class="surface top" onclick="selectToothSurface('<?= $g ?>','top',this)"></div>
                                    <div class="surface left" onclick="selectToothSurface('<?= $g ?>','left',this)"></div>
                                    <div class="surface center" onclick="selectToothSurface('<?= $g ?>','center',this)"></div>
                                    <div class="surface right" onclick="selectToothSurface('<?= $g ?>','right',this)"></div>
                                    <div class="surface bottom" onclick="selectToothSurface('<?= $g ?>','bottom',this)"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="quadrant">
                    <div class="quadrant-title"><span>Kuadran 8</span><span>Anak Bawah Kanan</span></div>
                    <div class="tooth-grid">
                        <?php foreach ($childLowerLeft as $g): ?>
                            <div class="tooth-card" data-tooth="<?= $g ?>">
                                <div class="history-dot hidden"></div>
                                <div class="tooth-no"><?= $g ?></div>
                                <div class="tooth-shape">
                                    <div class="surface top" onclick="selectToothSurface('<?= $g ?>','top',this)"></div>
                                    <div class="surface left" onclick="selectToothSurface('<?= $g ?>','left',this)"></div>
                                    <div class="surface center" onclick="selectToothSurface('<?= $g ?>','center',this)"></div>
                                    <div class="surface right" onclick="selectToothSurface('<?= $g ?>','right',this)"></div>
                                    <div class="surface bottom" onclick="selectToothSurface('<?= $g ?>','bottom',this)"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="quadrant">
                    <div class="quadrant-title"><span>Kuadran 7</span><span>Anak Bawah Kiri</span></div>
                    <div class="tooth-grid">
                        <?php foreach ($childLowerRight as $g): ?>
                            <div class="tooth-card" data-tooth="<?= $g ?>">
                                <div class="history-dot hidden"></div>
                                <div class="tooth-no"><?= $g ?></div>
                                <div class="tooth-shape">
                                    <div class="surface top" onclick="selectToothSurface('<?= $g ?>','top',this)"></div>
                                    <div class="surface left" onclick="selectToothSurface('<?= $g ?>','left',this)"></div>
                                    <div class="surface center" onclick="selectToothSurface('<?= $g ?>','center',this)"></div>
                                    <div class="surface right" onclick="selectToothSurface('<?= $g ?>','right',this)"></div>
                                    <div class="surface bottom" onclick="selectToothSurface('<?= $g ?>','bottom',this)"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card form-shell">
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

            <div class="grid4" style="margin-top:18px">
                <div>
                    <label>Nomor Gigi</label>
                    <input type="text" id="nomor_gigi" name="nomor_gigi" required>
                </div>
                <div>
                    <label>Surface</label>
                    <input type="text" id="surface_code" name="surface_code" placeholder="O / I / M / D / B / L" required>
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
                    <textarea name="catatan" rows="3" placeholder="Catatan klinis, misalnya karies oklusal, fraktur cusp, gigi sulung goyang, dan lain-lain"></textarea>
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
