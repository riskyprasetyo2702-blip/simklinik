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

function first_existing_column_local($conn, $table, $candidates) {
    foreach ($candidates as $col) {
        if (function_exists('column_exists') && column_exists($conn, $table, $col)) {
            return $col;
        }
    }
    return null;
}

function table_exists_local($conn, $table) {
    return function_exists('table_exists') ? table_exists($conn, $table) : false;
}

function insert_adaptive_local($conn, $table, $data) {
    $columns = [];
    $values  = [];

    foreach ($data as $col => $val) {
        if (function_exists('column_exists') && column_exists($conn, $table, $col)) {
            $columns[] = $col;
            $values[]  = $val;
        }
    }

    if (!$columns) {
        throw new Exception("Tidak ada kolom cocok untuk tabel {$table}");
    }

    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES ({$placeholders})";

    $id = db_insert($sql, $values);
    if (!$id) {
        throw new Exception("Gagal insert ke {$table}");
    }

    return (int)$id;
}

function ensure_odontogram_tindakan_exists($conn) {
    if (table_exists_local($conn, 'odontogram_tindakan')) return;

    $conn->query("
        CREATE TABLE IF NOT EXISTS odontogram_tindakan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pasien_id INT NULL,
            kunjungan_id INT NULL,
            nomor_gigi VARCHAR(20) NOT NULL,
            surface_code VARCHAR(10) NULL,
            tindakan_id INT NULL,
            nama_tindakan VARCHAR(255) NOT NULL,
            harga DECIMAL(15,2) NOT NULL DEFAULT 0,
            qty DECIMAL(10,2) NOT NULL DEFAULT 1,
            subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
            satuan_harga VARCHAR(100) NULL,
            catatan TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function sync_invoice_total_local($invoiceId) {
    $sum = db_fetch_one(
        "SELECT COALESCE(SUM(subtotal),0) AS subtotal FROM invoice_items WHERE invoice_id=?",
        [$invoiceId]
    );

    $inv = db_fetch_one("SELECT diskon FROM invoice WHERE id=? LIMIT 1", [$invoiceId]);
    $diskon = (float)($inv['diskon'] ?? 0);

    $subtotal = (float)($sum['subtotal'] ?? 0);
    $total = $subtotal - $diskon;
    if ($total < 0) $total = 0;

    db_run("UPDATE invoice SET subtotal=?, total=? WHERE id=?", [$subtotal, $total, $invoiceId]);
}

function ensure_invoice_exists_for_visit_local($pasienId, $kunjunganId) {
    if (!table_exists_local(db(), 'invoice')) {
        throw new Exception('Tabel invoice tidak ditemukan.');
    }

    $existing = db_fetch_one(
        "SELECT id FROM invoice WHERE kunjungan_id=? ORDER BY id DESC LIMIT 1",
        [$kunjunganId]
    );

    if ($existing && !empty($existing['id'])) {
        return (int)$existing['id'];
    }

    $invoiceId = db_insert(
        "INSERT INTO invoice (pasien_id, kunjungan_id, no_invoice, tanggal, subtotal, diskon, total, status_bayar, metode_bayar, catatan)
         VALUES (?,?,?,?,0,0,0,'pending','qris','Auto draft dari odontogram')",
        [
            $pasienId,
            $kunjunganId,
            next_invoice_no(),
            date('Y-m-d H:i:s')
        ]
    );

    if (!$invoiceId) {
        throw new Exception('Gagal membuat draft invoice.');
    }

    return (int)$invoiceId;
}

function insert_invoice_item_adaptive_local($conn, $invoiceId, $tindakanId, $nama, $qty, $harga, $subtotal, $nomorGigi, $catatan) {
    if (!table_exists_local($conn, 'invoice_items')) return;

    $nameCol = first_existing_column_local($conn, 'invoice_items', [
        'nama_item',
        'item',
        'deskripsi',
        'nama_tindakan',
        'tindakan',
        'treatment_name'
    ]);

    if (!$nameCol) {
        throw new Exception('Kolom nama item invoice_items tidak ditemukan.');
    }

    $data = [
        'invoice_id'      => $invoiceId,
        $nameCol          => $nama,
        'qty'             => $qty,
        'harga'           => $harga,
        'price'           => $harga,
        'subtotal'        => $subtotal,
        'total'           => $subtotal,
        'nomor_gigi'      => $nomorGigi,
        'tooth_number'    => $nomorGigi,
        'keterangan'      => $catatan,
        'notes'           => $catatan,
        'tindakan_id'     => $tindakanId,
        'treatment_id'    => $tindakanId,
        'service_id'      => $tindakanId,
        'procedure_id'    => $tindakanId,
        'item_id'         => $tindakanId
    ];

    insert_adaptive_local($conn, 'invoice_items', $data);
}

$patientId   = (int)(postv('patient_id', postv('pasien_id', 0)));
$visitId     = (int)(postv('visit_id', postv('kunjungan_id', 0)));
$toothNumber = trim((string)postv('tooth_number', postv('nomor_gigi', '')));
$surfaceCode = strtoupper(trim((string)postv('surface_code', '')));
$conditionCode = trim((string)postv('condition_code', ''));
$statusType  = trim((string)postv('status_type', 'completed'));

$tindakanId   = (int)(postv('tindakan_id', postv('treatment_id', 0)));
$namaTindakan = trim((string)postv('nama_tindakan', ''));
$harga        = to_float(postv('harga', 0));
$qty          = to_float(postv('qty', 1));
$subtotal     = to_float(postv('subtotal', 0));
$satuanHarga  = trim((string)postv('satuan_harga', 'per tindakan'));
$catatan      = trim((string)postv('catatan', ''));
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
    exit('Permukaan gigi wajib dipilih.');
}

if ($tindakanId <= 0) {
    http_response_code(422);
    exit('Tindakan wajib dipilih.');
}

if ($namaTindakan === '') {
    $master = db_fetch_one("SELECT * FROM tindakan WHERE id=? LIMIT 1", [$tindakanId]);
    if ($master) {
        $namaTindakan = $master['nama_tindakan'] ?? $master['nama'] ?? 'Tindakan';
    } else {
        $namaTindakan = 'Tindakan';
    }
}

if ($qty <= 0) $qty = 1;
if ($harga < 0) $harga = 0;
if ($subtotal <= 0) $subtotal = $harga * $qty;

$conn->begin_transaction();

try {
    ensure_odontogram_tindakan_exists($conn);

    // simpan ke odontogram_tindakan
    insert_adaptive_local($conn, 'odontogram_tindakan', [
        'patient_id'      => $patientId,
        'pasien_id'       => $patientId,
        'visit_id'        => $visitId,
        'kunjungan_id'    => $visitId,
        'tooth_number'    => $toothNumber,
        'nomor_gigi'      => $toothNumber,
        'surface_code'    => $surfaceCode,
        'condition_code'  => $conditionCode,
        'status_type'     => $statusType,
        'tindakan_id'     => $tindakanId,
        'treatment_id'    => $tindakanId,
        'nama_tindakan'   => $namaTindakan,
        'treatment_name'  => $namaTindakan,
        'harga'           => $harga,
        'qty'             => $qty,
        'subtotal'        => $subtotal,
        'satuan_harga'    => $satuanHarga,
        'catatan'         => $catatan,
        'notes'           => $catatan,
        'created_at'      => date('Y-m-d H:i:s')
    ]);

    // simpan ke billing kalau dipilih
    if ($sendToBilling) {
        $invoiceId = ensure_invoice_exists_for_visit_local($patientId, $visitId);

        insert_invoice_item_adaptive_local(
            $conn,
            $invoiceId,
            $tindakanId,
            $namaTindakan,
            $qty,
            $harga,
            $subtotal,
            $toothNumber,
            'Odontogram ' . $toothNumber . ' / ' . $surfaceCode . ($catatan ? ' - ' . $catatan : '')
        );

        sync_invoice_total_local($invoiceId);

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
