<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    $_SESSION['error'] = 'Koneksi database gagal.';
    header ('Location: invoice.php');
    exit;
}

function to_float($v) {
    if ($v === null || $v === '') return 0;
    $v = str_replace(',', '.', (string)$v);
    return (float)$v;
}

$id          = (int)($_POST['id'] ?? 0);
$pasienId    = (int)($_POST['pasien_id'] ?? 0);
$kunjunganId = (int)($_POST['kunjungan_id'] ?? 0);

$noInvoice   = trim($_POST['no_invoice'] ?? '');
$tanggal     = trim($_POST['tanggal'] ?? '');
$statusBayar = trim($_POST['status_bayar'] ?? 'pending');
$metodeBayar = trim($_POST['metode_bayar'] ?? 'qris');
$subtotal    = to_float($_POST['subtotal'] ?? 0);
$diskon      = to_float($_POST['diskon'] ?? 0);
$total       = to_float($_POST['total'] ?? 0);
$catatan     = trim($_POST['catatan'] ?? '');

$namaItems      = $_POST['nama_item'] ?? array();
$qtyItems       = $_POST['qty'] ?? array();
$hargaItems     = $_POST['harga'] ?? array();
$subtotalItems  = $_POST['subtotal_item'] ?? array();
$tindakanIds    = $_POST['tindakan_id'] ?? array();
$nomorGigiItems = $_POST['nomor_gigi'] ?? array();
$keteranganItem = $_POST['keterangan_item'] ?? array();

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

if (!in_array($statusBayar, array('lunas', 'pending', 'belum terbayar', 'paid'), true)) {
    $statusBayar = 'pending';
}

if (!in_array($metodeBayar, array('qris', 'tunai', 'transfer', 'debit', 'kartu kredit'), true)) {
    $metodeBayar = 'qris';
}

if ($subtotal < 0) $subtotal = 0;
if ($diskon < 0) $diskon = 0;
if ($total < 0) $total = 0;

$conn->begin_transaction();

try {
    if ($id > 0) {
        $existing = db_fetch_one("SELECT * FROM invoice WHERE id = ?", array($id));
        if (!$existing) {
            throw new Exception('Invoice tidak ditemukan.');
        }

        db_run(
            "UPDATE invoice
             SET pasien_id=?, kunjungan_id=?, no_invoice=?, tanggal=?, subtotal=?, diskon=?, total=?, status_bayar=?, metode_bayar=?, catatan=?
             WHERE id=?",
            array(
                $pasienId,
                $kunjunganId > 0 ? $kunjunganId : null,
                $noInvoice,
                $tanggal,
                $subtotal,
                $diskon,
                $total,
                $statusBayar,
                $metodeBayar,
                $catatan,
                $id
            )
        );

        db_run("DELETE FROM invoice_items WHERE invoice_id = ?", array($id));
        $invoiceId = $id;
    } else {
        $invoiceId = db_insert(
            "INSERT INTO invoice
             (pasien_id, kunjungan_id, no_invoice, tanggal, subtotal, diskon, total, status_bayar, metode_bayar, catatan)
             VALUES (?,?,?,?,?,?,?,?,?,?)",
            array(
                $pasienId,
                $kunjunganId > 0 ? $kunjunganId : null,
                $noInvoice,
                $tanggal,
                $subtotal,
                $diskon,
                $total,
                $statusBayar,
                $metodeBayar,
                $catatan
            )
        );

        if (!$invoiceId) {
            throw new Exception('Gagal membuat invoice baru.');
        }
    }

    $jumlahItemMasuk = 0;
    foreach ($namaItems as $i => $nama) {
        $nama = trim((string)$nama);
        $qty = to_float($qtyItems[$i] ?? 0);
        $harga = to_float($hargaItems[$i] ?? 0);
        $sub = to_float($subtotalItems[$i] ?? 0);
        $tindakanId = (int)($tindakanIds[$i] ?? 0);
        $nomorGigi = trim((string)($nomorGigiItems[$i] ?? ''));
        $ket = trim((string)($keteranganItem[$i] ?? ''));

        if ($nama === '') {
            continue;
        }

        if ($qty <= 0) $qty = 1;
        if ($harga < 0) $harga = 0;
        if ($sub <= 0) $sub = $qty * $harga;

        db_insert(
            "INSERT INTO invoice_items
             (invoice_id, tindakan_id, nama_item, qty, harga, subtotal, nomor_gigi, keterangan)
             VALUES (?,?,?,?,?,?,?,?)",
            array(
                $invoiceId,
                $tindakanId > 0 ? $tindakanId : null,
                $nama,
                $qty,
                $harga,
                $sub,
                $nomorGigi,
                $ket
            )
        );

        $jumlahItemMasuk++;
    }

    if ($jumlahItemMasuk <= 0) {
        throw new Exception('Minimal harus ada 1 item invoice.');
    }

    $sum = db_fetch_one(
        "SELECT COALESCE(SUM(subtotal),0) AS subtotal FROM invoice_items WHERE invoice_id = ?",
        array($invoiceId)
    );

    $subtotalAsli = (float)($sum['subtotal'] ?? 0);
    $totalAkhir = $subtotalAsli - $diskon;
    if ($totalAkhir < 0) $totalAkhir = 0;

    db_run(
        "UPDATE invoice SET subtotal=?, total=? WHERE id=?",
        array($subtotalAsli, $totalAkhir, $invoiceId)
    );

    sync_invoice_finance($invoiceId);

    $conn->commit();

    $_SESSION['success'] = 'Invoice berhasil disimpan dan sinkron ke keuangan.';
    header('Location: invoice.php?edit=' . $invoiceId);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Gagal simpan invoice: ' . $e->getMessage();
    header('Location: invoice.php' . ($id > 0 ? '?edit=' . $id : ''));
    exit;
}
