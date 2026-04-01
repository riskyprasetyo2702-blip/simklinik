<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost","root","","simklinik");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    die("ID pasien tidak valid");
}

$error = '';

$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ? LIMIT 1");
if (!$stmt) {
    die("Prepare select pasien gagal: " . $conn->error);
}
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$pasien = $result->fetch_assoc();
$stmt->close();

if (!$pasien) {
    die("Pasien tidak ditemukan");
}

if (isset($_POST['update'])) {
    $no_rm = trim($_POST['no_rm'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $jk = trim($_POST['jenis_kelamin'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $no_hp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');

    if ($no_rm === '' || $nama === '') {
        $error = "No. Rekam Medis dan Nama Pasien wajib diisi.";
    } else {
        $cek = $conn->prepare("SELECT id FROM patients WHERE no_rm = ? AND id <> ? LIMIT 1");
        if (!$cek) {
            die("Prepare cek no_rm gagal: " . $conn->error);
        }
        $cek->bind_param("si", $no_rm, $id);
        $cek->execute();
        $cekRes = $cek->get_result();
        $dupe = $cekRes->fetch_assoc();
        $cek->close();

        if ($dupe) {
            $error = "No. Rekam Medis sudah dipakai pasien lain.";
        } else {
            $tanggal_db = ($tanggal_lahir !== '') ? $tanggal_lahir : null;

            $upd = $conn->prepare("
                UPDATE patients
                SET no_rm = ?, nama = ?, nik = ?, jenis_kelamin = ?, tanggal_lahir = ?, no_hp = ?, alamat = ?
                WHERE id = ?
            ");

            if (!$upd) {
                die("Prepare update pasien gagal: " . $conn->error);
            }

            $upd->bind_param("sssssssi", $no_rm, $nama, $nik, $jk, $tanggal_db, $no_hp, $alamat, $id);

            if ($upd->execute()) {
                header("Location: pasien.php?success=2");
                exit;
            } else {
                $error = "Gagal update pasien: " . $upd->error;
            }

            $upd->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Edit Pasien</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f4f7fb;">
<div class="container py-4">
    <div class="d-flex justify-content-between mb-3">
        <h3>Edit Pasien</h3>
        <a href="pasien.php" class="btn btn-secondary">Kembali</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <input type="hidden" name="id" value="<?= (int)$pasien['id'] ?>">

                <div class="col-md-3">
                    <label class="form-label">No. Rekam Medis</label>
                    <input class="form-control" name="no_rm" value="<?= htmlspecialchars($pasien['no_rm']) ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Nama Pasien</label>
                    <input class="form-control" name="nama" value="<?= htmlspecialchars($pasien['nama']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">NIK</label>
                    <input class="form-control" name="nik" value="<?= htmlspecialchars($pasien['nik']) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">JK</label>
                    <select class="form-select" name="jenis_kelamin">
                        <option value="">-</option>
                        <option value="L" <?= ($pasien['jenis_kelamin'] === 'L') ? 'selected' : '' ?>>L</option>
                        <option value="P" <?= ($pasien['jenis_kelamin'] === 'P') ? 'selected' : '' ?>>P</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Lahir</label>
                    <input class="form-control" type="date" name="tanggal_lahir" value="<?= htmlspecialchars($pasien['tanggal_lahir']) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">No HP</label>
                    <input class="form-control" name="no_hp" value="<?= htmlspecialchars($pasien['no_hp']) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Alamat</label>
                    <input class="form-control" name="alamat" value="<?= htmlspecialchars($pasien['alamat']) ?>">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit" name="update">Update Pasien</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>