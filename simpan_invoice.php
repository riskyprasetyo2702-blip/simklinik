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

function insert_invoice_item_adaptive($conn, $invoiceId, $nama, $qty, $harga, $subtotal, $postedTindakanId = 0, $nomorGigi = '', $keterangan = '') {
    $columns = ['invoice_id'];
    $params  = [$invoiceId];

    // nama item
    $nameCol = first_existing_column($conn, 'invoice_items', [
        'nama_item',
        'item',
        'deskripsi',
        'nama_tindakan',
        'tindakan',
        'treatment_name'
    ]);
    if (!$nameCol) {
        throw new Exception('Kolom nama item pada invoice_items tidak ditemukan.');
    }
    $columns[] = $nameCol;
    $params[]  = $nama;

    // qty/harga/subtotal
    if (column_exists($conn, 'invoice_items', 'qty')) {
        $columns[] = 'qty';
        $params[] = $qty;
    }

    if (column_exists($conn, 'invoice_items', 'harga')) {
        $columns[] = 'harga';
        $params[] = $harga;
    } elseif (column_exists($conn, 'invoice_items', 'price')) {
        $columns[] = 'price';
        $params[] = $harga;
    }

    if (column_exists($conn, 'invoice_items', 'subtotal')) {
        $columns[] = 'subtotal';
        $params[] = $subtotal;
    } elseif (column_exists($conn, 'invoice_items', 'total')) {
        $columns[] = 'total';
        $params[] = $subtotal;
    }

    // kolom gigi / keterangan kalau ada
    if (column_exists($conn, 'invoice_items', 'nomor_gigi')) {
        $columns[] = 'nomor_gigi';
        $params[] = $nomorGigi;
    } elseif (column_exists($conn, 'invoice_items', 'tooth_number')) {
        $columns[] = 'tooth_number';
        $params[] = $nomorGigi;
    }

    if (column_exists($conn, 'invoice_items', 'keterangan')) {
        $columns[] = 'keterangan';
        $params[] = $keterangan;
    } elseif (column_exists($conn, 'invoice_items', 'notes')) {
        $columns[] = 'notes';
        $params[] = $keterangan;
    }

    // semua kemungkinan kolom id tindakan yang bikin error
    $treatmentValue = (int)$postedTindakanId;
    if ($treatmentValue < 0) $treatmentValue = 0;

    $idCandidates = [
        'tindakan_id',
        'treatment_id',
        'service_id',
        'procedure_id',
        'item_id'
    ];

    foreach ($idCandidates as $idCol) {
        if (column_exists($conn, 'invoice_items', $idCol)) {
            $columns[] = $idCol;
            $params[] = $treatmentValue;
        }
    }

    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO invoice_items (" . implode(',', $columns) . ") VALUES ($placeholders)";

    $itemId = db_insert($sql, $params);
    if (!$itemId) {
        throw new Exception('Gagal menyimpan item invoice: ' . $nama);
    }

    return $itemId;
}

$id            = (int)($_POST['id'] ?? 0);
$pasienId      = (int)($_POST['pasien_id'] ?? 0);
$kunjunganId   = (int)($_POST['kunjungan_id'] ?? 0);
$noInvoice     = trim($_POST['no_invoice'] ?? '');
$tanggal       = trim($_POST['tanggal'] ?? '');
$statusBayar   = trim($_POST['status_bayar'] ?? 'pending');
$metodeBayar   = trim($_POST['metode_bayar'] ?? 'qris');
$diskon        = to_float($_POST['diskon'] ?? 0);
$catatan       = trim($_POST['catatan'] ?? '');

$namaItems      = $_POST['nama_item'] ?? [];
$qtyItems       = $_POST['qty'] ?? [];
$hargaItems     = $_POST['harga'] ?? [];
$subtotalItems  = $_POST['subtotal_item'] ?? [];
$tindakanIds    = $_POST['tindakan_id'] ?? [];
$nomorGigiItems = $_POST['nomor_gigi'] ?? [];
$keteranganItem = $_POST['keterangan_item'] ?? [];

if ($pasienId <= 0) {
    $_SESSION['error'] = 'Pasien wajib dipilih.';
    header('Location: invoice.php');
    exit;
}

if ($noInvoice === '') {
    $noInvoice = next_invoice_no();
}

if ($tanggal === '') {
    $tanggal = date('Y-m-d H:i:s');
} else {
    $tanggal = str_replace('T', ' ', $tanggal);
    if (strlen($tanggal) === 16) {
        $tanggal .= ':00';
    }
}

if (!in_array($statusBayar, ['lunas', 'pending', 'belum terbayar', 'paid'], true)) {
    $statusBayar = 'pending';
}

$conn->begin_transaction();

try {
    if ($id > 0) {
        $existing = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$id]);
        if (!$existing) {
            throw new Exception('Invoice tidak ditemukan.');
        }

        $ok = db_run(
            "UPDATE invoice
             SET pasien_id=?, kunjungan_id=?, no_invoice=?, tanggal=?, status_bayar=?, metode_bayar=?, diskon=?, catatan=?
             WHERE id=?",
            [
                $pasienId,
                $kunjunganId > 0 ? $kunjunganId : null,
                $noInvoice,
                $tanggal,
                $statusBayar,
                $metodeBayar,
                $diskon,
                $catatan,
                $id
            ]
        );

        if (!$ok) {
            throw new Exception('Gagal update invoice.');
        }

        $invoiceId = $id;
        db_run("DELETE FROM invoice_items WHERE invoice_id = ?", [$invoiceId]);
    } else {
        $invoiceId = db_insert(
            "INSERT INTO invoice
             (pasien_id, kunjungan_id, no_invoice, tanggal, status_bayar, metode_bayar, diskon, catatan)
             VALUES (?,?,?,?,?,?,?,?)",
            [
                $pasienId,
                $kunjunganId > 0 ? $kunjunganId : null,
                $noInvoice,
                $tanggal,
                $statusBayar,
                $metodeBayar,
                $diskon,
                $catatan
            ]
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

        $qty = to_float($qtyItems[$i] ?? 1);
        $harga = to_float($hargaItems[$i] ?? 0);
        $subtotal = to_float($subtotalItems[$i] ?? 0);
        $tindakanId = (int)($tindakanIds[$i] ?? 0);
        $nomorGigi = trim((string)($nomorGigiItems[$i] ?? ''));
        $ket = trim((string)($keteranganItem[$i] ?? ''));

        if ($qty <= 0) $qty = 1;
        if ($harga < 0) $harga = 0;
        if ($subtotal <= 0) $subtotal = $qty * $harga;

        insert_invoice_item_adaptive(
            $conn,
            $invoiceId,
            $nama,
            $qty,
            $harga,
            $subtotal,
            $tindakanId,
            $nomorGigi,
            $ket
        );

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
