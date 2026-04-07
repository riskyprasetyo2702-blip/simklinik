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

function first_existing_column($conn, $table, $candidates) {
    foreach ($candidates as $col) {
        if (column_exists($conn, $table, $col)) return $col;
    }
    return null;
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

$namaItems = $_POST['nama_item'] ?? [];
$qtyItems  = $_POST['qty'] ?? [];
$hargaItems = $_POST['harga'] ?? [];

if ($pasienId <= 0) {
    $_SESSION['error'] = 'Pasien wajib dipilih';
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

/**
 * Cari nama kolom asli pada tabel invoice_items
 */
$itemNameCol = first_existing_column($conn, 'invoice_items', [
    'nama_item',
    'item',
    'deskripsi',
    'nama_tindakan',
    'tindakan'
]);

if (!$itemNameCol) {
    $_SESSION['error'] = 'Tabel invoice_items tidak memiliki kolom nama item yang dikenali.';
    header('Location: invoice.php');
    exit;
}

$hasQty      = column_exists($conn, 'invoice_items', 'qty');
$hasHarga    = column_exists($conn, 'invoice_items', 'harga');
$hasSubtotal = column_exists($conn, 'invoice_items', 'subtotal');

$conn->begin_transaction();

try {
    if ($id > 0) {
        $ok = db_run(
            "UPDATE invoice
             SET pasien_id=?, kunjungan_id=?, no_invoice=?, tanggal=?, status_bayar=?, metode_bayar=?, diskon=?, catatan=?
             WHERE id=?",
            [$pasienId, $kunjunganId, $noInvoice, $tanggal, $status, $metode, $diskon, $catatan, $id]
        );

        if (!$ok) {
            throw new Exception('Gagal update invoice');
        }

        $invoiceId = $id;
        db_run("DELETE FROM invoice_items WHERE invoice_id=?", [$invoiceId]);
    } else {
        $invoiceId = db_insert(
            "INSERT INTO invoice (pasien_id, kunjungan_id, no_invoice, tanggal, status_bayar, metode_bayar, diskon, catatan)
             VALUES (?,?,?,?,?,?,?,?)",
            [$pasienId, $kunjunganId, $noInvoice, $tanggal, $status, $metode, $diskon, $catatan]
        );

        if (!$invoiceId) {
            throw new Exception('Gagal membuat invoice baru');
        }
    }

    $subtotal = 0;
    $jumlahItem = 0;

    foreach ($namaItems as $i => $nama) {
        $nama = trim((string)$nama);
        if ($nama === '') continue;

        $qty   = to_float($qtyItems[$i] ?? 1);
        $harga = to_float($hargaItems[$i] ?? 0);

        if ($qty <= 0) $qty = 1;
        if ($harga < 0) $harga = 0;

        $sub = $qty * $harga;

        $columns = ['invoice_id', $itemNameCol];
        $params  = [$invoiceId, $nama];

        if ($hasQty) {
            $columns[] = 'qty';
            $params[] = $qty;
        }

        if ($hasHarga) {
            $columns[] = 'harga';
            $params[] = $harga;
        }

        if ($hasSubtotal) {
            $columns[] = 'subtotal';
            $params[] = $sub;
        }

        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO invoice_items (" . implode(',', $columns) . ") VALUES ($placeholders)";

        $itemId = db_insert($sql, $params);
        if (!$itemId) {
            throw new Exception('Gagal menyimpan item invoice: ' . $nama);
        }

        $subtotal += $sub;
        $jumlahItem++;
    }

    if ($jumlahItem <= 0) {
        throw new Exception('Minimal harus ada 1 item invoice');
    }

    $total = $subtotal - $diskon;
    if ($total < 0) $total = 0;

    db_run("UPDATE invoice SET subtotal=?, total=? WHERE id=?", [$subtotal, $total, $invoiceId]);

    $conn->commit();

    $_SESSION['success'] = 'Invoice berhasil disimpan';
    header('Location: invoice.php?edit=' . $invoiceId);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Gagal simpan invoice: ' . $e->getMessage();
    header('Location: invoice.php' . ($id > 0 ? '?edit=' . $id : ''));
    exit;
}
