<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    $_SESSION['error'] = 'Koneksi database tidak tersedia.';
    header('Location: invoice.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: invoice.php');
    exit;
}

if (!table_exists($conn, 'invoice')) {
    $_SESSION['error'] = 'Tabel invoice tidak ditemukan.';
    header('Location: invoice.php');
    exit;
}

$invoice_id = (int)($_POST['invoice_id'] ?? 0);
if ($invoice_id <= 0) {
    $_SESSION['error'] = 'Invoice tidak valid.';
    header('Location: invoice.php');
    exit;
}

$invoice = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$invoice_id]);
if (!$invoice) {
    $_SESSION['error'] = 'Data invoice tidak ditemukan.';
    header('Location: invoice.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| TAMBAH ITEM MANUAL
|--------------------------------------------------------------------------
*/
if (isset($_POST['tambah_item'])) {
    if (!table_exists($conn, 'invoice_items')) {
        $_SESSION['error'] = 'Tabel invoice_items tidak ditemukan.';
        header('Location: invoice.php?edit=' . $invoice_id);
        exit;
    }

    $nama_tindakan = trim($_POST['nama_tindakan'] ?? '');
    $qty = (float)($_POST['qty'] ?? 1);
    $harga = (float)($_POST['harga'] ?? 0);

    if ($nama_tindakan === '') {
        $_SESSION['error'] = 'Nama tindakan wajib diisi.';
        header('Location: invoice.php?edit=' . $invoice_id);
        exit;
    }

    if ($qty <= 0) $qty = 1;
    if ($harga < 0) $harga = 0;

    $subtotal = $qty * $harga;

    $data = [];
    if (column_exists($conn, 'invoice_items', 'invoice_id')) $data['invoice_id'] = $invoice_id;
    if (column_exists($conn, 'invoice_items', 'treatment_id')) $data['treatment_id'] = 0;
    if (column_exists($conn, 'invoice_items', 'tindakan_id')) $data['tindakan_id'] = 0;
    if (column_exists($conn, 'invoice_items', 'nama_tindakan')) $data['nama_tindakan'] = $nama_tindakan;
    if (column_exists($conn, 'invoice_items', 'nama_item')) $data['nama_item'] = $nama_tindakan;
    if (column_exists($conn, 'invoice_items', 'qty')) $data['qty'] = $qty;
    if (column_exists($conn, 'invoice_items', 'harga')) $data['harga'] = $harga;
    if (column_exists($conn, 'invoice_items', 'subtotal')) $data['subtotal'] = $subtotal;
    if (column_exists($conn, 'invoice_items', 'tooth_number')) $data['tooth_number'] = '';
    if (column_exists($conn, 'invoice_items', 'nomor_gigi')) $data['nomor_gigi'] = '';
    if (column_exists($conn, 'invoice_items', 'surface_code')) $data['surface_code'] = '';
    if (column_exists($conn, 'invoice_items', 'keterangan')) $data['keterangan'] = 'manual';
    if (column_exists($conn, 'invoice_items', 'sumber')) $data['sumber'] = 'manual';
    if (column_exists($conn, 'invoice_items', 'created_at')) $data['created_at'] = date('Y-m-d H:i:s');

    if (empty($data)) {
        $_SESSION['error'] = 'Struktur invoice_items tidak sesuai.';
        header('Location: invoice.php?edit=' . $invoice_id);
        exit;
    }

    $cols = [];
    $holders = [];
    $params = [];
    foreach ($data as $col => $val) {
        $cols[] = "`$col`";
        $holders[] = "?";
        $params[] = $val;
    }

    $sql = "INSERT INTO invoice_items (" . implode(',', $cols) . ") VALUES (" . implode(',', $holders) . ")";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $_SESSION['error'] = 'Prepare tambah item gagal: ' . $conn->error;
        header('Location: invoice.php?edit=' . $invoice_id);
        exit;
    }

    $types = '';
    foreach ($params as $p) {
        if (is_int($p)) $types .= 'i';
        elseif (is_float($p)) $types .= 'd';
        else $types .= 's';
    }

    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();

    if ($ok) {
        $_SESSION['success'] = 'Item berhasil ditambahkan.';
    } else {
        $_SESSION['error'] = 'Gagal tambah item: ' . $err;
    }

    header('Location: invoice.php?edit=' . $invoice_id);
    exit;
}

/*
|--------------------------------------------------------------------------
| SIMPAN INVOICE
|--------------------------------------------------------------------------
*/
if (isset($_POST['simpan_invoice']) || isset($_POST['selesai_dashboard'])) {
    $diskon = (float)($_POST['diskon'] ?? 0);
    $status_bayar = trim($_POST['status_bayar'] ?? 'belum terbayar');
    $metode_bayar = trim($_POST['metode_bayar'] ?? 'tunai');
    $catatan = trim($_POST['catatan'] ?? '');

    if ($diskon < 0) $diskon = 0;

    $subtotal = 0;
    if (table_exists($conn, 'invoice_items')) {
        $sum = db_fetch_one(
            "SELECT COALESCE(SUM(subtotal),0) AS total_subtotal FROM invoice_items WHERE invoice_id = ?",
            [$invoice_id]
        );
        $subtotal = (float)($sum['total_subtotal'] ?? 0);
    }

    $total = max(0, $subtotal - $diskon);

    $setParts = [];
    $params = [];

    if (column_exists($conn, 'invoice', 'subtotal')) {
        $setParts[] = "`subtotal` = ?";
        $params[] = $subtotal;
    }
    if (column_exists($conn, 'invoice', 'diskon')) {
        $setParts[] = "`diskon` = ?";
        $params[] = $diskon;
    }
    if (column_exists($conn, 'invoice', 'total')) {
        $setParts[] = "`total` = ?";
        $params[] = $total;
    }
    if (column_exists($conn, 'invoice', 'status_bayar')) {
        $setParts[] = "`status_bayar` = ?";
        $params[] = $status_bayar;
    }
    if (column_exists($conn, 'invoice', 'metode_bayar')) {
        $setParts[] = "`metode_bayar` = ?";
        $params[] = $metode_bayar;
    }
    if (column_exists($conn, 'invoice', 'catatan')) {
        $setParts[] = "`catatan` = ?";
        $params[] = $catatan;
    }
    if (column_exists($conn, 'invoice', 'updated_at')) {
        $setParts[] = "`updated_at` = NOW()";
    }

    $params[] = $invoice_id;

    $sql = "UPDATE invoice SET " . implode(', ', $setParts) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        $_SESSION['error'] = 'Prepare simpan invoice gagal: ' . $conn->error;
        header('Location: invoice.php?edit=' . $invoice_id);
        exit;
    }

    $types = '';
    for ($i = 0; $i < count($params) - 1; $i++) {
        if (is_int($params[$i])) $types .= 'i';
        elseif (is_float($params[$i])) $types .= 'd';
        else $types .= 's';
    }
    $types .= 'i';

    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $err = $stmt->error;
    $stmt->close();

    if (!$ok) {
        $_SESSION['error'] = 'Gagal simpan invoice: ' . $err;
        header('Location: invoice.php?edit=' . $invoice_id);
        exit;
    }

    // sinkron ke keuangan
    try {
        sync_invoice_finance($invoice_id);
    } catch (Throwable $e) {
        // invoice tetap aman
    }

    $_SESSION['success'] = 'Invoice berhasil disimpan.';

    if (isset($_POST['selesai_dashboard'])) {
        header('Location: dashboard.php');
        exit;
    }

    header('Location: invoice.php?edit=' . $invoice_id);
    exit;
}

header('Location: invoice.php?edit=' . $invoice_id);
exit;
