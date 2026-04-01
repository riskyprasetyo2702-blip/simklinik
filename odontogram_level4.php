<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "simklinik");
if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}

$visits = $conn->query("
    SELECT 
        v.id,
        v.patient_id,
        p.nama,
        p.no_rm,
        v.tanggal_kunjungan
    FROM visits v
    JOIN patients p ON p.id = v.patient_id
    ORDER BY v.id DESC
");

if (!$visits) {
    die("Query visits gagal: " . $conn->error);
}

/*
 * Master tindakan odontogram
 * Pastikan tabel tindakan sudah berisi daftar harga Tiga Dental
 */
$treatments = $conn->query("
    SELECT 
        id,
        kode,
        nama_tindakan,
        kategori,
        harga,
        NULL AS harga_min,
        NULL AS harga_max,
        'per tindakan' AS satuan_harga,
        NULL AS keterangan
    FROM tindakan
    ORDER BY kategori ASC, nama_tindakan ASC
");

if (!$treatments) {
    die("Query tindakan gagal: " . $conn->error);
}

$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$patient_id = 0;

/*
 * Ambil info visit aktif
 */
$activeVisit = null;
if ($visit_id > 0) {
    $stmtVisit = $conn->prepare("
        SELECT 
            v.id,
            v.patient_id,
            p.nama,
            p.no_rm,
            v.tanggal_kunjungan
        FROM visits v
        JOIN patients p ON p.id = v.patient_id
        WHERE v.id = ?
        LIMIT 1
    ");
    $stmtVisit->bind_param("i", $visit_id);
    $stmtVisit->execute();
    $activeVisit = $stmtVisit->get_result()->fetch_assoc();
    $stmtVisit->close();

    if ($activeVisit) {
        $patient_id = (int)$activeVisit['patient_id'];
    }
}

$surfaceData = [];
if ($visit_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            tooth_number, 
            surface_code, 
            condition_code, 
            status_type
        FROM odontogram_surfaces
        WHERE visit_id = ?
    ");
    $stmt->bind_param("i", $visit_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $surfaceData[$row['tooth_number']][$row['surface_code']] = [
            'condition_code' => $row['condition_code'],
            'status_type' => $row['status_type']
        ];
    }
    $stmt->close();
}

/*
 * Ambil daftar tindakan yang sudah tersimpan untuk visit ini
 */
$odontogramActions = null;
if ($visit_id > 0) {
    $odontogramActions = $conn->query("
        SELECT *
        FROM odontogram_tindakan
        WHERE kunjungan_id = {$visit_id}
        ORDER BY id DESC
    ");

    if (!$odontogramActions) {
        die("Query odontogram_tindakan gagal: " . $conn->error);
    }
}

function surfaceClass($tooth, $surface, $surfaceData) {
    if (!isset($surfaceData[$tooth][$surface])) {
        return 'surface-empty';
    }

    $code = strtoupper($surfaceData[$tooth][$surface]['condition_code']);

    if (
        str_contains($code, 'KONS') ||
        str_contains($code, 'PEMR') ||
        str_contains($code, 'KONSULT') ||
        str_contains($code, 'PERIKSA')
    ) {
        return 'bg-primary text-white';
    }

    if (
        str_contains($code, 'SC') ||
        str_contains($code, 'SCAL') ||
        str_contains($code, 'PER')
    ) {
        return 'bg-info text-dark';
    }

    if (
        str_contains($code, 'TMB') ||
        str_contains($code, 'FILL') ||
        str_contains($code, 'KOMPOSIT') ||
        str_contains($code, 'GIC')
    ) {
        return 'bg-success text-white';
    }

    if (
        str_contains($code, 'RCT') ||
        str_contains($code, 'ENDO') ||
        str_contains($code, 'PSA') ||
        str_contains($code, 'PULPEKTOMI')
    ) {
        return 'bg-warning text-dark';
    }

    if (
        str_contains($code, 'CRN') ||
        str_contains($code, 'CROWN') ||
        str_contains($code, 'VENEER') ||
        str_contains($code, 'ONLAY')
    ) {
        return 'bg-dark text-white';
    }

    if (
        str_contains($code, 'EXT') ||
        str_contains($code, 'CABUT') ||
        str_contains($code, 'ODONTEKTOMI')
    ) {
        return 'bg-danger text-white';
    }

    if (
        str_contains($code, 'ORTH') ||
        str_contains($code, 'BRACKET') ||
        str_contains($code, 'DAMON')
    ) {
        return 'bg-secondary text-white';
    }

    return 'bg-secondary text-white';
}

$teeth = [
    ['18','17','16','15','14','13','12','11'],
    ['21','22','23','24','25','26','27','28'],
    ['48','47','46','45','44','43','42','41'],
    ['31','32','33','34','35','36','37','38'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odontogram PRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #eef2f7; }
        .tooth-card {
            width: 96px;
            background: #fff;
            border-radius: 18px;
            padding: 8px;
            box-shadow: 0 4px 14px rgba(0,0,0,.08);
        }
        .tooth-label {
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 6px;
        }
        .surface-grid {
            display: grid;
            grid-template-columns: 24px 24px 24px;
            grid-template-rows: 24px 24px 24px;
            gap: 4px;
            justify-content: center;
        }
        .surface {
            border: 1px solid #cfd6df;
            border-radius: 5px;
            cursor: pointer;
            font-size: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            user-select: none;
            transition: .15s ease;
        }
        .surface:hover { transform: scale(1.05); }
        .surface-empty { background: #f8f9fa; }
        .surface-b { grid-column: 2; grid-row: 1; }
        .surface-m { grid-column: 1; grid-row: 2; }
        .surface-o { grid-column: 2; grid-row: 2; }
        .surface-d { grid-column: 3; grid-row: 2; }
        .surface-l { grid-column: 2; grid-row: 3; }
        .teeth-row {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 16px;
        }
        .legend-box {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            display: inline-block;
            margin-right: 6px;
        }
        .surface.active-selected {
            outline: 3px solid #0d6efd;
            outline-offset: 1px;
        }
        .price-note {
            font-size: 12px;
            color: #6c757d;
        }
        .small-muted {
            font-size: 12px;
            color: #6c757d;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Odontogram PRO</h3>
        <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Pilih Kunjungan</label>
                    <select name="visit_id" class="form-select" onchange="this.form.submit()" required>
                        <option value="">Pilih kunjungan</option>
                        <?php while($v = $visits->fetch_assoc()): ?>
                            <option value="<?= (int)$v['id'] ?>" <?= ($visit_id === (int)$v['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['no_rm'] . ' - ' . $v['nama'] . ' - ' . $v['tanggal_kunjungan']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>

            <?php if ($activeVisit): ?>
                <div class="alert alert-light border mt-3 mb-0">
                    <strong>Pasien:</strong> <?= htmlspecialchars($activeVisit['nama']) ?><br>
                    <strong>No. RM:</strong> <?= htmlspecialchars($activeVisit['no_rm']) ?><br>
                    <strong>Tanggal Kunjungan:</strong> <?= htmlspecialchars($activeVisit['tanggal_kunjungan']) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-3">
                <div><span class="legend-box bg-success"></span>Tambal / Restorasi</div>
                <div><span class="legend-box bg-warning"></span>RCT / Endo</div>
                <div><span class="legend-box bg-dark"></span>Crown / Veneer / Onlay</div>
                <div><span class="legend-box bg-danger"></span>Cabut / Bedah</div>
                <div><span class="legend-box bg-info"></span>Scaling / Perio</div>
                <div><span class="legend-box bg-primary"></span>Konsultasi / Pemeriksaan</div>
                <div><span class="legend-box bg-secondary"></span>Ortho / Lainnya</div>
            </div>
        </div>
    </div>

    <?php if ($visit_id > 0): ?>
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body">
                <h5 class="mb-3">Rahang Atas</h5>
                <?php foreach (array_slice($teeth, 0, 2) as $row): ?>
                    <div class="teeth-row">
                        <?php foreach ($row as $tooth): ?>
                            <div class="tooth-card">
                                <div class="tooth-label"><?= $tooth ?></div>
                                <div class="surface-grid">
                                    <?php foreach (['B','M','O','D','L'] as $surface): ?>
                                        <div
                                            class="surface surface-<?= strtolower($surface) ?> <?= surfaceClass($tooth, $surface, $surfaceData) ?>"
                                            data-visit="<?= (int)$visit_id ?>"
                                            data-patient="<?= (int)$patient_id ?>"
                                            data-tooth="<?= htmlspecialchars($tooth) ?>"
                                            data-surface="<?= htmlspecialchars($surface) ?>"
                                            title="<?= htmlspecialchars($tooth . ' - ' . $surface) ?>"
                                        >
                                            <?= htmlspecialchars($surface) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>

                <h5 class="mt-4 mb-3">Rahang Bawah</h5>
                <?php foreach (array_slice($teeth, 2, 2) as $row): ?>
                    <div class="teeth-row">
                        <?php foreach ($row as $tooth): ?>
                            <div class="tooth-card">
                                <div class="tooth-label"><?= $tooth ?></div>
                                <div class="surface-grid">
                                    <?php foreach (['B','M','O','D','L'] as $surface): ?>
                                        <div
                                            class="surface surface-<?= strtolower($surface) ?> <?= surfaceClass($tooth, $surface, $surfaceData) ?>"
                                            data-visit="<?= (int)$visit_id ?>"
                                            data-patient="<?= (int)$patient_id ?>"
                                            data-tooth="<?= htmlspecialchars($tooth) ?>"
                                            data-surface="<?= htmlspecialchars($surface) ?>"
                                            title="<?= htmlspecialchars($tooth . ' - ' . $surface) ?>"
                                        >
                                            <?= htmlspecialchars($surface) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body">
                <h5 class="mb-3">Riwayat Tindakan Odontogram</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Gigi</th>
                                <th>Surface</th>
                                <th>Tindakan</th>
                                <th>Kategori</th>
                                <th>Harga</th>
                                <th>Qty</th>
                                <th>Subtotal</th>
                                <th>Satuan</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($odontogramActions && $odontogramActions->num_rows > 0): ?>
                            <?php $no = 1; while($row = $odontogramActions->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= htmlspecialchars($row['nomor_gigi']) ?></td>
                                    <td><?= htmlspecialchars($row['surface_code'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['nama_tindakan']) ?></td>
                                    <td><?= htmlspecialchars($row['kategori']) ?></td>
                                    <td>Rp <?= number_format((int)$row['harga'], 0, ',', '.') ?></td>
                                    <td><?= (int)$row['qty'] ?></td>
                                    <td>Rp <?= number_format((int)$row['subtotal'], 0, ',', '.') ?></td>
                                    <td><?= htmlspecialchars($row['satuan_harga'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['catatan'] ?? '-') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted">Belum ada tindakan tersimpan</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="surfaceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content rounded-4">
            <div class="modal-header">
                <h5 class="modal-title">Pilih Tindakan Odontogram</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Target Gigi</label>
                    <input type="text" id="modal_target" class="form-control" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Tindakan</label>
                    <select id="condition_code" class="form-select">
                        <option value="">Pilih tindakan</option>
                        <?php
                        $currentKategori = '';
                        $treatments->data_seek(0);
                        while($t = $treatments->fetch_assoc()):
                            if ($currentKategori !== $t['kategori']):
                                if ($currentKategori !== '') echo '</optgroup>';
                                $currentKategori = $t['kategori'];
                                echo '<optgroup label="'.htmlspecialchars($currentKategori ?: 'Lainnya').'">';
                            endif;

                            $hargaText = '';
                            if ((int)$t['harga'] > 0) {
                                $hargaText = 'Rp ' . number_format((int)$t['harga'], 0, ',', '.');
                            } elseif ((int)$t['harga_min'] > 0 || (int)$t['harga_max'] > 0) {
                                $hargaText = 'Rp ' . number_format((int)$t['harga_min'], 0, ',', '.') . ' - Rp ' . number_format((int)$t['harga_max'], 0, ',', '.');
                            } else {
                                $hargaText = 'Harga belum ditentukan';
                            }
                        ?>
                            <option 
                                value="<?= htmlspecialchars($t['kode']) ?>"
                                data-id="<?= (int)$t['id'] ?>"
                                data-kode="<?= htmlspecialchars($t['kode']) ?>"
                                data-nama="<?= htmlspecialchars($t['nama_tindakan']) ?>"
                                data-kategori="<?= htmlspecialchars($t['kategori']) ?>"
                                data-harga="<?= (int)$t['harga'] ?>"
                                data-harga_min="<?= (int)($t['harga_min'] ?? 0) ?>"
                                data-harga_max="<?= (int)($t['harga_max'] ?? 0) ?>"
                                data-satuan="<?= htmlspecialchars($t['satuan_harga'] ?? 'per tindakan') ?>"
                                data-keterangan="<?= htmlspecialchars($t['keterangan'] ?? '') ?>"
                            >
                                <?= htmlspecialchars($t['nama_tindakan']) ?> - <?= htmlspecialchars($hargaText) ?>
                            </option>
                        <?php endwhile; if ($currentKategori !== '') echo '</optgroup>'; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Harga</label>
                    <input type="number" id="modal_harga" class="form-control" value="0" min="0">
                    <div class="price-note mt-1" id="modal_price_note">Harga akan otomatis terisi.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Qty</label>
                    <input type="number" id="modal_qty" class="form-control" value="1" min="1">
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

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="send_to_billing" checked>
                    <label class="form-check-label" for="send_to_billing">
                        Hubungkan ke billing otomatis
                    </label>
                </div>

                <div class="form-text">
                    Tindakan dipilih sesuai master harga Tiga Dental, dan bisa disimpan ke odontogram serta billing.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="saveSurfaceBtn">Simpan</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let selectedEl = null;
const modal = new bootstrap.Modal(document.getElementById('surfaceModal'));
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
    const subtotal = harga * qty;
    subtotalText.value = formatRupiah(subtotal);
}

function applyTreatmentMeta() {
    const selected = tindakanSelect.options[tindakanSelect.selectedIndex];
    if (!selected || !selected.value) {
        hargaInput.value = 0;
        satuanInput.value = '-';
        noteBox.textContent = 'Harga akan otomatis terisi.';
        updateSubtotal();
        return;
    }

    const harga = parseInt(selected.dataset.harga || '0', 10);
    const hargaMin = parseInt(selected.dataset.harga_min || '0', 10);
    const hargaMax = parseInt(selected.dataset.harga_max || '0', 10);
    const satuan = selected.dataset.satuan || 'per tindakan';
    const keterangan = selected.dataset.keterangan || '';

    satuanInput.value = satuan;

    if (harga > 0) {
        hargaInput.value = harga;
        noteBox.textContent = 'Harga standar: ' + formatRupiah(harga) + (keterangan ? ' | ' + keterangan : '');
    } else if (hargaMin > 0 || hargaMax > 0) {
        hargaInput.value = hargaMin > 0 ? hargaMin : 0;
        noteBox.textContent = 'Range harga: ' + formatRupiah(hargaMin) + ' - ' + formatRupiah(hargaMax) + (keterangan ? ' | ' + keterangan : '');
    } else {
        hargaInput.value = 0;
        noteBox.textContent = 'Harga belum ditentukan' + (keterangan ? ' | ' + keterangan : '');
    }

    updateSubtotal();
}

document.querySelectorAll('.surface').forEach(el => {
    el.addEventListener('click', function () {
        document.querySelectorAll('.surface').forEach(s => s.classList.remove('active-selected'));
        this.classList.add('active-selected');
        selectedEl = this;

        document.getElementById('modal_target').value = this.dataset.tooth + '-' + this.dataset.surface;
        tindakanSelect.value = '';
        hargaInput.value = 0;
        qtyInput.value = 1;
        document.getElementById('modal_catatan').value = '';
        satuanInput.value = '-';
        noteBox.textContent = 'Harga akan otomatis terisi.';
        updateSubtotal();

        modal.show();
    });
});

tindakanSelect.addEventListener('change', applyTreatmentMeta);
hargaInput.addEventListener('input', updateSubtotal);
qtyInput.addEventListener('input', updateSubtotal);

document.getElementById('saveSurfaceBtn').addEventListener('click', function () {
    if (!selectedEl) return;

    const selectedOption = tindakanSelect.options[tindakanSelect.selectedIndex];
    const tindakanId = parseInt(selectedOption?.dataset?.id || '0', 10);
    const conditionCode = tindakanSelect.value;
    const visitId = parseInt(selectedEl.dataset.visit || '0', 10);
    const patientId = parseInt(selectedEl.dataset.patient || '0', 10);
    const tooth = selectedEl.dataset.tooth;
    const surface = selectedEl.dataset.surface;
    const harga = parseInt(hargaInput.value || '0', 10);
    const qty = parseInt(qtyInput.value || '1', 10);
    const satuan = selectedOption?.dataset?.satuan || 'per tindakan';
    const catatan = document.getElementById('modal_catatan').value || '';
    const sendToBilling = document.getElementById('send_to_billing').checked ? '1' : '0';

    if (!conditionCode || tindakanId <= 0) {
        alert('Pilih tindakan dulu');
        return;
    }

    const formData = new URLSearchParams();
    formData.append('visit_id', visitId);
    formData.append('patient_id', patientId);
    formData.append('tooth_number', tooth);
    formData.append('surface_code', surface);
    formData.append('condition_code', conditionCode);
    formData.append('status_type', 'completed');
    formData.append('send_to_billing', sendToBilling);
    formData.append('tindakan_id', tindakanId);
    formData.append('harga', harga);
    formData.append('qty', qty);
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
        alert('Gagal menyimpan tindakan odontogram');
    });
});
</script>
</body>
</html>