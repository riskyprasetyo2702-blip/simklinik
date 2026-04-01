<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

require_once 'config.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "", "simklinik");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

/*
|--------------------------------------------------------------------------
| FUNCTION HITUNG ULANG TOTAL INVOICE
|--------------------------------------------------------------------------
*/
function hitungUlangInvoice($conn, $invoice_id) {
    $invoice_id = (int)$invoice_id;

    $sum = $conn->query("
        SELECT COALESCE(SUM(subtotal), 0) AS subtotal_items
        FROM invoice_items
        WHERE invoice_id = $invoice_id
    ")->fetch_assoc();

    $inv = $conn->query("
        SELECT COALESCE(diskon, 0) AS diskon
        FROM invoices
        WHERE id = $invoice_id
        LIMIT 1
    ")->fetch_assoc();

    $subtotal = (float)($sum['subtotal_items'] ?? 0);
    $diskon = (float)($inv['diskon'] ?? 0);
    $total = $subtotal - $diskon;

    if ($total < 0) {
        $total = 0;
    }

    $stmt = $conn->prepare("
        UPDATE invoices
        SET subtotal = ?, total = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ddi", $subtotal, $total, $invoice_id);
    $stmt->execute();
    $stmt->close();
}

/*
|--------------------------------------------------------------------------
| TAMBAH ITEM MANUAL
|--------------------------------------------------------------------------
*/
if (isset($_POST['tambah_item'])) {
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $treatment_id = (int)($_POST['treatment_id'] ?? 0);
    $qty = (int)($_POST['qty'] ?? 1);

    if ($invoice_id > 0 && $treatment_id > 0) {
        if ($qty < 1) {
            $qty = 1;
        }

        $stmt = $conn->prepare("
            SELECT harga
            FROM treatments
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $treatment_id);
        $stmt->execute();
        $treatment = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($treatment) {
            $harga = (float)$treatment['harga'];
            $subtotal = $qty * $harga;

            $stmt = $conn->prepare("
                INSERT INTO invoice_items
                    (invoice_id, treatment_id, qty, harga, subtotal, sumber)
                VALUES
                    (?, ?, ?, ?, ?, 'manual')
            ");
            $stmt->bind_param("iiidd", $invoice_id, $treatment_id, $qty, $harga, $subtotal);
            $stmt->execute();
            $stmt->close();

            hitungUlangInvoice($conn, $invoice_id);
        }
    }

    header("Location: invoice.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| SIMPAN PENGATURAN INVOICE
|--------------------------------------------------------------------------
*/
if (isset($_POST['update_invoice'])) {
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $diskon = (float)($_POST['diskon'] ?? 0);
    $metode_bayar = $_POST['metode_bayar'] ?? '';
    $catatan = $_POST['catatan'] ?? '';

    if ($diskon < 0) $diskon = 0;

    $stmt = $conn->prepare("
        UPDATE invoices
        SET diskon = ?, metode_bayar = ?, catatan = ?
        WHERE id = ?
    ");

    // PENTING: dssi (bukan salah urutan)
    $stmt->bind_param("dssi", $diskon, $metode_bayar, $catatan, $invoice_id);

    if (!$stmt->execute()) {
        die("ERROR update: " . $stmt->error);
    }

    $stmt->close();

    hitungUlangInvoice($conn, $invoice_id);

    header("Location: invoice.php");
    exit;

}

/*
|--------------------------------------------------------------------------
| UBAH STATUS MANUAL
|--------------------------------------------------------------------------
*/
if (isset($_POST['set_status'])) {
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $status_bayar = trim($_POST['status_bayar'] ?? 'pending');

    if (in_array($status_bayar, ['pending', 'lunas', 'tidak_terbayar'], true)) {
        $stmt = $conn->prepare("
            UPDATE invoices
            SET status_bayar = ?
            WHERE id = ?
        ");
        $stmt->bind_param("si", $status_bayar, $invoice_id);
        $stmt->execute();
        $stmt->close();
    }

    header("Location: invoice.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| FINAL INVOICE -> LUNAS + MASUK KEUANGAN + REDIRECT DASHBOARD
|--------------------------------------------------------------------------
*/
if (isset($_POST['final_invoice'])) {
    $invoice_id = (int)($_POST['invoice_id'] ?? 0);
    $diskon = (float)($_POST['diskon'] ?? 0);
    $metode_bayar = $_POST['metode_bayar'] ?? '';
    $catatan = $_POST['catatan'] ?? '';

    if ($diskon < 0) $diskon = 0;

    // UPDATE INVOICE
    $stmt = $conn->prepare("
        UPDATE invoices
        SET diskon = ?, metode_bayar = ?, catatan = ?, status_bayar = 'lunas'
        WHERE id = ?
    ");

    $stmt->bind_param("dssi", $diskon, $metode_bayar, $catatan, $invoice_id);

    if (!$stmt->execute()) {
        die("ERROR update invoice: " . $stmt->error);
    }

    $stmt->close();

    // HITUNG ULANG TOTAL
    hitungUlangInvoice($conn, $invoice_id);

    // AMBIL TOTAL
    $stmt = $conn->prepare("SELECT total, visit_id FROM invoices WHERE id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $inv = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inv) {
        die("Invoice tidak ditemukan");
    }

    $total = (float)$inv['total'];
    $visit_id = (int)$inv['visit_id'];

    // AMBIL PATIENT
    $stmt = $conn->prepare("SELECT patient_id FROM visits WHERE id = ?");
    $stmt->bind_param("i", $visit_id);
    $stmt->execute();
    $visit = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $patient_id = (int)($visit['patient_id'] ?? 0);

    // CEK SUDAH MASUK KEUANGAN ATAU BELUM
    $stmt = $conn->prepare("SELECT id FROM keuangan WHERE invoice_id = ?");
    $stmt->bind_param("i", $invoice_id);
    $stmt->execute();
    $cek = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cek) {
        $desc = "Pembayaran pasien";

        $stmt = $conn->prepare("
            INSERT INTO keuangan
            (tanggal, jenis, deskripsi, nominal, invoice_id, patient_id)
            VALUES (NOW(), 'pemasukan', ?, ?, ?, ?)
        ");

        $stmt->bind_param("sdii", $desc, $total, $invoice_id, $patient_id);

        if (!$stmt->execute()) {
            die("ERROR keuangan: " . $stmt->error);
        }

        $stmt->close();
    }

    // REDIRECT
    header("Location: dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| AMBIL DATA
|--------------------------------------------------------------------------
*/
$invoices = $conn->query("
    SELECT
        i.*,
        p.nama AS nama_pasien,
        p.no_rm
    FROM invoices i
    JOIN visits v ON v.id = i.visit_id
    JOIN patients p ON p.id = v.patient_id
    ORDER BY i.id DESC
");

$treatments = $conn->query("
    SELECT *
    FROM treatments
    ORDER BY kategori ASC, nama_tindakan ASC
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?= NAMA_KLINIK ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #eef2f7;
        }
        .card-soft {
            border: 0;
            border-radius: 22px;
            box-shadow: 0 10px 28px rgba(0,0,0,.06);
        }
        .summary-box {
            background: #f8fafc;
            border-radius: 16px;
            padding: 14px;
            height: 100%;
        }
        .table th {
            white-space: nowrap;
        }
        .section-title {
            font-weight: 700;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="mb-0">💰 <?= NAMA_KLINIK ?></h3>
        <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
    </div>

    <?php if ($invoices->num_rows > 0): ?>
        <?php while($inv = $invoices->fetch_assoc()): ?>
            <div class="card card-soft mb-4">
                <div class="card-body">

                    <div class="row g-3 mb-3">
                        <div class="col-lg-4">
                            <div class="summary-box">
                                <div><strong>No Invoice:</strong> <?= htmlspecialchars($inv['nomor_invoice']) ?></div>
                                <div><strong>No RM:</strong> <?= htmlspecialchars($inv['no_rm'] ?? '-') ?></div>
                                <div><strong>Pasien:</strong> <?= htmlspecialchars($inv['nama_pasien']) ?></div>
                                <div><strong>Tanggal:</strong> <?= htmlspecialchars($inv['tanggal_invoice']) ?></div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="summary-box">
                                <div><strong>Status:</strong>
                                    <?php if ($inv['status_bayar'] === 'lunas'): ?>
                                        <span class="badge bg-success">Lunas</span>
                                    <?php elseif ($inv['status_bayar'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($inv['status_bayar'] === 'tidak_terbayar'): ?>
                                        <span class="badge bg-danger">Tidak Terbayar</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($inv['status_bayar']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div><strong>Metode Bayar:</strong> <?= htmlspecialchars($inv['metode_bayar'] ?: '-') ?></div>
                                <div><strong>Catatan:</strong> <?= htmlspecialchars($inv['catatan'] ?: '-') ?></div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <div class="summary-box">
                                <div><strong>Subtotal:</strong> Rp <?= number_format((float)$inv['subtotal'],0,',','.') ?></div>
                                <div><strong>Diskon:</strong> Rp <?= number_format((float)$inv['diskon'],0,',','.') ?></div>
                                <div class="mt-2"><strong>Total Akhir:</strong></div>
                                <h4 class="mb-0">Rp <?= number_format((float)$inv['total'],0,',','.') ?></h4>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Tindakan</th>
                                    <th>Gigi</th>
                                    <th>Surface</th>
                                    <th>Sumber</th>
                                    <th>Qty</th>
                                    <th>Harga</th>
                                    <th>Subtotal</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $invoice_id = (int)$inv['id'];
                            $items = $conn->query("
                                SELECT ii.*, COALESCE(ii.nama_tindakan, t.nama_tindakan) AS nama_tindakan
                                FROM invoice_items ii
                                LEFT JOIN treatments t ON t.id = ii.treatment_id
                                WHERE ii.invoice_id = $invoice_id
                                ORDER BY ii.id ASC
                            ");
                            ?>
                            <?php if ($items->num_rows > 0): ?>
                                <?php while($item = $items->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['nama_tindakan']) ?></td>
                                        <td><?= htmlspecialchars($item['tooth_number'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($item['surface_code'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($item['sumber'] ?? '-') ?></td>
                                        <td><?= htmlspecialchars($item['qty']) ?></td>
                                        <td>Rp <?= number_format((float)$item['harga'],0,',','.') ?></td>
                                        <td>Rp <?= number_format((float)$item['subtotal'],0,',','.') ?></td>
                                        <td>
                                            <a href="invoice_edit_item.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                            <a href="invoice_delete_item.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-danger"
                                               onclick="return confirm('Hapus item ini?')">Hapus</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center">Belum ada item invoice.</td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <div class="section-title">Tambah Item Manual</div>
                                    <form method="POST" class="row g-2">
                                        <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">

                                        <div class="col-md-7">
                                            <select name="treatment_id" class="form-select" required>
                                                <option value="">Pilih tindakan</option>
                                                <?php
                                                $treatments->data_seek(0);
                                                while($t = $treatments->fetch_assoc()):
                                                ?>
                                                    <option value="<?= $t['id'] ?>">
                                                        <?= htmlspecialchars($t['nama_tindakan']) ?> (Rp <?= number_format((float)$t['harga'],0,',','.') ?>)
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-2">
                                            <input type="number" name="qty" class="form-control" value="1" min="1">
                                        </div>

                                        <div class="col-md-3">
                                            <button name="tambah_item" class="btn btn-primary w-100">Tambah</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <div class="section-title">Pengaturan Invoice</div>

                                    <form method="POST" class="row g-2">
                                        <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">

                                        <div class="col-md-4">
                                            <label class="form-label">Diskon Nominal</label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="diskon"
                                                class="form-control"
                                                value="<?= htmlspecialchars((float)$inv['diskon']) ?>"
                                            >
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Metode Bayar</label>
                                            <select name="metode_bayar" class="form-select">
                                                <option value="">- Pilih -</option>
                                                <option value="cash" <?= ($inv['metode_bayar'] === 'cash') ? 'selected' : '' ?>>Cash</option>
                                                <option value="transfer" <?= ($inv['metode_bayar'] === 'transfer') ? 'selected' : '' ?>>Transfer</option>
                                                <option value="qris" <?= ($inv['metode_bayar'] === 'qris') ? 'selected' : '' ?>>QRIS</option>
                                                <option value="debit" <?= ($inv['metode_bayar'] === 'debit') ? 'selected' : '' ?>>Debit</option>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Status Bayar</label>
                                            <select class="form-select" disabled>
                                                <option><?= htmlspecialchars($inv['status_bayar']) ?></option>
                                            </select>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Catatan</label>
                                            <textarea name="catatan" class="form-control" rows="2"><?= htmlspecialchars($inv['catatan'] ?? '') ?></textarea>
                                        </div>

                                        <div class="col-12 d-flex gap-2 flex-wrap">
                                            <button name="update_invoice" class="btn btn-dark">Simpan Pengaturan</button>
                                            <button name="final_invoice" class="btn btn-success">Selesai</button>
                                        </div>
                                    </form>

                                    <div class="mt-3 d-flex gap-2 flex-wrap">
                                        <form method="POST">
                                            <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                            <input type="hidden" name="status_bayar" value="lunas">
                                            <button name="set_status" class="btn btn-success btn-sm">Lunas</button>
                                        </form>

                                        <form method="POST">
                                            <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                            <input type="hidden" name="status_bayar" value="pending">
                                            <button name="set_status" class="btn btn-warning btn-sm">Pending</button>
                                        </form>

                                        <form method="POST">
                                            <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                            <input type="hidden" name="status_bayar" value="tidak_terbayar">
                                            <button name="set_status" class="btn btn-danger btn-sm">Tidak Terbayar</button>
                                        </form>

                                        <a href="invoice_print.php?id=<?= $inv['id'] ?>" class="btn btn-outline-success btn-sm">Print A4</a>
                                        <a href="invoice_struk.php?id=<?= $inv['id'] ?>" class="btn btn-outline-dark btn-sm">Struk</a>
                                        <a href="invoice_pdf.php?id=<?= $inv['id'] ?>" class="btn btn-outline-primary btn-sm">PDF</a>
                                        <a href="invoice_delete.php?id=<?= $inv['id'] ?>" class="btn btn-outline-danger btn-sm"
                                           onclick="return confirm('Hapus invoice ini?')">Hapus Invoice</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="card card-soft">
            <div class="card-body text-center py-5">
                Belum ada invoice.
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>