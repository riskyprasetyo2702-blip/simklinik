<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    $_SESSION['error'] = 'Koneksi database gagal.';
    header('Location: invoice.php');
    exit;
}

function to_float($v) {
    if ($v === null || $v === '') return 0;
    $v = str_replace(['Rp', 'rp', '.', ' '], '', (string)$v);
    $v = str_replace(',', '.', $v);
    return (float)$v;
}

function first_existing_column_local($conn, $table, $candidates) {
    foreach ($candidates as $col) {
        if (function_exists('column_exists') && column_exists($conn, $table, $col)) {
            return $col;
        }
    }
    return null;
}

$id          = (int)($_POST['id'] ?? 0);
$pasienId    = (int)($_POST['pasien_id'] ?? 0);
$kunjunganId = (int)($_POST['kunjungan_id'] ?? 0);
$noInvoice   = trim($_POST['no_invoice'] ?? '');
$tanggal     = trim($_POST['tanggal'] ?? '');
$status      = trim($_POST['status_bayar'] ?? 'pending');
$metode      = trim($_POST['metode_bayar'] ?? 'qris');
$diskon      = to_float($_POST['diskon'] ?? 0);
$catatan     = trim($_POST['catatan'] ?? '');

$namaItems     = $_POST['nama_item'] ?? [];
$qtyItems      = $_POST['qty'] ?? [];
$hargaItems    = $_POST['harga'] ?? [];
$subtotalItems = $_POST['subtotal_item'] ?? [];
$tindakanIds   = $_POST['tindakan_id'] ?? [];

if ($pasienId <= 0) {
    $_SESSION['error'] = 'Pasien wajib dipilih.';
    header('Location: invoice.php');
    exit;
}

if ($noInvoice === '') {
    $noInvoice = next_invoice_no();
}

$tanggal = str_replace('T', ' ', $tanggal);
if (strlen($tanggal) === 16) {
    $tanggal .= ':00';
}

/*
 * cari nama kolom item yang benar di tabel invoice_items
 */
$itemNameCol = first_existing_column_local($conn, 'invoice_items', [
    'nama_item',
    'item',
    'deskripsi',
    'nama_tindakan',
    'tindakan'
]);

if (!$itemNameCol) {
    $_SESSION['error'] = 'Tabel invoice_items tidak memiliki kolom nama item yang dikenali.';
    header('Location: invoice.php' . ($id > 0 ? '?edit=' . $id : ''));
    exit;
}

$hasQty       = function_exists('column_exists') && column_exists($conn, 'invoice_items', 'qty');
$hasHarga     = function_exists('column_exists') && column_exists($conn, 'invoice_items', 'harga');
$hasPrice     = function_exists('column_exists') && column_exists($conn, 'invoice_items', 'price');
$hasSubtotal  = function_exists('column_exists') && column_exists($conn, 'invoice_items', 'subtotal');
$hasTotal     = function_exists('column_exists') && column_exists($conn, 'invoice_items', 'total');
$hasTindakan  = function_exists('column_exists') && column_exists($conn, 'invoice_items', 'tindakan_id');

$conn->begin_transaction();

try {
    if ($id > 0) {
        $existing = db_fetch_one("SELECT * FROM invoice WHERE id=?", [$id]);
        if (!$existing) {
            throw new Exception('Invoice tidak ditemukan.');
        }

        $ok = db_run(
            "UPDATE invoice
             SET pasien_id=?, kunjungan_id=?, no_invoice=?, tanggal=?, status_bayar=?, metode_bayar=?, diskon=?, catatan=?
             WHERE id=?",
            [$pasienId, $kunjunganId, $noInvoice, $tanggal, $status, $metode, $diskon, $catatan, $id]
        );

        if (!$ok) {
            throw new Exception('Gagal update invoice.');
        }

        $invoiceId = $id;
        db_run("DELETE FROM invoice_items WHERE invoice_id=?", [$invoiceId]);
    } else {
        $invoiceId = db_insert(
            "INSERT INTO invoice
             (pasien_id, kunjungan_id, no_invoice, tanggal, status_bayar, metode_bayar, diskon, catatan)
             VALUES (?,?,?,?,?,?,?,?)",
            [$pasienId, $kunjunganId, $noInvoice, $tanggal, $status, $metode, $diskon, $catatan]
        );

        if (!$invoiceId) {
            throw new Exception('Gagal membuat invoice baru.');
        }
    }

    $subtotalFinal = 0;
    $jumlahItem = 0;

    foreach ($namaItems as $i => $nama) {
        $nama = trim((string)$nama);
        if ($nama === '') continue;

        $qty      = to_float($qtyItems[$i] ?? 1);
        $harga    = to_float($hargaItems[$i] ?? 0);
        $subtotal = to_float($subtotalItems[$i] ?? 0);
        $tid      = (int)($tindakanIds[$i] ?? 0);

        if ($qty <= 0) $qty = 1;
        if ($harga < 0) $harga = 0;
        if ($subtotal <= 0) $subtotal = $qty * $harga;

        $columns = ['invoice_id', $itemNameCol];
        $params  = [$invoiceId, $nama];

        if ($hasTindakan) {
            $columns[] = 'tindakan_id';
            $params[] = $tid;
        }

        if ($hasQty) {
            $columns[] = 'qty';
            $params[] = $qty;
        }

        if ($hasHarga) {
            $columns[] = 'harga';
            $params[] = $harga;
        } elseif ($hasPrice) {
            $columns[] = 'price';
            $params[] = $harga;
        }

        if ($hasSubtotal) {
            $columns[] = 'subtotal';
            $params[] = $subtotal;
        } elseif ($hasTotal) {
            $columns[] = 'total';
            $params[] = $subtotal;
        }

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO invoice_items (" . implode(',', $columns) . ") VALUES ($placeholders)";

        $itemId = db_insert($sql, $params);
        if (!$itemId) {
            throw new Exception('Gagal menyimpan item invoice: ' . $nama);
        }

        $subtotalFinal += $subtotal;
        $jumlahItem++;
    }

    if ($jumlahItem <= 0) {
        throw new Exception('Minimal harus ada 1 item invoice.');
    }

    $totalFinal = $subtotalFinal - $diskon;
    if ($totalFinal < 0) $totalFinal = 0;

    db_run(
        "UPDATE invoice SET subtotal=?, total=? WHERE id=?",
        [$subtotalFinal, $totalFinal, $invoiceId]
    );

    if (function_exists('sync_invoice_finance')) {
        sync_invoice_finance($invoiceId);
    }

    $conn->commit();

    $_SESSION['success'] = 'Invoice berhasil disimpan.';
    header('Location: invoice.php?edit=' . $invoiceId);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Gagal simpan invoice: ' . $e->getMessage();
    header('Location: invoice.php' . ($id > 0 ? '?edit=' . $id : ''));
    exit;
}
