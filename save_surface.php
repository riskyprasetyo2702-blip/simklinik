<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

header('Content-Type: text/plain; charset=utf-8');

$conn = db();
if (!$conn) {
    http_response_code(500);
    exit('Koneksi database gagal.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode tidak valid.');
}

function postv($key, $default = null) {
    return $_POST[$key] ?? $default;
}

function to_float($v) {
    if ($v === null || $v === '') return 0;
    $v = str_replace(['Rp', 'rp', '.', ' '], '', (string)$v);
    $v = str_replace(',', '.', $v);
    return (float)$v;
}

function table_exists_local($conn, $table) {
    return function_exists('table_exists') ? table_exists($conn, $table) : false;
}

function column_exists_local($conn, $table, $column) {
    return function_exists('column_exists') ? column_exists($conn, $table, $column) : false;
}

function ensure_odontogram_tindakan_exists($conn) {
    if (table_exists_local($conn, 'odontogram_tindakan')) return;

    $conn->query("
        CREATE TABLE IF NOT EXISTS odontogram_tindakan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kunjungan_id INT NOT NULL,
            nomor_gigi VARCHAR(20) NOT NULL,
            surface_code VARCHAR(10) DEFAULT NULL,
            tindakan_id INT DEFAULT NULL,
            nama_tindakan VARCHAR(255) NOT NULL,
            harga DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty DECIMAL(10,2) NOT NULL DEFAULT 1,
            subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
            satuan_harga VARCHAR(100) DEFAULT NULL,
            catatan TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function ensure_invoice_exists_for_visit($pasienId, $kunjunganId) {
    $row = db_fetch_one(
        "SELECT id FROM invoice WHERE kunjungan_id=? ORDER BY id DESC LIMIT 1",
        [$kunjunganId]
    );

    if ($row && !empty($row['id'])) {
        return (int)$row['id'];
    }

    $invoiceId = db_insert(
        "INSERT INTO invoice
        (pasien_id, kunjungan_id, no_invoice, tanggal, subtotal, diskon, total, status_bayar, metode_bayar, catatan)
        VALUES (?,?,?,?,0,0,0,'pending','qris','Auto draft dari odontogram')",
        [
            $pasienId,
            $kunjunganId,
            next_invoice_no(),
            date('Y-m-d H:i:s')
        ]
    );

    if (!$invoiceId) {
        throw new Exception('Gagal membuat invoice draft.');
    }

    return (int)$invoiceId;
}

function insert_invoice_item_safe($conn, $invoiceId, $tindakanId, $nama, $qty, $harga, $subtotal, $catatan = '') {
    $hasTindakanId = column_exists_local($conn, 'invoice_items', 'tindakan_id');

    if ($hasTindakanId) {
        $ok = db_insert(
            "INSERT INTO invoice_items (invoice_id, tindakan_id, nama_item, qty, harga, subtotal, keterangan)
             VALUES (?,?,?,?,?,?,?)",
            [$invoiceId, $tindakanId, $nama, $qty, $harga, $subtotal, $catatan]
        );
    } else {
        $ok = db_insert(
            "INSERT INTO invoice_items (invoice_id, nama_item, qty, harga, subtotal, keterangan)
             VALUES (?,?,?,?,?,?)",
            [$invoiceId, $nama, $qty, $harga, $subtotal, $catatan]
        );
    }

    if (!$ok) {
        throw new Exception('Gagal simpan item invoice dari odontogram.');
    }
}

function sync_invoice_total($invoiceId) {
    $sum = db_fetch_one(
        "SELECT COALESCE(SUM(subtotal),0) AS subtotal FROM invoice_items WHERE invoice_id=?",
        [$invoiceId]
    );

    $inv = db_fetch_one("SELECT diskon FROM invoice WHERE id=?", [$invoiceId]);
    $diskon = (float)($inv['diskon'] ?? 0);

    $subtotal = (float)($sum['subtotal'] ?? 0);
    $total = $subtotal - $diskon;
    if ($total < 0) $total = 0;

    db_run("UPDATE invoice SET subtotal=?, total=? WHERE id=?", [$subtotal, $total, $invoiceId]);
}

$patientId   = (int)(postv('patient_id', 0));
$visitId     = (int)(postv('visit_id', 0));
$toothNumber = trim((string)postv('tooth_number', ''));
$surfaceCode = strtoupper(trim((string)postv('surface_code', '')));
$tindakanId  = (int)(postv('tindakan_id', 0));
$namaTindakan = trim((string)postv('nama_tindakan', ''));
$harga       = to_float(postv('harga', 0));
$qty         = to_float(postv('qty', 1));
$subtotal    = to_float(postv('subtotal', 0));
$satuanHarga = trim((string)postv('satuan_harga', 'per tindakan'));
$catatan     = trim((string)postv('catatan', ''));
$sendToBilling = (string)postv('send_to_billing', '1') === '1';

if ($patientId <= 0) {
    http_response_code(422);
    exit('Pasien tidak valid.');
}

if ($visitId <= 0) {
    http_response_code(422);
    exit('Kunjungan tidak valid.');
}

if ($toothNumber === '') {
    http_response_code(422);
    exit('Nomor gigi wajib diisi.');
}

if ($surfaceCode === '') {
    http_response_code(422);
    exit('Surface wajib dipilih.');
}

if ($tindakanId <= 0) {
    http_response_code(422);
    exit('Tindakan wajib dipilih.');
}

if ($namaTindakan === '') {
    $master = db_fetch_one("SELECT * FROM tindakan WHERE id=? LIMIT 1", [$tindakanId]);
    $namaTindakan = $master['nama_tindakan'] ?? $master['nama'] ?? 'Tindakan';
}

if ($qty <= 0) $qty = 1;
if ($harga < 0) $harga = 0;
if ($subtotal <= 0) $subtotal = $qty * $harga;

$conn->begin_transaction();

try {
    ensure_odontogram_tindakan_exists($conn);

    $ok = db_insert(
        "INSERT INTO odontogram_tindakan
        (kunjungan_id, nomor_gigi, surface_code, tindakan_id, nama_tindakan, harga, qty, subtotal, satuan_harga, catatan)
        VALUES (?,?,?,?,?,?,?,?,?,?)",
        [
            $visitId,
            $toothNumber,
            $surfaceCode,
            $tindakanId,
            $namaTindakan,
            $harga,
            $qty,
            $subtotal,
            $satuanHarga,
            $catatan
        ]
    );

    if (!$ok) {
        throw new Exception('Gagal simpan odontogram_tindakan.');
    }

    if ($sendToBilling) {
        $invoiceId = ensure_invoice_exists_for_visit($patientId, $visitId);

        insert_invoice_item_safe(
            $conn,
            $invoiceId,
            $tindakanId,
            $namaTindakan,
            $qty,
            $harga,
            $subtotal,
            'Odontogram ' . $toothNumber . ' / ' . $surfaceCode
        );

        sync_invoice_total($invoiceId);

        if (function_exists('sync_invoice_finance')) {
            sync_invoice_finance($invoiceId);
        }
    }

    $conn->commit();
    exit('Tindakan odontogram berhasil disimpan.');
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    exit('Gagal menyimpan odontogram: ' . $e->getMessage());
}
