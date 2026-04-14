<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

if (!table_exists($conn, 'invoice')) {
    die('Tabel invoice tidak ditemukan.');
}

function inv_redirect(int $invoiceId, bool $toDashboard = false): void
{
    if ($toDashboard) {
        header('Location: dashboard.php');
    } else {
        header('Location: invoice.php?edit=' . $invoiceId);
    }
    exit;
}

function recalc_invoice_totals(mysqli $conn, int $invoiceId): array
{
    $items = table_exists($conn, 'invoice_items')
        ? db_fetch_all("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC", [$invoiceId])
        : [];

    $subtotal = 0.0;
    foreach ($items as $it) {
        $subtotal += (float)($it['subtotal'] ?? 0);
    }

    $invoice = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$invoiceId]);
    $diskon = (float)($invoice['diskon'] ?? 0);
    $total = max(0, $subtotal - $diskon);

    return [
        'invoice' => $invoice,
        'subtotal' => $subtotal,
        'diskon' => $diskon,
        'total' => $total,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$invoice_id = (int)($_POST['invoice_id'] ?? 0);
if ($invoice_id <= 0) {
    die('Invoice tidak valid.');
}

$invoice = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$invoice_id]);
if (!$invoice) {
    die('Invoice tidak ditemukan.');
}

/*
|--------------------------------------------------------------------------
| Tambah item manual
|--------------------------------------------------------------------------
*/
if (isset($_POST['tambah_item'])) {
    if (!table_exists($conn, 'invoice_items')) {
        $_SESSION['error'] = 'Tabel invoice_items tidak ditemukan.';
        inv_redirect($invoice_id);
    }

    $treatment_id = (int)($_POST['treatment_id'] ?? 0);
    $nama_tindakan = trim($_POST['nama_tindakan'] ?? '');
    $qty = (float)($_POST['qty'] ?? 0);
    $harga = (float)($_POST['harga'] ?? 0);

    if ($nama_tindakan === '') {
        $_SESSION['error'] = 'Nama tindakan wajib diisi.';
        inv_redirect($invoice_id);
    }

    if ($qty <= 0) {
        $qty = 1;
    }

    if ($harga < 0) {
        $harga = 0;
    }

    $subtotal = round($qty * $harga, 2);

    // kalau invoice_items wajib treatment_id, paksa pilih master tindakan
    if (column_exists($conn, 'invoice_items', 'treatment_id') && $treatment_id <= 0) {
        $_SESSION['error'] = 'Pilih tindakan dari Master Tindakan terlebih dahulu.';
        inv_redirect($invoice_id);
    }

    $data = [];
    if (column_exists($conn, 'invoice_items', 'invoice_id')) $data['invoice_id'] = $invoice_id;
    if (column_exists($conn, 'invoice_items', 'treatment_id')) $data['treatment_id'] = $treatment_id;
    if (column_exists($conn, 'invoice_items', 'tindakan_id')) $data['tindakan_id'] = $treatment_id;
    if (column_exists($conn, 'invoice_items', 'nama_tindakan')) $data['nama_tindakan'] = $nama_tindakan;
    if (column_exists($conn, 'invoice_items', 'nama_item')) $data['nama_item'] = $nama_tindakan;
    if (column_exists($conn, 'invoice_items', 'qty')) $data['qty'] = $qty;
    if (column_exists($conn, 'invoice_items', 'harga')) $data['harga'] = $harga;
    if (column_exists($conn, 'invoice_items', 'subtotal')) $data['subtotal'] = $subtotal;
    if (column_exists($conn, 'invoice_items', 'keterangan')) $data['keterangan'] = 'manual';
    if (column_exists($conn, 'invoice_items', 'sumber')) $data['sumber'] = 'manual';
    if (column_exists($conn, 'invoice_items', 'created_at')) $data['created_at'] = date('Y-m-d H:i:s');

    $cols = [];
    $holders = [];
    $params = [];
    foreach ($data as $col => $val) {
        $cols[] = "`$col`";
        $holders[] = '?';
        $params[] = $val;
    }

    if (!$cols) {
        $_SESSION['error'] = 'Struktur invoice_items tidak sesuai.';
        inv_redirect($invoice_id);
    }

    if (db_insert(
        "INSERT INTO invoice_items (" . implode(',', $cols) . ") VALUES (" . implode(',', $holders) . ")",
        $params
    ) === false) {
        $_SESSION['error'] = 'Gagal menambah item invoice.';
        inv_redirect($invoice_id);
    }

    $totals = recalc_invoice_totals($conn, $invoice_id);
    db_run(
        "UPDATE invoice SET subtotal = ?, total = ? WHERE id = ?",
        [$totals['subtotal'], $totals['total'], $invoice_id]
    );

    $_SESSION['success'] = 'Item invoice berhasil ditambahkan.';
    inv_redirect($invoice_id);
}

