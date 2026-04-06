<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
$conn = db();
if (!($conn instanceof mysqli)) {
    die('Koneksi database tidak ditemukan dari config.php');
}
ensure_odontogram_tables($conn);
$pasienList = get_pasien_options($conn);
$kunjunganList = get_kunjungan_options($conn);
$icd10List = get_icd10_list();

$defaultTeeth = [18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28,48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38];
$defaultActions = [
    'Pemeriksaan' => 50000,
    'Tambal GIC' => 150000,
    'Tambal Komposit' => 250000,
    'Scaling' => 350000,
    'Ekstraksi' => 300000,
    'Devitalisasi' => 250000,
    'PSA' => 800000,
    'RCT Anterior' => 900000,
    'RCT Posterior' => 1500000,
    'Mahkota Sementara' => 300000,
    'Crown PFM' => 2500000,
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odontogram Pro</title>
    <style>
        :root{--bg:#f8fafc;--card:#fff;--line:#dbe4f0;--ink:#0f172a;--muted:#64748b;--blue:#2563eb;--violet:#7c3aed;--green:#16a34a}
        *{box-sizing:border-box} body{margin:0;background:linear-gradient(180deg,#eff6ff,#f8fafc 35%,#eef2ff);font-family:Inter,Arial,sans-serif;color:var(--ink)}
        .wrap{max-width:1450px;margin:0 auto;padding:24px}
        .head{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px}
        .head h1{margin:0;font-size:38px}.head p{margin:8px 0 0;color:var(--muted)}
        .btn{display:inline-flex;text-decoration:none;padding:12px 16px;border-radius:14px;font-weight:700;border:1px solid #cbd5e1;background:#fff;color:#0f172a}
        .layout{display:grid;grid-template-columns:1.2fr 1fr;gap:20px}
        .panel{background:rgba(255,255,255,.92);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.8);box-shadow:0 16px 50px rgba(15,23,42,.08);border-radius:26px;padding:22px}
        .panel h2{margin:0 0 8px;font-size:24px}.sub{margin:0 0 18px;color:var(--muted)}
        .grid2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .field{display:flex;flex-direction:column;gap:8px}.field.full{grid-column:1/-1}
        label{font-size:13px;font-weight:800;letter-spacing:.02em;color:#334155;text-transform:uppercase}
        input,select,textarea{width:100%;border:1px solid var(--line);border-radius:14px;padding:13px 14px;font-size:15px;background:#fff}
        textarea{min-height:100px;resize:vertical}
        .teeth-grid{display:grid;grid-template-columns:repeat(8,minmax(0,1fr));gap:10px}
        .tooth{background:#fff;border:1px solid var(--line);border-radius:18px;padding:12px;text-align:center;cursor:pointer;transition:.18s}
        .tooth:hover{transform:translateY(-2px);box-shadow:0 10px 18px rgba(37,99,235,.08)}
        .tooth input{display:none}
        .tooth .num{font-size:20px;font-weight:800;color:#1e293b}
        .tooth .mark{font-size:12px;color:var(--muted);margin-top:4px}
        .tooth input:checked + .box{outline:3px solid #93c5fd;background:linear-gradient(180deg,#eff6ff,#fff)}
        .box{border-radius:14px;padding:12px 8px}
        .items{margin-top:16px;border:1px solid var(--line);border-radius:18px;overflow:hidden}
        .items table{width:100%;border-collapse:collapse}.items th,.items td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left}.items th{background:#f8fafc;font-size:13px;text-transform:uppercase;letter-spacing:.02em}.items tfoot td{font-weight:800;background:#f8fafc}
        .toolbar{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
        .btn-primary{background:linear-gradient(135deg,var(--blue),var(--violet));color:#fff;border:none}
        .pill{display:inline-flex;padding:7px 10px;border-radius:999px;background:#dcfce7;color:#166534;font-weight:800;font-size:12px}
        .helper{margin-top:10px;color:var(--muted);font-size:14px}
        @media (max-width:1050px){.layout{grid-template-columns:1fr}.teeth-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media (max-width:640px){.grid2{grid-template-columns:1fr}.teeth-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.head h1{font-size:30px}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <div>
            <h1>Odontogram Pro + ICD-10</h1>
            <p>Pilih pasien, kunjungan, gigi, tindakan, dan tarif. Setelah disimpan, data langsung diteruskan ke billing.</p>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <a class="btn" href="dashboard.php">← Dashboard</a>
            <a class="btn" href="invoice.php">Buka Billing</a>
        </div>
    </div>

    <form action="simpan_odontogram.php" method="post" id="odontogramForm">
        <div class="layout">
            <div class="panel">
                <h2>Identitas Klinis</h2>
                <p class="sub">Data ini menjadi kepala transaksi klinis dan dasar pengiriman ke invoice.</p>
                <div class="grid2">
                    <div class="field">
                        <label>Pasien</label>
                        <select name="pasien_id" required>
                            <option value="">Pilih pasien</option>
                            <?php foreach ($pasienList as $p): ?>
                                <option value="<?= e($p['id']) ?>"><?= e($p['nama']) ?><?= $p['no_rm'] ? ' - RM ' . e($p['no_rm']) : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Kunjungan</label>
                        <select name="kunjungan_id">
                            <option value="">Pilih kunjungan</option>
                            <?php foreach ($kunjunganList as $k): ?>
                                <option value="<?= e($k['id']) ?>" data-pasien="<?= e($k['pasien_id']) ?>"><?= e($k['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Tanggal</label>
                        <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="field">
                        <label>Diagnosa ICD-10</label>
                        <select name="diagnosa_icd10" id="diagnosa_icd10" required>
                            <option value="">Pilih diagnosa</option>
                            <?php foreach ($icd10List as $d): ?>
                                <option value="<?= e($d['code']) ?>" data-name="<?= e($d['name']) ?>"><?= e($d['code']) ?> - <?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="nama_diagnosa" id="nama_diagnosa">
                    </div>
                    <div class="field full">
                        <label>Keluhan Utama</label>
                        <textarea name="keluhan_utama" placeholder="Contoh: nyeri spontan regio 36 sejak 2 hari"></textarea>
                    </div>
                    <div class="field full">
                        <label>Catatan Klinis</label>
                        <textarea name="catatan" placeholder="Catatan tambahan pemeriksaan intra oral, vitalitas, oklusi, dsb"></textarea>
                    </div>
                </div>

                <div style="margin-top:22px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                    <div>
                        <h2 style="margin:0">Peta Gigi</h2>
                        <div class="helper">Klik gigi yang akan diberi tindakan. Anda bisa memilih lebih dari satu.</div>
                    </div>
                    <span class="pill">Terintegrasi ke billing</span>
                </div>
                <div class="teeth-grid" style="margin-top:16px;">
                    <?php foreach ($defaultTeeth as $tooth): ?>
                        <label class="tooth">
                            <input type="checkbox" class="tooth-check" value="<?= $tooth ?>">
                            <div class="box">
                                <div class="num"><?= $tooth ?></div>
                                <div class="mark">Pilih</div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel">
                <h2>Tindakan per Gigi</h2>
                <p class="sub">Setelah memilih gigi, tentukan tindakan dan tarif. Baris yang ditambahkan akan dijumlah otomatis.</p>
                <div class="grid2">
                    <div class="field">
                        <label>Nomor Gigi</label>
                        <input type="text" id="nomor_gigi" placeholder="mis. 11, 21, 36" readonly>
                    </div>
                    <div class="field">
                        <label>Kondisi</label>
                        <input type="text" id="kondisi" placeholder="mis. karies profunda, missing, impaksi">
                    </div>
                    <div class="field">
                        <label>Tindakan</label>
                        <select id="tindakan">
                            <option value="">Pilih tindakan</option>
                            <?php foreach ($defaultActions as $name => $price): ?>
                                <option value="<?= e($name) ?>" data-tarif="<?= e($price) ?>"><?= e($name) ?> - Rp <?= number_format($price,0,',','.') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label>Tarif</label>
                        <input type="number" id="tarif" placeholder="0" min="0" step="1000">
                    </div>
                </div>
                <div class="toolbar">
                    <button type="button" class="btn btn-primary" id="addItem">Tambah ke Billing</button>
                    <button type="button" class="btn" id="clearSelected">Reset Pilihan Gigi</button>
                </div>

                <div class="items">
                    <table>
                        <thead>
                            <tr>
                                <th>Gigi</th>
                                <th>Kondisi</th>
                                <th>Tindakan</th>
                                <th>Tarif</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <tr><td colspan="5" style="text-align:center;color:#64748b;">Belum ada item tindakan</td></tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3">Total Billing Awal</td>
                                <td colspan="2" id="grandTotal">Rp 0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div id="hiddenInputs"></div>
                <input type="hidden" name="total_tagihan" id="total_tagihan" value="0">
                <div class="toolbar">
                    <button class="btn btn-primary" type="submit">Simpan & Lanjut ke Invoice</button>
                </div>
                <div class="helper">File ini menambahkan tabel odontogram dan odontogram_items bila belum ada. Setelah submit, halaman mengarah ke invoice dengan data ringkas billing.</div>
            </div>
        </div>
    </form>
</div>
<script>
const toothChecks = document.querySelectorAll('.tooth-check');
const nomorGigi = document.getElementById('nomor_gigi');
const tindakan = document.getElementById('tindakan');
const tarif = document.getElementById('tarif');
const kondisi = document.getElementById('kondisi');
const itemsBody = document.getElementById('itemsBody');
const hiddenInputs = document.getElementById('hiddenInputs');
const grandTotal = document.getElementById('grandTotal');
const totalTagihan = document.getElementById('total_tagihan');
const diagnosaSel = document.getElementById('diagnosa_icd10');
const namaDiagnosa = document.getElementById('nama_diagnosa');

let items = [];

function selectedTeeth(){
  return [...toothChecks].filter(x=>x.checked).map(x=>x.value);
}
function syncSelectedTeeth(){
  nomorGigi.value = selectedTeeth().join(', ');
}
function formatRupiah(n){
  return 'Rp ' + Number(n || 0).toLocaleString('id-ID');
}
function renderItems(){
  hiddenInputs.innerHTML = '';
  if(items.length === 0){
    itemsBody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#64748b;">Belum ada item tindakan</td></tr>';
  } else {
    itemsBody.innerHTML = items.map((it, idx)=>`<tr>
      <td>${it.nomor_gigi}</td>
      <td>${it.kondisi || '-'}</td>
      <td>${it.tindakan}</td>
      <td>${formatRupiah(it.tarif)}</td>
      <td><button type="button" class="btn" onclick="removeItem(${idx})">Hapus</button></td>
    </tr>`).join('');
  }
  let total = items.reduce((a,b)=>a + Number(b.tarif || 0), 0);
  grandTotal.textContent = formatRupiah(total);
  totalTagihan.value = total;
  items.forEach((it, idx)=>{
    hiddenInputs.insertAdjacentHTML('beforeend', `
      <input type="hidden" name="items[${idx}][nomor_gigi]" value="${it.nomor_gigi}">
      <input type="hidden" name="items[${idx}][kondisi]" value="${(it.kondisi || '').replace(/"/g,'&quot;')}">
      <input type="hidden" name="items[${idx}][tindakan]" value="${it.tindakan}">
      <input type="hidden" name="items[${idx}][tarif]" value="${it.tarif}">
    `);
  });
}
function removeItem(idx){ items.splice(idx,1); renderItems(); }
window.removeItem = removeItem;

toothChecks.forEach(chk=>chk.addEventListener('change', syncSelectedTeeth));
tindakan.addEventListener('change', ()=>{
  const opt = tindakan.options[tindakan.selectedIndex];
  if(opt && opt.dataset.tarif) tarif.value = opt.dataset.tarif;
});
diagnosaSel.addEventListener('change', ()=>{
  const opt = diagnosaSel.options[diagnosaSel.selectedIndex];
  namaDiagnosa.value = opt ? (opt.dataset.name || '') : '';
});

document.getElementById('addItem').addEventListener('click', ()=>{
  const selected = selectedTeeth();
  if(selected.length === 0){ alert('Pilih minimal satu gigi.'); return; }
  if(!tindakan.value){ alert('Pilih tindakan terlebih dahulu.'); return; }
  const tarifValue = Number(tarif.value || 0);
  selected.forEach(g=> items.push({ nomor_gigi:g, kondisi:kondisi.value.trim(), tindakan:tindakan.value, tarif:tarifValue }));
  renderItems();
});

document.getElementById('clearSelected').addEventListener('click', ()=>{
  toothChecks.forEach(x=>x.checked=false); syncSelectedTeeth();
});

document.getElementById('odontogramForm').addEventListener('submit', (e)=>{
  if(items.length === 0){ e.preventDefault(); alert('Tambahkan minimal satu tindakan ke billing.'); }
  if(!namaDiagnosa.value && diagnosaSel.value){
    const opt = diagnosaSel.options[diagnosaSel.selectedIndex];
    namaDiagnosa.value = opt ? (opt.dataset.name || '') : '';
  }
});
</script>
</body>
</html>
