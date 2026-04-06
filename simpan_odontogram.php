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

function post_clean($key, $default = '')
{
    return trim($_POST[$key] ?? $default);
}

$pasien_id    = (int)($_POST['pasien_id'] ?? 0);
$kunjungan_id = (int)($_POST['kunjungan_id'] ?? 0);
$nomor_gigi   = post_clean('nomor_gigi');
$surface_code = strtoupper(post_clean('surface_code'));
$tindakan_id  = (int)($_POST['tindakan_id'] ?? 0);
$nama_tindakan = post_clean('nama_tindakan');
$harga        = (float)($_POST['harga'] ?? 0);
$qty          = (float)($_POST['qty'] ?? 1);
$subtotal     = (float)($_POST['subtotal'] ?? 0);
$satuan_harga = post_clean('satuan_harga', 'per tindakan');
$catatan      = post_clean('catatan');

if ($pasien_id <= 0) {
    $_SESSION['error'] = 'Pasien tidak valid.';
    header('Location: odontogram.php');
    exit;
}

if ($kunjungan_id <= 0) {
    $_SESSION['error'] = 'Kunjungan tidak valid.';
    header('Location: odontogram.php?pasien_id=' . $pasien_id);
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

if ($harga < 0) {
    $harga = 0;
}

if ($qty <= 0) {
    $qty = 1;
}

if ($subtotal <= 0) {
    $subtotal = $harga * $qty;
}

if (!table_exists($conn, 'odontogram_tindakan')) {
    $_SESSION['error'] = 'Tabel odontogram_tindakan belum ada.';
    header('Location: odontogram.php?pasien_id=' . $pasien_id . '&kunjungan_id=' . $kunjungan_id);
    exit;
}

// validasi pasien
$pasien = db_fetch_one("SELECT * FROM pasien WHERE id = ?", [$pasien_id]);
if (!$pasien) {
    $_SESSION['error'] = 'Data pasien tidak ditemukan.';
    header('Location: odontogram.php');
    exit;
}

// validasi kunjungan
$kunjungan = db_fetch_one("SELECT * FROM kunjungan WHERE id = ? AND pasien_id = ?", [$kunjungan_id, $pasien_id]);
if (!$kunjungan) {
    $_SESSION['error'] = 'Data kunjungan tidak ditemukan atau tidak sesuai pasien.';
    header('Location: odontogram.php?pasien_id=' . $pasien_id);
    exit;
}

// ambil data tindakan kalau ada
$kategori = '';
if ($tindakan_id > 0 && table_exists($conn, 'tindakan')) {
    $tindakan = db_fetch_one("SELECT * FROM tindakan WHERE id = ?", [$tindakan_id]);
    if ($tindakan) {
        if ($nama_tindakan === '') {
            $nama_tindakan = $tindakan['nama_tindakan'] ?? $tindakan['nama'] ?? 'Tindakan';
        }
        if ($harga <= 0) {
            $harga = (float)($tindakan['harga'] ?? 0);
        }
        $kategori = $tindakan['kategori'] ?? '';
        if ($satuan_harga === '') {
            $satuan_harga = $tindakan['satuan_harga'] ?? 'per tindakan';
        }
        $subtotal = $harga * $qty;
    }
}

$conn->begin_transaction();

try {
    // 1. simpan ke odontogram_tindakan
    $odontogram_id = db_insert(
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

    if (!$odontogram_id) {
        throw new Exception('Gagal menyimpan odontogram.');
    }

    // 2. cari invoice aktif untuk pasien + kunjungan
    $invoice = null;
    if (table_exists($conn, 'invoice')) {
        $invoice = db_fetch_one(
            "SELECT * FROM invoice WHERE pasien_id = ? AND kunjungan_id = ? ORDER BY id DESC LIMIT 1",
            [$pasien_id, $kunjungan_id]
        );
    }

    // 3. kalau belum ada invoice, buat baru
    if (!$invoice) {
        $invoice_no = function_exists('next_invoice_no') ? next_invoice_no() : ('INV-' . date('YmdHis'));
        $tanggal_invoice = date('Y-m-d H:i:s');

        $invoice_id = db_insert(
            "INSERT INTO invoice
            (pasien_id, kunjungan_id, no_invoice, tanggal, subtotal, diskon, total, status_bayar, metode_bayar, catatan)
            VALUES (?, ?, ?, ?, 0, 0, 0, 'pending', 'tunai', ?)",
            [
                $pasien_id,
                $kunjungan_id,
                $invoice_no,
                $tanggal_invoice,
                'Auto dibuat dari odontogram'
            ]
        );

        if (!$invoice_id) {
            throw new Exception('Gagal membuat invoice otomatis.');
        }

        $invoice = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$invoice_id]);
    }

    $invoice_id = (int)$invoice['id'];

    // 4. masukkan item ke invoice_items
    if (!table_exists($conn, 'invoice_items')) {
        throw new Exception('Tabel invoice_items belum ada.');
    }

    $invoice_item_id = db_insert(
        "INSERT INTO invoice_items
        (invoice_id, tindakan_id, nama_item, qty, harga, subtotal, nomor_gigi, keterangan)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        [
            $invoice_id,
            $tindakan_id > 0 ? $tindakan_id : null,
            $nama_tindakan,
            $qty,
            $harga,
            $subtotal,
            $nomor_gigi,
            $catatan
        ]
    );

    if (!$invoice_item_id) {
        throw new Exception('Gagal mendorong item ke invoice.');
    }

    // 5. hitung ulang subtotal invoice
    $sum = db_fetch_one(
        "SELECT COALESCE(SUM(subtotal),0) AS subtotal_baru FROM invoice_items WHERE invoice_id = ?",
        [$invoice_id]
    );

    $subtotal_baru = (float)($sum['subtotal_baru'] ?? 0);
    $diskon_lama = (float)($invoice['diskon'] ?? 0);
    $total_baru = max(0, $subtotal_baru - $diskon_lama);

    $ok_update_invoice = db_run(
        "UPDATE invoice SET subtotal = ?, total = ?, updated_at = NOW() WHERE id = ?",
        [$subtotal_baru, $total_baru, $invoice_id]
    );

    if (!$ok_update_invoice) {
        throw new Exception('Gagal update total invoice.');
    }

    // 6. sinkron ke keuangan kalau status lunas
    if (function_exists('sync_invoice_finance')) {
        sync_invoice_finance($invoice_id);
    }

    $conn->commit();

    $_SESSION['success'] = 'Odontogram berhasil disimpan dan masuk ke billing.';
    header('Location: invoice.php?edit=' . $invoice_id);
    exit;

} catch (Throwable $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Gagal menyimpan odontogram: ' . $e->getMessage();
    header('Location: odontogram.php?pasien_id=' . $pasien_id . '&kunjungan_id=' . $kunjungan_id);
    exit;
}
