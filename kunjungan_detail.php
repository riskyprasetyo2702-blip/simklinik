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

$kunjungan_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($kunjungan_id <= 0) {
    die("Kunjungan tidak valid");
}

$q = $conn->query("
    SELECT 
        v.*,
        p.nama,
        p.no_rm
    FROM visits v
    JOIN patients p ON p.id = v.patient_id
    WHERE v.id = {$kunjungan_id}
");

if (!$q) {
    die("Query kunjungan gagal: " . $conn->error);
}

$kunjungan = $q->fetch_assoc();
if (!$kunjungan) {
    die("Data kunjungan tidak ditemukan");
}

$pasien_id = (int)$kunjungan['patient_id'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Medis & Surat Sakit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f5f7fb; }
        .card { border:0; border-radius:18px; box-shadow:0 4px 16px rgba(0,0,0,.08); }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Resume Medis & Surat Sakit</h3>
        <a href="kunjungan.php" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="card p-4 mb-4">
        <h5>Info Kunjungan</h5>
        <p class="mb-1"><strong>No RM:</strong> <?= htmlspecialchars($kunjungan['no_rm']) ?></p>
        <p class="mb-1"><strong>Pasien:</strong> <?= htmlspecialchars($kunjungan['nama']) ?></p>
        <p class="mb-1"><strong>Tanggal:</strong> <?= htmlspecialchars($kunjungan['tanggal_kunjungan']) ?></p>
        <p class="mb-0"><strong>Keluhan:</strong> <?= htmlspecialchars($kunjungan['keluhan'] ?: '-') ?></p>
    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card p-4">
                <h5 class="mb-3">Resume Medis</h5>
                <form method="POST" action="save_resume_medis.php">
                    <input type="hidden" name="pasien_id" value="<?= $pasien_id ?>">
                    <input type="hidden" name="kunjungan_id" value="<?= $kunjungan_id ?>">

                    <div class="mb-3">
                        <label class="form-label">Keluhan Utama</label>
                        <textarea class="form-control" name="keluhan_utama" rows="3"><?= htmlspecialchars($kunjungan['keluhan'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Anamnesis</label>
                        <textarea class="form-control" name="anamnesis" rows="3"><?= htmlspecialchars($kunjungan['subjective'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Pemeriksaan</label>
                        <textarea class="form-control" name="pemeriksaan" rows="3"><?= htmlspecialchars($kunjungan['objective'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Diagnosa</label>
                        <input type="text" class="form-control" name="diagnosa" value="<?= htmlspecialchars($kunjungan['diagnosa'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Kode ICD-10</label>
                        <input type="text" class="form-control" name="icd10_code" value="<?= htmlspecialchars($kunjungan['icd10_code'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tindakan</label>
                        <textarea class="form-control" name="tindakan" rows="3"><?= htmlspecialchars($kunjungan['plan'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Terapi</label>
                        <textarea class="form-control" name="terapi" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Instruksi / Edukasi</label>
                        <textarea class="form-control" name="instruksi" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" name="catatan" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Dokter</label>
                        <input type="text" class="form-control" name="dokter_nama" value="drg. Nama Dokter" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">No. SIP</label>
                        <input type="text" class="form-control" name="dokter_sip">
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan Resume Medis</button>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card p-4">
                <h5 class="mb-3">Surat Sakit</h5>
                <form method="POST" action="save_surat_sakit.php">
                    <input type="hidden" name="pasien_id" value="<?= $pasien_id ?>">
                    <input type="hidden" name="kunjungan_id" value="<?= $kunjungan_id ?>">

                    <div class="mb-3">
                        <label class="form-label">Tanggal Surat</label>
                        <input type="date" class="form-control" name="tanggal_surat" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal Mulai Istirahat</label>
                        <input type="date" class="form-control" name="tanggal_mulai" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Tanggal Selesai Istirahat</label>
                        <input type="date" class="form-control" name="tanggal_selesai" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Diagnosis Singkat</label>
                        <input type="text" class="form-control" name="diagnosis_singkat" value="<?= htmlspecialchars($kunjungan['diagnosa'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea class="form-control" name="keterangan" rows="4">Disarankan istirahat dan menghindari aktivitas berat selama masa pemulihan.</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nama Dokter</label>
                        <input type="text" class="form-control" name="dokter_nama" value="drg. Nama Dokter" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">No. SIP</label>
                        <input type="text" class="form-control" name="dokter_sip">
                    </div>

                    <button type="submit" class="btn btn-success">Buat Surat Sakit</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>