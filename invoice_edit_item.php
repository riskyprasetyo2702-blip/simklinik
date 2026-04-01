<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$conn = new mysqli("localhost","root","","simklinik");
if ($conn->connect_error) {
    die("Koneksi gagal");
}

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT ii.*, COALESCE(ii.nama_tindakan, t.nama_tindakan) AS nama_tindakan
    FROM invoice_items ii
    LEFT JOIN treatments t ON t.id = ii.treatment_id
    WHERE ii.id = ?
    LIMIT 1
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$item = $result->fetch_assoc();
$stmt->close();

if (!$item) {
    die("Item invoice tidak ditemukan");
}

$treatments = $conn->query("
    SELECT id, nama_tindakan, harga
    FROM treatments
    ORDER BY nama_tindakan ASC
");

if (isset($_POST['simpan'])) {
    $treatment_id = (int)($_POST['treatment_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);
    $harga = (float)($_POST['harga'] ?? 0);

    if ($qty < 1) $qty = 1;
    if ($harga < 0) $harga = 0;

    $subtotal = $qty * $harga;

    $getTreatment = $conn->prepare("SELECT nama_tindakan FROM treatments WHERE id = ? LIMIT 1");
    $getTreatment->bind_param("i", $treatment_id);
    $getTreatment->execute();
    $treatmentRow = $getTreatment->get_result()->fetch_assoc();
    $getTreatment->close();
    $nama_tindakan = $treatmentRow['nama_tindakan'] ?? null;

    $up = $conn->prepare("
        UPDATE invoice_items
        SET treatment_id = ?, nama_tindakan = ?, qty = ?, harga = ?, subtotal = ?
        WHERE id = ?
    ");
    $up->bind_param("isiddi", $treatment_id, $nama_tindakan, $qty, $harga, $subtotal, $id);
    $up->execute();
    $up->close();

    $invoice_id = (int)$item['invoice_id'];

    $sum = $conn->prepare("
        SELECT COALESCE(SUM(subtotal),0) AS subtotal_baru
        FROM invoice_items
        WHERE invoice_id = ?
    ");
    $sum->bind_param("i", $invoice_id);
    $sum->execute();
    $sumRes = $sum->get_result()->fetch_assoc();
    $sum->close();

    $getInv = $conn->prepare("
        SELECT COALESCE(diskon,0) AS diskon
        FROM invoices
        WHERE id = ?
    ");
    $getInv->bind_param("i", $invoice_id);
    $getInv->execute();
    $invRes = $getInv->get_result()->fetch_assoc();
    $getInv->close();

    $subtotalBaru = (float)$sumRes['subtotal_baru'];
    $diskon = (float)$invRes['diskon'];
    $totalBaru = $subtotalBaru - $diskon;
    if ($totalBaru < 0) $totalBaru = 0;

    $updInv = $conn->prepare("
        UPDATE invoices
        SET subtotal = ?, total = ?
        WHERE id = ?
    ");
    $updInv->bind_param("ddi", $subtotalBaru, $totalBaru, $invoice_id);
    $updInv->execute();
    $updInv->close();

    header("Location: invoice.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Item Invoice</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#eef2f7;">
<div class="container py-4">
    <div class="d-flex justify-content-between mb-3">
        <h3>Edit Item Invoice</h3>
        <a href="invoice.php" class="btn btn-secondary">Kembali</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Tindakan</label>
                    <select name="treatment_id" class="form-select" required>
                        <?php while($t = $treatments->fetch_assoc()): ?>
                            <option value="<?= $t['id'] ?>" <?= ($t['id'] == $item['treatment_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nama_tindakan']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Qty</label>
                    <input type="number" name="qty" class="form-control" min="1" value="<?= htmlspecialchars($item['qty']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Harga</label>
                    <input type="number" step="0.01" name="harga" class="form-control" min="0" value="<?= htmlspecialchars($item['harga']) ?>" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Gigi</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($item['tooth_number'] ?? '-') ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Surface</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($item['surface_code'] ?? '-') ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Sumber</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($item['sumber'] ?? '-') ?>" readonly>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Subtotal Baru</label>
                    <input type="text" class="form-control" value="Rp <?= number_format((float)$item['subtotal'],0,',','.') ?>" readonly>
                </div>

                <div class="col-12">
                    <button class="btn btn-primary" name="simpan">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>