<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    $_SESSION['error'] = 'Koneksi database tidak tersedia.';
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$pasien_id     = (int)($_POST['pasien_id'] ?? 0);
$kunjungan_id  = (int)($_POST['kunjungan_id'] ?? 0);
$nomor_gigi    = trim($_POST['nomor_gigi'] ?? '');
$surface_code  = trim($_POST['surface_code'] ?? '');
$tindakan_id   = (int)($_POST['tindakan_id'] ?? 0);
$nama_tindakan = trim($_POST['nama_tindakan'] ?? '');
$harga         = (float)($_POST['harga'] ?? 0);
$qty           = (float)($_POST['qty'] ?? 1);
$subtotal      = (float)($_POST['subtotal'] ?? 0);
$satuan_harga  = trim($_POST['satuan_harga'] ?? 'per tindakan');
$catatan       = trim($_POST['catatan'] ?? '');

if ($pasien_id <= 0 || $kunjungan_id <= 0) {
    $_SESSION['error'] = 'Pasien dan kunjungan wajib valid.';
    header('Location: odontogram.php');
    exit;
}

if ($nomor_gigi === '') {
    $_SESSION['error'] = 'Nomor gigi wajib diisi.';
    header('Location: odontogram.php?pasien_id=' . $pasien_id . '&kunjungan_id=' . $kunjungan_id);
    exit;
}

if ($tindakan_id <= 0 && $nama_tindakan === '') {
    $_SESSION['error'] = 'Tindakan wajib dipilih.';
    header('Location: odontogram.php?pasien_id=' . $pasien_id . '&kunjungan_id=' . $kunjungan_id);
    exit;
}

if ($qty <= 0) {
    $qty = 1;
}

if ($subtotal <= 0) {
    $subtotal = $harga * $qty;
}

$pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasien_id]);
$kunjungan = db_fetch_one("SELECT * FROM kunjungan WHERE id = ? AND pasien_id = ?", [$kunjungan_id, $pasien_id]);

if (!$pasien) {
    $_SESSION['error'] = 'Pasien tidak ditemukan.';
    header('Location: odontogram.php');
    exit;
}

if (!$kunjungan) {
    $_SESSION['error'] = 'Kunjungan tidak ditemukan.';
    header('Location: odontogram.php?pasien_id=' . $pasien_id);
    exit;
}

$kategori = null;

if ($tindakan_id > 0) {
    $tindakan = db_fetch_one("SELECT * FROM tindakan WHERE id = ?", [$tindakan_id]);
    if ($tindakan) {
        if ($nama_tindakan === '') {
            $nama_tindakan = $tindakan['nama_tindakan'] ?? '';
        }
        if ($harga <= 0) {
            $harga = (float)($tindakan['harga'] ?? 0);
        }
        if ($subtotal <= 0) {
            $subtotal = $harga * $qty;
        }
        $kategori = $tindakan['kategori'] ?? null;
        if ($satuan_harga === '') {
            $satuan_harga = $tindakan['satuan_harga'] ?? 'per tindakan';
        }
    }
}

$conn->begin_transaction();

try {
    // 1. Simpan ke odontogram_tindakan
    $odonto_id = db_insert(
        "INSERT INTO odontogram_tindakan
        (pasien_id, kunjungan_id, nomor_gigi, surface_code, tindakan_id, nama_tindakan, kategori, harga, qty, subtotal, satuan_harga, catatan)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $pasien_id,
            $kunjungan_id,
            $nomor_gigi,
            $surface_code,
            $tindakan_id,
            $nama_tindakan,
            $kategori,
            $harga,
            $qty,
            $subtotal,
            $satuan_harga,
            $catatan
        ]
    );

    if (!$odonto_id) {
        throw new Exception('Gagal menyimpan odontogram.');
    }

    // 2. Cari invoice existing
    $invoice = db_fetch_one(
        "SELECT * FROM invoice WHERE pasien_id = ? AND kunjungan_id = ? ORDER BY id DESC LIMIT 1",
        [$pasien_id, $kunjungan_id]
    );

    // 3. Kalau belum ada, buat invoice
    if (!$invoice) {
        $no_invoice = function_exists('next_invoice_no') ? next_invoice_no() : ('INV-' . date('YmdHis'));

        $invoice_id = db_insert(
            "INSERT INTO invoice
            (no_invoice, pasien_id, kunjungan_id, tanggal, subtotal, diskon, total, status_bayar, metode_bayar, catatan)
            VALUES (?, ?, ?, NOW(), 0, 0, 0, 'belum terbayar', 'tunai', ?)",
            [
                $no_invoice,
                $pasien_id,
                $kunjungan_id,
                'Auto dibuat dari odontogram'
            ]
        );

        if (!$invoice_id) {
            throw new Exception('Gagal membuat invoice otomatis.');
        }

        $invoice = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$invoice_id]);
    }

    $invoice_id = (int)$invoice['id'];

    // 4. Dorong ke invoice_items SESUAI STRUKTUR TABEL ANDA
    $invoice_item_id = db_insert(
        "INSERT INTO invoice_items
        (invoice_id, treatment_id, nama_tindakan, qty, harga, subtotal, tooth_number, surface_code, sumber)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $invoice_id,
            $tindakan_id,
            $nama_tindakan,
            $qty,
            $harga,
            $subtotal,
            $nomor_gigi,
            $surface_code,
            'manual'
        ]
    );

    if (!$invoice_item_id) {
        throw new Exception('Gagal menyimpan item invoice.');
    }

    // 5. Hitung ulang subtotal invoice
    $sum = db_fetch_one(
        "SELECT COALESCE(SUM(subtotal),0) AS total_subtotal FROM invoice_items WHERE invoice_id = ?",
        [$invoice_id]
    );

    $subtotal_baru = (float)($sum['total_subtotal'] ?? 0);
    $diskon = (float)($invoice['diskon'] ?? 0);
    $total_baru = max(0, $subtotal_baru - $diskon);

    $okUpdate = db_run(
        "UPDATE invoice SET subtotal = ?, total = ?, updated_at = NOW() WHERE id = ?",
        [$subtotal_baru, $total_baru, $invoice_id]
    );

    if (!$okUpdate) {
        throw new Exception('Gagal update total invoice.');
    }

    if (function_exists('sync_invoice_finance')) {
        sync_invoice_finance($invoice_id);
    }

    $conn->commit();

    $_SESSION['success'] = 'Odontogram berhasil disimpan dan masuk ke invoice.';
    header('Location: invoice.php?edit=' . $invoice_id);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Gagal menyimpan odontogram: ' . $e->getMessage();
    header('Location: odontogram.php?pasien_id=' . $pasien_id . '&kunjungan_id=' . $kunjungan_id);
    exit;
}
