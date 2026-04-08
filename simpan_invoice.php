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

/*
|--------------------------------------------------------------------------
| Ambil invoice_id
|--------------------------------------------------------------------------
*/
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
| MODE 1: Tambah item manual
|--------------------------------------------------------------------------
*/
if (isset($_POST['tambah_item'])) {
    if (!table_exists($conn, 'invoice_items')) {
        $_SESSION['error'] = 'Tabel invoice_items tidak ditemukan.';
        header('Location: invoice.php?edit=' . $invoice_id);
        exit;
    }

    $nama_tindakan = trim($_POST['nama_tindakan'] ?? '');
    $qty           = (float)($_POST['qty'] ?? 1);
    $harga         = (float)($_POST['harga'] ?? 0);

    if ($nama_tindakan === '') {
        $_SESSION['error'] = 'Nama tindakan wajib diisi.';
        header('Location: invoice.php?edit=' . $invoice_id);
        exit;
    }

    if ($qty <= 0) {
        $qty = 1;
    }

    if ($harga < 0) {
        $harga = 0;
    }

    $subtotal = $qty * $harga;

    /*
    |--------------------------------------------------------------------------
    | Susun kolom adaptif sesuai struktur invoice_items cloud
    |--------------------------------------------------------------------------
    */
    $data = [];

    if (column_exists($conn, 'invoice_items', 'invoice_id')) {
        $data['invoice_id'] = $invoice_id;
    }
    if (column_exists($conn, 'invoice_items', 'treatment_id')) {
        $data['treatment_id'] = null;
    }
    if (column_exists($conn, 'invoice_items', 'tindakan_id')) {
        $data['tindakan_id'] = null;
    }
    if (column_exists($conn, 'invoice_items', 'nama_tindakan')) {
        $data['nama_tindakan'] = $nama_tindakan;
    }
    if (column_exists($conn, 'invoice_items', 'nama_item')) {
        $data['nama_item'] = $nama_tindakan;
    }
    if (column_exists($conn, 'invoice_items', 'qty')) {
        $data['qty'] = $qty;
    }
    if (column_exists($conn, 'invoice_items', 'harga')) {
        $data['harga'] = $harga;
    }
    if (column_exists($conn, 'invoice_items', 'subtotal')) {
        $data['subtotal'] = $subtotal;
    }
    if (column_exists($conn, 'invoice_items', 'tooth_number')) {
        $data['tooth_number'] = null;
    }
    if (column_exists($conn, 'invoice_items', 'nomor_gigi')) {
        $data['nomor_gigi'] = null;
    }
    if (column_exists($conn, 'invoice_items', 'surface_code')) {
        $data['surface_code'] = null;
    }
    if (column_exists($conn, 'invoice_items', 'keterangan')) {
        $data['keterangan'] = 'manual';
    }
    if (column_exists($conn, 'invoice_items', 'sumber')) {
        $data['sumber'] = 'manual';
    }
    if (column_exists($conn, 'invoice_items', 'created_at')) {
        $data['created_at'] = date('Y-m-d H:i:s');
    }

    $cols = [];
    $placeholders = [];
    $params = [];

    foreach ($data as $col => $val) {
        $cols[] = "`$col`";
        $placeholders[] = "?";
        $params[] = $val;
    }

    $sql = "INSERT INTO invoice_items (" . implode(', ', $cols) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = 'Gagal prepare item invoice: ' . $conn->error;
        header('Location: invoice.php?edit=' . $invoice_id);
        exit;
    }

    $types = '';
    foreach ($params as $p) {
        if (is_int($p)) {
            $types .= 'i';
        } elseif (is_float($p)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $_SESSION['success'] = 'Item invoice berhasil ditambahkan.';
    } else {
        $_SESSION['error'] = 'Gagal menambahkan item invoice.';
    }

    header('Location: invoice.php?edit=' . $invoice_id);
    exit;
}

/*
|--------------------------------------------------------------------------
| MODE 2: Simpan invoice
|--------------------------------------------------------------------------
*/
if (isset($_POST['simpan_invoice'])) {
    $diskon       = (float)($_POST['diskon'] ?? 0);
    $status_bayar = trim($_POST['status_bayar'] ?? 'belum terbayar');
    $metode_bayar = trim($_POST['metode_bayar'] ?? 'tunai');

    if ($diskon < 0) {
        $diskon = 0;
    }

    $sum = 0;
    if (table_exists($conn, 'invoice_items')) {
        $rowSum = db_fetch_one(
            "SELECT COALESCE(SUM(subtotal),0) AS total_subtotal FROM invoice_items WHERE invoice_id = ?",
            [$invoice_id]
        );
        $sum = (float)($rowSum['total_subtotal'] ?? 0);
    }

    $total = max(0, $sum - $diskon);

    $data = [];
    if (column_exists($conn, 'invoice', 'subtotal')) {
        $data['subtotal'] = $sum;
    }
    if (column_exists($conn, 'invoice', 'diskon')) {
        $data['diskon'] = $diskon;
    }
    if (column_exists($conn, 'invoice', 'total')) {
        $data['total'] = $total;
    }
    if (column_exists($conn, 'invoice', 'status_bayar')) {
        $data['status_bayar'] = $status_bayar;
    }
    if (column_exists($conn, 'invoice', 'metode_bayar')) {
        $data['metode_bayar'] = $metode_bayar;
    }
    if (column_exists($conn, 'invoice', 'updated_at')) {
        // di-handle langsung di SQL
    }

    $setParts = [];
    $params = [];

    foreach ($data as $col => $val) {
        $setParts[] = "`$col` = ?";
        $params[] = $val;
    }

    if (column_exists($conn, 'invoice', 'updated_at')) {
        $setParts[] = "`updated_at` = NOW()";
    }

    $params[] = $invoice_id;

    $sql = "UPDATE invoice SET " . implode(', ', $setParts) . " WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = 'Gagal prepare update invoice: ' . $conn->error;
        header('Location: invoice.php?edit=' . $invoice_id);
        exit;
    }

    $types = '';
    for ($i = 0; $i < count($params) - 1; $i++) {
        if (is_int($params[$i])) {
            $types .= 'i';
        } elseif (is_float($params[$i])) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    $types .= 'i';

    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        $_SESSION['error'] = 'Gagal menyimpan invoice.';
        header('Location: invoice.php?edit=' . $invoice_id);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Sinkron ke keuangan AMAN
    | - invoice tetap berhasil walau sync gagal
    |--------------------------------------------------------------------------
    */
    try {
        if (function_exists('sync_invoice_finance')) {
            sync_invoice_finance($invoice_id);
        }
    } catch (Throwable $e) {
        // sengaja diabaikan agar invoice tidak gagal
    }

    $_SESSION['success'] = 'Invoice berhasil disimpan.';
    header('Location: invoice.php?edit=' . $invoice_id);
    exit;
}

header('Location: invoice.php?edit=' . $invoice_id);
exit;
