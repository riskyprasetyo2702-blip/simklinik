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
    die("Koneksi gagal: " . $conn->connect_error);
}

$error = '';
$success = '';

if (isset($_POST['simpan'])) {
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
        $cek = $conn->prepare("SELECT id FROM patients WHERE no_rm = ? LIMIT 1");
        if (!$cek) {
            die("Prepare cek pasien gagal: " . $conn->error);
        }
        $cek->bind_param("s", $no_rm);
        $cek->execute();
        $cekRes = $cek->get_result();
        $existing = $cekRes->fetch_assoc();
        $cek->close();

        if ($existing) {
            $error = "No. Rekam Medis sudah terdaftar.";
        } else {
            $tanggal_db = ($tanggal_lahir !== '') ? $tanggal_lahir : null;

            $stmt = $conn->prepare("
                INSERT INTO patients (no_rm, nama, nik, jenis_kelamin, tanggal_lahir, no_hp, alamat)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            if (!$stmt) {
                die("Prepare insert pasien gagal: " . $conn->error);
            }

            $stmt->bind_param("sssssss", $no_rm, $nama, $nik, $jk, $tanggal_db, $no_hp, $alamat);

            if ($stmt->execute()) {
                header("Location: pasien.php?success=1");
                exit;
            } else {
                $error = "Gagal menyimpan pasien: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "Pasien berhasil disimpan.";
}

$data = $conn->query("SELECT * FROM patients ORDER BY id DESC");
if (!$data) {
    die("Query data pasien gagal: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <title>Data Pasien</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f4f7fb;">
<div class="container py-4">
    <div class="d-flex justify-content-between mb-3">
        <h3>Data Pasien</h3>
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
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">No. Rekam Medis</label>
                    <input class="form-control" name="no_rm" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Nama Pasien</label>
                    <input class="form-control" name="nama" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">NIK</label>
                    <input class="form-control" name="nik">
                </div>
                <div class="col-md-2">
                    <label class="form-label">JK</label>
                    <select class="form-select" name="jenis_kelamin">
                        <option value="">-</option>
                        <option value="L">L</option>
                        <option value="P">P</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tanggal Lahir</label>
                    <input class="form-control" type="date" name="tanggal_lahir">
                </div>
                <div class="col-md-3">
                    <label class="form-label">No HP</label>
                    <input class="form-control" name="no_hp">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Alamat</label>
                    <input class="form-control" name="alamat">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary" type="submit" name="simpan">Simpan Pasien</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead>
                        <tr>
                            <th>No RM</th>
                            <th>Nama</th>
                            <th>NIK</th>
                            <th>JK</th>
                            <th>No HP</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($row = $data->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['no_rm']) ?></td>
                            <td><?= htmlspecialchars($row['nama']) ?></td>
                            <td><?= htmlspecialchars($row['nik']) ?></td>
                            <td><?= htmlspecialchars($row['jenis_kelamin']) ?></td>
                            <td><?= htmlspecialchars($row['no_hp']) ?></td>
                            <td>
                                <a href="pasien_edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning">Ubah</a>
                                <a href="pasien_delete.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Hapus pasien ini? Riwayat kunjungan juga bisa ikut terhapus.')">Hapus</a>
                                <a href="pasien_history.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-info text-white">History</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</body>
</html>