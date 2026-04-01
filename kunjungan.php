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

$error = '';
$success = '';

if (isset($_POST['simpan'])) {
    $patient_id     = (int)($_POST['patient_id'] ?? 0);
    $tanggal        = trim($_POST['tanggal_kunjungan'] ?? '');
    $keluhan        = trim($_POST['keluhan'] ?? '');
    $subjective     = trim($_POST['subjective'] ?? '');
    $objective      = trim($_POST['objective'] ?? '');
    $assessment     = trim($_POST['assessment'] ?? '');
    $plan           = trim($_POST['plan'] ?? '');
    $diagnosa       = trim($_POST['diagnosa'] ?? '');
    $icd10_code     = trim($_POST['icd10_code'] ?? '');
    $icd10_nama     = trim($_POST['icd10_nama'] ?? '');
    $dokter_id      = (int)($_SESSION['user_id'] ?? 0);

    if ($patient_id <= 0 || $tanggal === '') {
        $error = "Pasien dan tanggal kunjungan wajib diisi.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO visits (
                patient_id,
                dokter_id,
                tanggal_kunjungan,
                keluhan,
                subjective,
                objective,
                assessment,
                plan,
                diagnosa,
                icd10_code,
                icd10_nama,
                status_kunjungan
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft')
        ");

        if (!$stmt) {
            $error = "Prepare statement gagal: " . $conn->error;
        } else {
            $stmt->bind_param(
                "iisssssssss",
                $patient_id,
                $dokter_id,
                $tanggal,
                $keluhan,
                $subjective,
                $objective,
                $assessment,
                $plan,
                $diagnosa,
                $icd10_code,
                $icd10_nama
            );

            if ($stmt->execute()) {
                header("Location: kunjungan.php?success=1");
                exit;
            } else {
                $error = "Gagal menyimpan kunjungan: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "Kunjungan berhasil disimpan.";
}

$patients = $conn->query("
    SELECT id, no_rm, nama
    FROM patients
    ORDER BY nama ASC
");

if (!$patients) {
    die("Query patients gagal: " . $conn->error);
}

$visits = $conn->query("
    SELECT 
        v.*,
        p.nama AS nama_pasien,
        p.no_rm,
        u.nama AS nama_dokter
    FROM visits v
    JOIN patients p ON p.id = v.patient_id
    LEFT JOIN users u ON u.id = v.dokter_id
    ORDER BY v.id DESC
");

if (!$visits) {
    die("Query visits gagal: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kunjungan Pasien</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #eef2f7; }
        .search-results { max-height: 220px; overflow-y: auto; position: relative; z-index: 10; }
        .small-muted { font-size: 12px; color: #6c757d; }
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Kunjungan Pasien</h3>
        <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <h5 class="mb-3">Form Kunjungan</h5>

            <form method="POST" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Pasien</label>
                    <select class="form-select" name="patient_id" required>
                        <option value="">Pilih pasien</option>
                        <?php while ($p = $patients->fetch_assoc()): ?>
                            <option value="<?= (int)$p['id'] ?>">
                                <?= htmlspecialchars($p['no_rm'] . ' - ' . $p['nama']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Tanggal Kunjungan</label>
                    <input class="form-control" type="datetime-local" name="tanggal_kunjungan" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Keluhan Utama</label>
                    <input class="form-control" name="keluhan" placeholder="Contoh: gigi kanan atas sakit">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Subjective</label>
                    <textarea class="form-control" name="subjective" rows="3"></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Objective</label>
                    <textarea class="form-control" name="objective" rows="3"></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Assessment</label>
                    <textarea class="form-control" name="assessment" rows="3"></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Plan</label>
                    <textarea class="form-control" name="plan" rows="3"></textarea>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Diagnosa Klinis</label>
                    <input class="form-control" name="diagnosa" id="diagnosa" placeholder="Akan otomatis terisi saat pilih ICD-10">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Cari ICD-10</label>
                    <input type="text" id="icd10_search" class="form-control" placeholder="Ketik kode / diagnosis">
                    <div id="icd10_results" class="list-group mt-1 search-results"></div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Kode ICD-10</label>
                    <input type="text" name="icd10_code" id="icd10_code" class="form-control" readonly>
                </div>

                <div class="col-md-9">
                    <label class="form-label">Nama Diagnosis ICD-10</label>
                    <input type="text" name="icd10_nama" id="icd10_nama" class="form-control" readonly>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary" type="submit" name="simpan">Simpan Kunjungan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body">
            <h5 class="mb-3">Daftar Kunjungan</h5>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>No RM</th>
                            <th>Pasien</th>
                            <th>Dokter</th>
                            <th>Keluhan</th>
                            <th>Diagnosa</th>
                            <th>ICD-10</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while ($v = $visits->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($v['tanggal_kunjungan']) ?></td>
                            <td><?= htmlspecialchars($v['no_rm']) ?></td>
                            <td><?= htmlspecialchars($v['nama_pasien']) ?></td>
                            <td><?= htmlspecialchars($v['nama_dokter'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($v['keluhan'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($v['diagnosa'] ?? '-') ?></td>
                            <td>
                                <?php if (!empty($v['icd10_code'])): ?>
                                    <div><strong><?= htmlspecialchars($v['icd10_code']) ?></strong></div>
                                    <div class="small-muted"><?= htmlspecialchars($v['icd10_nama']) ?></div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($v['status_kunjungan'] ?? '-') ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="kunjungan_detail.php?id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-info text-white">Detail</a>
                                    <a href="invoice.php?visit_id=<?= (int)$v['id'] ?>" class="btn btn-sm btn-success">Invoice</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>

<script>
const searchInput = document.getElementById('icd10_search');
const resultsBox = document.getElementById('icd10_results');
const kodeInput = document.getElementById('icd10_code');
const namaInput = document.getElementById('icd10_nama');
const diagnosaInput = document.getElementById('diagnosa');

if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        const q = this.value.trim();

        if (q.length < 2) {
            resultsBox.innerHTML = '';
            return;
        }

        fetch('icd10_search.php?q=' + encodeURIComponent(q))
            .then(res => res.text())
            .then(data => {
                resultsBox.innerHTML = data;

                document.querySelectorAll('.icd-item').forEach(item => {
                    item.addEventListener('click', function() {
                        const kode = this.dataset.kode || '';
                        const nama = this.dataset.nama || '';

                        kodeInput.value = kode;
                        namaInput.value = nama;
                        searchInput.value = kode + ' - ' + nama;
                        resultsBox.innerHTML = '';

                        if (diagnosaInput && diagnosaInput.value.trim() === '') {
                            diagnosaInput.value = nama;
                        }
                    });
                });
            })
            .catch(() => {
                resultsBox.innerHTML = '<div class="list-group-item text-danger">Pencarian ICD-10 gagal</div>';
            });
    });
}
</script>
</body>
</html>