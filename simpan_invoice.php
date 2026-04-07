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
    return (float)str_replace(',', '.', (string)$v);
}

$id          = (int)($_POST['id'] ?? 0);
$pasienId    = (int)($_POST['pasien_id'] ?? 0);
$kunjunganId = (int)($_POST['kunjungan_id'] ?? 0);
$noInvoice   = trim($_POST['no_invoice'] ?? '');
$tanggal     = trim($_POST['tanggal'] ?? '');
$status      = trim($_POST['status_bayar'] ?? 'pending');
$metode      = trim($_POST['metode_bayar'] ?? 'tunai');
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

$conn->begin_transaction();

try {
    if ($id > 0) {
        db_run(
            "UPDATE invoice
             SET pasien_id=?, kunjungan_id=?, no_invoice=?, tanggal=?, status_bayar=?, metode_bayar=?, diskon=?, catatan=?
             WHERE id=?",
            [$pasienId, $kunjunganId, $noInvoice, $tanggal, $status, $metode, $diskon, $catatan, $id]
        );

        $invoiceId = $id;
        db_run("DELETE FROM invoice_items WHERE invoice_id=?", [$invoiceId]);
    } else {
        $invoiceId = db_insert(
            "INSERT INTO invoice
             (pasien_id, kunjungan_id, no_invoice, tanggal, status_bayar, metode_bayar, diskon, catatan)
             VALUES (?,?,?,?,?,?,?,?)",
            [$pasienId, $kunjunganId, $noInvoice, $tanggal, $status, $metode, $diskon, $catatan]
        );
    }

    $subtotal = 0;
    $jumlahItem = 0;

    foreach ($namaItems as $i => $nama) {
        $nama = trim((string)$nama);
        if ($nama === '') continue;

        $qty   = to_float($qtyItems[$i] ?? 1);
        $harga = to_float($hargaItems[$i] ?? 0);
        $sub   = to_float($subtotalItems[$i] ?? 0);
        $tid   = (int)($tindakanIds[$i] ?? 0);

        if ($qty <= 0) $qty = 1;
        if ($sub <= 0) $sub = $qty * $harga;

        $sql = "INSERT INTO invoice_items (invoice_id, nama_item, qty, harga, subtotal";
        $vals = " VALUES (?,?,?,?,?";
        $params = [$invoiceId, $nama, $qty, $harga, $sub];

        if (function_exists('column_exists') && column_exists($conn, 'invoice_items', 'tindakan_id')) {
            $sql .= ", tindakan_id";
            $vals .= ",?";
            $params[] = $tid;
        }

        $sql .= ")";
        $vals .= ")";
        db_insert($sql . $vals, $params);

        $subtotal += $sub;
        $jumlahItem++;
    }

    if ($jumlahItem <= 0) {
        throw new Exception('Minimal harus ada 1 item invoice.');
    }

    $total = $subtotal - $diskon;
    if ($total < 0) $total = 0;

    db_run("UPDATE invoice SET subtotal=?, total=? WHERE id=?", [$subtotal, $total, $invoiceId]);

    // sync_invoice_finance($invoiceId);

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
