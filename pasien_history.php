<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$conn = new mysqli("localhost","root","","simklinik");
if ($conn->connect_error) die("Koneksi gagal");

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$pasien = $result->fetch_assoc();
$stmt->close();

if (!$pasien) die("Pasien tidak ditemukan");

$visits = $conn->query("
    SELECT v.*, u.nama AS nama_dokter
    FROM visits v
    LEFT JOIN users u ON u.id = v.dokter_id
    WHERE v.patient_id = $id
    ORDER BY v.tanggal_kunjungan DESC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>History Pasien</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f4f7fb;">
<div class="container py-4">
    <div class="d-flex justify-content-between mb-3">
        <h3>History Pasien</h3>
        <a href="pasien.php" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="card shadow-sm border-0 rounded-4 mb-4">
        <div class="card-body">
            <h5><?= htmlspecialchars($pasien['nama']) ?></h5>
            <div>No RM: <?= htmlspecialchars($pasien['no_rm']) ?></div>
            <div>No HP: <?= htmlspecialchars($pasien['no_hp']) ?></div>
            <div>Alamat: <?= htmlspecialchars($pasien['alamat']) ?></div>
        </div>
    </div>

    <?php while($v = $visits->fetch_assoc()): ?>
        <?php
        $visit_id = (int)$v['id'];

        $invoice = $conn->query("SELECT * FROM invoices WHERE visit_id = $visit_id LIMIT 1")->fetch_assoc();

        $items = null;
        if ($invoice) {
            $invoice_id = (int)$invoice['id'];
            $items = $conn->query("
                SELECT ii.*, t.nama_tindakan
                FROM invoice_items ii
                JOIN treatments t ON t.id = ii.treatment_id
                WHERE ii.invoice_id = $invoice_id
                ORDER BY ii.id ASC
            ");
        }

        $odonto = $conn->query("
            SELECT tooth_number, surface_code, condition_code, status_type
            FROM odontogram_surfaces
            WHERE visit_id = $visit_id
            ORDER BY tooth_number ASC, surface_code ASC
        ");
        ?>

        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3">
                        <strong>Tanggal Kunjungan</strong><br>
                        <?= htmlspecialchars($v['tanggal_kunjungan']) ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Dokter</strong><br>
                        <?= htmlspecialchars($v['nama_dokter'] ?? '-') ?>
                    </div>
                    <div class="col-md-3">
                        <strong>Diagnosa</strong><br>
                        <?= htmlspecialchars($v['diagnosa'] ?? '-') ?>
                    </div>
                    <div class="col-md-3">
                        <strong>ICD-10</strong><br>
                        <?= htmlspecialchars(($v['icd10_code'] ?? '') . ' - ' . ($v['icd10_nama'] ?? '')) ?>
                    </div>
                </div>

                <div class="mb-3">
                    <strong>Keluhan:</strong><br>
                    <?= nl2br(htmlspecialchars($v['keluhan'] ?? '-')) ?>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>SOAP - Subjective</strong><br>
                        <?= nl2br(htmlspecialchars($v['subjective'] ?? '-')) ?>
                    </div>
                    <div class="col-md-6">
                        <strong>SOAP - Objective</strong><br>
                        <?= nl2br(htmlspecialchars($v['objective'] ?? '-')) ?>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>SOAP - Assessment</strong><br>
                        <?= nl2br(htmlspecialchars($v['assessment'] ?? '-')) ?>
                    </div>
                    <div class="col-md-6">
                        <strong>SOAP - Plan</strong><br>
                        <?= nl2br(htmlspecialchars($v['plan'] ?? '-')) ?>
                    </div>
                </div>

                <hr>

                <h6>Odontogram / Permukaan Gigi</h6>
                <table class="table table-sm table-bordered mb-4">
                    <thead>
                        <tr>
                            <th>Gigi</th>
                            <th>Surface</th>
                            <th>Kondisi</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($o = $odonto->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($o['tooth_number']) ?></td>
                            <td><?= htmlspecialchars($o['surface_code']) ?></td>
                            <td><?= htmlspecialchars($o['condition_code']) ?></td>
                            <td><?= htmlspecialchars($o['status_type']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>

                <h6>History Biaya / Billing</h6>
                <?php if ($invoice): ?>
                    <div class="mb-2">
                        <strong>No Invoice:</strong> <?= htmlspecialchars($invoice['nomor_invoice']) ?><br>
                        <strong>Status Bayar:</strong> <?= htmlspecialchars($invoice['status_bayar']) ?><br>
                        <strong>Total:</strong> Rp <?= number_format($invoice['total'], 0, ',', '.') ?>
                    </div>

                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Tindakan</th>
                                <th>Gigi</th>
                                <th>Surface</th>
                                <th>Sumber</th>
                                <th>Qty</th>
                                <th>Harga</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($it = $items->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($it['nama_tindakan']) ?></td>
                                <td><?= htmlspecialchars($it['tooth_number'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($it['surface_code'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($it['sumber'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($it['qty']) ?></td>
                                <td>Rp <?= number_format($it['harga'], 0, ',', '.') ?></td>
                                <td>Rp <?= number_format($it['subtotal'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="alert alert-secondary mb-0">Belum ada billing pada kunjungan ini.</div>
                <?php endif; ?>
            </div>
        </div>
    <?php endwhile; ?>
</div>
</body>
</html>