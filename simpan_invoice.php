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

$id            = (int)($_POST['id'] ?? 0);
$pasienId      = (int)($_POST['pasien_id'] ?? 0);
$kunjunganId   = (int)($_POST['kunjungan_id'] ?? 0);
$noInvoice     = trim($_POST['no_invoice'] ?? '');
$tanggal       = trim($_POST['tanggal'] ?? '');
$statusBayar   = trim($_POST['status_bayar'] ?? 'pending');
$metodeBayar   = trim($_POST['metode_bayar'] ?? 'qris');
$subtotalPost  = to_float($_POST['subtotal'] ?? 0);
$diskon        = to_float($_POST['diskon'] ?? 0);
$totalPost     = to_float($_POST['total'] ?? 0);
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

if (!in_array($metodeBayar, ['qris', 'tunai', 'transfer', 'debit', 'kartu kredit'], true)) {
    $metodeBayar = 'qris';
}

$hasTindakanId = column_exists($conn, 'invoice_items', 'tindakan_id');
$hasNomorGigi  = column_exists($conn, 'invoice_items', 'nomor_gigi');
$hasKeterangan = column_exists($conn, 'invoice_items', 'keterangan');

$conn->begin_transaction();

try {
    if ($id > 0) {
        $existing = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$id]);
        if (!$existing) {
            throw new Exception('Invoice tidak ditemukan.');
        }

        $ok = db_run(
            "UPDATE invoice
             SET pasien_id=?, kunjungan_id=?, no_invoice=?, tanggal=?, subtotal=?, diskon=?, total=?, status_bayar=?, metode_bayar=?, catatan=?
             WHERE id=?",
            [
                $pasienId,
                $kunjunganId > 0 ? $kunjunganId : null,
                $noInvoice,
                $tanggal,
                $subtotalPost,
                $diskon,
                $totalPost,
                $statusBayar,
                $metodeBayar,
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
             (pasien_id, kunjungan_id, no_invoice, tanggal, subtotal, diskon, total, status_bayar, metode_bayar, catatan)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            [
                $pasienId,
                $kunjunganId > 0 ? $kunjunganId : null,
                $noInvoice,
                $tanggal,
                $subtotalPost,
                $diskon,
                $totalPost,
                $statusBayar,
                $metodeBayar,
                $catatan
            ]
        );

        if (!$invoiceId) {
            throw new Exception('Gagal membuat invoice baru.');
        }
    }

    $jumlahItemMasuk = 0;

    foreach ($namaItems as $i => $nama) {
        $nama = trim((string)$nama);
        if ($nama === '') continue;

        $qty       = to_float($qtyItems[$i] ?? 1);
        $harga     = to_float($hargaItems[$i] ?? 0);
        $subtotal  = to_float($subtotalItems[$i] ?? 0);
        $tindakan  = (int)($tindakanIds[$i] ?? 0);
        $nomorGigi = trim((string)($nomorGigiItems[$i] ?? ''));
        $ket       = trim((string)($keteranganItem[$i] ?? ''));

        if ($qty <= 0) $qty = 1;
        if ($harga < 0) $harga = 0;
        if ($subtotal <= 0) $subtotal = $qty * $harga;

        if ($hasTindakanId && $hasNomorGigi && $hasKeterangan) {
            $itemId = db_insert(
                "INSERT INTO invoice_items
                 (invoice_id, tindakan_id, nama_item, qty, harga, subtotal, nomor_gigi, keterangan)
                 VALUES (?,?,?,?,?,?,?,?)",
                [
                    $invoiceId,
                    $tindakan > 0 ? $tindakan : null,
                    $nama,
                    $qty,
                    $harga,
                    $subtotal,
                    $nomorGigi,
                    $ket
                ]
            );
        } elseif ($hasTindakanId && $hasNomorGigi && !$hasKeterangan) {
            $itemId = db_insert(
                "INSERT INTO invoice_items
                 (invoice_id, tindakan_id, nama_item, qty, harga, subtotal, nomor_gigi)
                 VALUES (?,?,?,?,?,?,?)",
                [
                    $invoiceId,
                    $tindakan > 0 ? $tindakan : null,
                    $nama,
                    $qty,
                    $harga,
                    $subtotal,
                    $nomorGigi
                ]
            );
        } elseif ($hasTindakanId && !$hasNomorGigi && $hasKeterangan) {
            $itemId = db_insert(
                "INSERT INTO invoice_items
                 (invoice_id, tindakan_id, nama_item, qty, harga, subtotal, keterangan)
                 VALUES (?,?,?,?,?,?,?)",
                [
                    $invoiceId,
                    $tindakan > 0 ? $tindakan : null,
                    $nama,
                    $qty,
                    $harga,
                    $subtotal,
                    $ket
                ]
            );
        } elseif (!$hasTindakanId && $hasNomorGigi && $hasKeterangan) {
            $itemId = db_insert(
                "INSERT INTO invoice_items
                 (invoice_id, nama_item, qty, harga, subtotal, nomor_gigi, keterangan)
                 VALUES (?,?,?,?,?,?,?)",
                [
                    $invoiceId,
                    $nama,
                    $qty,
                    $harga,
                    $subtotal,
                    $nomorGigi,
                    $ket
                ]
            );
        } elseif ($hasTindakanId && !$hasNomorGigi && !$hasKeterangan) {
            $itemId = db_insert(
                "INSERT INTO invoice_items
                 (invoice_id, tindakan_id, nama_item, qty, harga, subtotal)
                 VALUES (?,?,?,?,?,?)",
                [
                    $invoiceId,
                    $tindakan > 0 ? $tindakan : null,
                    $nama,
                    $qty,
                    $harga,
                    $subtotal
                ]
            );
        } elseif (!$hasTindakanId && $hasNomorGigi && !$hasKeterangan) {
            $itemId = db_insert(
                "INSERT INTO invoice_items
                 (invoice_id, nama_item, qty, harga, subtotal, nomor_gigi)
                 VALUES (?,?,?,?,?,?)",
                [
                    $invoiceId,
                    $nama,
                    $qty,
                    $harga,
                    $subtotal,
                    $nomorGigi
                ]
            );
        } elseif (!$hasTindakanId && !$hasNomorGigi && $hasKeterangan) {
            $itemId = db_insert(
                "INSERT INTO invoice_items
                 (invoice_id, nama_item, qty, harga, subtotal, keterangan)
                 VALUES (?,?,?,?,?,?)",
                [
                    $invoiceId,
                    $nama,
                    $qty,
                    $harga,
                    $subtotal,
                    $ket
                ]
            );
        } else {
            $itemId = db_insert(
                "INSERT INTO invoice_items
                 (invoice_id, nama_item, qty, harga, subtotal)
                 VALUES (?,?,?,?,?)",
                [
                    $invoiceId,
                    $nama,
                    $qty,
                    $harga,
                    $subtotal
                ]
            );
        }

        if (!$itemId) {
            throw new Exception('Gagal menyimpan item invoice: ' . $nama);
        }

        $jumlahItemMasuk++;
    }

    if ($jumlahItemMasuk <= 0) {
        throw new Exception('Minimal harus ada 1 item invoice.');
    }

    $sum = db_fetch_one(
        "SELECT COALESCE(SUM(subtotal),0) AS subtotal FROM invoice_items WHERE invoice_id=?",
        [$invoiceId]
    );

    $subtotalFinal = (float)($sum['subtotal'] ?? 0);
    $totalFinal = $subtotalFinal - $diskon;
    if ($totalFinal < 0) $totalFinal = 0;

    db_run(
        "UPDATE invoice SET subtotal=?, total=? WHERE id=?",
        [$subtotalFinal, $totalFinal, $invoiceId]
    );

    sync_invoice_finance($invoiceId);

    $conn->commit();

    $_SESSION['success'] = 'Invoice berhasil disimpan.';
    header('Location: invoice_pdf.php?id=' . $invoiceId);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Gagal simpan invoice: ' . $e->getMessage();
    header('Location: invoice.php' . ($id > 0 ? '?edit=' . $id : ''));
    exit;
}