/*
|--------------------------------------------------------------------------
| Simpan invoice / cicilan
|--------------------------------------------------------------------------
*/
if (isset($_POST['simpan_invoice']) || isset($_POST['selesai_dashboard'])) {
    $totals = recalc_invoice_totals($conn, $invoice_id);
    $subtotal = (float)$totals['subtotal'];

    $diskon = max(0, (float)($_POST['diskon'] ?? 0));
    $total = max(0, $subtotal - $diskon);

    $status_bayar = strtolower(trim($_POST['status_bayar'] ?? 'belum terbayar'));
    $metode_bayar = trim($_POST['metode_bayar'] ?? 'tunai');
    $catatan = trim($_POST['catatan'] ?? '');
    $tipe_pembayaran = trim($_POST['tipe_pembayaran'] ?? 'tunai');

    if (!in_array($tipe_pembayaran, ['tunai', 'cicilan'], true)) {
        $tipe_pembayaran = 'tunai';
    }

    $dp = max(0, (float)($_POST['dp'] ?? 0));
    $tenor_bulan = (int)($_POST['tenor_bulan'] ?? 2);
    $tanggal_mulai_cicilan = trim($_POST['tanggal_mulai_cicilan'] ?? date('Y-m-d'));
    if ($tanggal_mulai_cicilan === '') {
        $tanggal_mulai_cicilan = date('Y-m-d');
    }

    if ($tipe_pembayaran === 'cicilan') {
    if ($tenor_bulan < 2) $tenor_bulan = 2;
    if ($tenor_bulan > 12) $tenor_bulan = 12;
    if ($dp > $total) $dp = $total;
    $status_bayar = ($total > 0 && $dp >= $total) ? 'lunas' : 'cicilan';
} else {
    $dp = $total;
    $tenor_bulan = 0;
}

$allowedStatus = ['belum terbayar', 'pending', 'lunas', 'cicilan'];
$status_bayar = trim(strtolower($status_bayar));

if (!in_array($status_bayar, $allowedStatus, true)) {
    $status_bayar = 'belum terbayar';
}

    $sisa_tagihan = max(0, $total - $dp);
    $cicilan_per_bulan = ($tipe_pembayaran === 'cicilan' && $tenor_bulan >= 2)
        ? round($sisa_tagihan / $tenor_bulan, 2)
        : 0;

    $conn->begin_transaction();

    try {
        $parts = [];
        $params = [];

        if (column_exists($conn, 'invoice', 'subtotal')) {
            $parts[] = 'subtotal = ?';
            $params[] = $subtotal;
        }
        if (column_exists($conn, 'invoice', 'diskon')) {
            $parts[] = 'diskon = ?';
            $params[] = $diskon;
        }
        if (column_exists($conn, 'invoice', 'total')) {
            $parts[] = 'total = ?';
            $params[] = $total;
        }
        if (column_exists($conn, 'invoice', 'status_bayar')) {
            $parts[] = 'status_bayar = ?';
            $params[] = $status_bayar;
        }
        if (column_exists($conn, 'invoice', 'metode_bayar')) {
            $parts[] = 'metode_bayar = ?';
            $params[] = $metode_bayar;
        }
        if (column_exists($conn, 'invoice', 'catatan')) {
            $parts[] = 'catatan = ?';
            $params[] = $catatan;
        }
        if (column_exists($conn, 'invoice', 'tipe_pembayaran')) {
            $parts[] = 'tipe_pembayaran = ?';
            $params[] = $tipe_pembayaran;
        }
        if (column_exists($conn, 'invoice', 'tenor_bulan')) {
            $parts[] = 'tenor_bulan = ?';
            $params[] = $tipe_pembayaran === 'cicilan' ? $tenor_bulan : null;
        }
        if (column_exists($conn, 'invoice', 'dp')) {
            $parts[] = 'dp = ?';
            $params[] = $dp;
        }
        if (column_exists($conn, 'invoice', 'sisa_tagihan')) {
            $parts[] = 'sisa_tagihan = ?';
            $params[] = $sisa_tagihan;
        }
        if (column_exists($conn, 'invoice', 'cicilan_per_bulan')) {
            $parts[] = 'cicilan_per_bulan = ?';
            $params[] = $cicilan_per_bulan;
        }

        if (!$parts) {
            throw new RuntimeException('Kolom invoice tidak cukup untuk disimpan.');
        }

        $params[] = $invoice_id;
        if (!db_run("UPDATE invoice SET " . implode(', ', $parts) . " WHERE id = ?", $params)) {
            throw new RuntimeException('Gagal mengupdate invoice.');
        }

        if (table_exists($conn, 'invoice_cicilan')) {
            db_run("DELETE FROM invoice_cicilan WHERE invoice_id = ?", [$invoice_id]);

            if ($tipe_pembayaran === 'cicilan' && $sisa_tagihan > 0 && $tenor_bulan >= 2) {
                $baseNominal = floor(($sisa_tagihan / $tenor_bulan) * 100) / 100;
                $distributed = 0.0;

                for ($i = 1; $i <= $tenor_bulan; $i++) {
                    $nominal = ($i < $tenor_bulan)
                        ? $baseNominal
                        : round($sisa_tagihan - $distributed, 2);
                    $distributed += $nominal;

                    $jatuhTempo = date('Y-m-d', strtotime($tanggal_mulai_cicilan . ' +' . ($i - 1) . ' month'));

                    $cols = ['invoice_id', 'angsuran_ke', 'tanggal_jatuh_tempo', 'nominal', 'status'];
                    $vals = ['?', '?', '?', '?', '?'];
                    $rowParams = [$invoice_id, $i, $jatuhTempo, $nominal, 'belum_bayar'];

                    if (column_exists($conn, 'invoice_cicilan', 'created_at')) {
                        $cols[] = 'created_at';
                        $vals[] = '?';
                        $rowParams[] = date('Y-m-d H:i:s');
                    }

                    if (db_insert(
                        "INSERT INTO invoice_cicilan (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")",
                        $rowParams
                    ) === false) {
                        throw new RuntimeException('Gagal membuat jadwal cicilan.');
                    }
                }
            }
        }

        if (function_exists('sync_invoice_finance')) {
            sync_invoice_finance($invoice_id);
        }

        $conn->commit();
        $_SESSION['success'] = 'Invoice berhasil disimpan.';
    } catch (Throwable $e) {
        $conn->rollback();
        $_SESSION['error'] = 'Gagal menyimpan invoice: ' . $e->getMessage();
    }

    inv_redirect($invoice_id, isset($_POST['selesai_dashboard']));
}

$_SESSION['error'] = 'Aksi tidak dikenali.';
inv_redirect($invoice_id);
