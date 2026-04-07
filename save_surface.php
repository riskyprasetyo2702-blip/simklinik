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

function to_float($v): float {
    if ($v === null || $v === '') return 0;
    $v = str_replace(['Rp', 'rp', '.', ' '], '', (string)$v);
    $v = str_replace(',', '.', $v);
    return (float)$v;
}

function first_existing_column_local(mysqli $conn, string $table, array $candidates): ?string {
    foreach ($candidates as $col) {
        if (function_exists('column_exists') && column_exists($conn, $table, $col)) {
            return $col;
        }
    }
    return null;
}

function insert_adaptive(mysqli $conn, string $table, array $data): int {
    $columns = [];
    $values  = [];

    foreach ($data as $col => $val) {
        if (function_exists('column_exists') && column_exists($conn, $table, $col)) {
            $columns[] = $col;
            $values[]  = $val;
        }
    }

    if (!$columns) {
        throw new Exception("Tidak ada kolom yang cocok untuk tabel {$table}.");
    }

    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO {$table} (" . implode(',', $columns) . ") VALUES ({$placeholders})";

    $id = db_insert($sql, $values);
    if (!$id) {
        throw new Exception("Gagal insert ke tabel {$table}.");
    }

    return (int)$id;
}

function upsert_surface(mysqli $conn, array $data): void {
    if (!function_exists('table_exists') || !table_exists($conn, 'odontogram_surfaces')) {
        return;
    }

    $visitCol   = first_existing_column_local($conn, 'odontogram_surfaces', ['visit_id', 'kunjungan_id']);
    $patientCol = first_existing_column_local($conn, 'odontogram_surfaces', ['patient_id', 'pasien_id']);
    $toothCol   = first_existing_column_local($conn, 'odontogram_surfaces', ['tooth_number', 'nomor_gigi']);
    $surfaceCol = first_existing_column_local($conn, 'odontogram_surfaces', ['surface_code', 'surface']);
    $condCol    = first_existing_column_local($conn, 'odontogram_surfaces', ['condition_code', 'condition']);
    $statusCol  = first_existing_column_local($conn, 'odontogram_surfaces', ['status_type', 'status']);

    if (!$visitCol || !$toothCol || !$surfaceCol || !$condCol) {
        return;
    }

    $visitId = $data[$visitCol] ?? 0;
    $tooth   = $data[$toothCol] ?? '';
    $surface = $data[$surfaceCol] ?? '';

    $sql = "SELECT id FROM odontogram_surfaces WHERE {$visitCol}=? AND {$toothCol}=? AND {$surfaceCol}=? LIMIT 1";
    $existing = db_fetch_one($sql, [$visitId, $tooth, $surface]);

    if ($existing) {
        $updates = [];
        $params = [];

        if ($patientCol && array_key_exists($patientCol, $data)) {
            $updates[] = "{$patientCol}=?";
            $params[] = $data[$patientCol];
        }
        $updates[] = "{$condCol}=?";
        $params[] = $data[$condCol];

        if ($statusCol && array_key_exists($statusCol, $data)) {
            $updates[] = "{$statusCol}=?";
            $params[] = $data[$statusCol];
        }

        if (!$updates) return;

        $params[] = (int)$existing['id'];
        $sqlUpdate = "UPDATE odontogram_surfaces SET " . implode(', ', $updates) . " WHERE id=?";
        db_run($sqlUpdate, $params);
    } else {
        insert_adaptive($conn, 'odontogram_surfaces', $data);
    }
}

function ensure_odontogram_tindakan_exists(mysqli $conn): void {
    if (function_exists('table_exists') && table_exists($conn, 'odontogram_tindakan')) {
        return;
    }

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

function ensure_invoice_exists_for_visit(int $pasienId, int $kunjunganId): int {
    $row = db_fetch_one("SELECT id FROM invoice WHERE kunjungan_id=? ORDER BY id DESC LIMIT 1", [$kunjunganId]);
    if ($row && !empty($row['id'])) {
        return (int)$row['id'];
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
        throw new Exception('Gagal membuat draft invoice odontogram.');
    }

    return (int)$invoiceId;
}

function insert_invoice_item_adaptive(mysqli $conn, int $invoiceId, int $tindakanId, string $nama, float $qty, float $harga, float $subtotal, string $nomorGigi, string $catatan): void {
    if (!function_exists('table_exists') || !table_exists($conn, 'invoice_items')) {
        return;
    }

    $nameCol = first_existing_column_local($conn, 'invoice_items', [
        'nama_item', 'item', 'deskripsi', 'nama_tindakan', 'tindakan', 'treatment_name'
    ]);

    if (!$nameCol) {
        throw new Exception('Kolom nama item invoice_items tidak ditemukan.');
    }

    $data = [
        'invoice_id'     => $invoiceId,
        $nameCol         => $nama,
        'qty'            => $qty,
        'harga'          => $harga,
        'price'          => $harga,
        'subtotal'       => $subtotal,
        'total'          => $subtotal,
        'nomor_gigi'     => $nomorGigi,
        'tooth_number'   => $nomorGigi,
        'keterangan'     => $catatan,
        'notes'          => $catatan,
        'tindakan_id'    => $tindakanId,
        'treatment_id'   => $tindakanId,
        'service_id'     => $tindakanId,
        'procedure_id'   => $tindakanId,
        'item_id'        => $tindakanId,
    ];

    insert_adaptive($conn, 'invoice_items', $data);
}

$patientId = (int)(postv('patient_id', postv('pasien_id', 0)));
$visitId   = (int)(postv('visit_id', postv('kunjungan_id', 0)));

$toothNumber   = trim((string)postv('tooth_number', postv('nomor_gigi', '')));
$surfaceCode   = strtoupper(trim((string)postv('surface_code', '')));
$conditionCode = trim((string)postv('condition_code', ''));
$statusType    = trim((string)postv('status_type', 'completed'));

$tindakanId   = (int)postv('tindakan_id', postv('treatment_id', 0));
$namaTindakan = trim((string)postv('nama_tindakan', ''));
$harga        = to_float(postv('harga', 0));
$qty          = to_float(postv('qty', 1));
$subtotal     = to_float(postv('subtotal', 0));
$satuanHarga  = trim((string)postv('satuan_harga', 'per tindakan'));
$catatan      = trim((string)postv('catatan', ''));
$sendToBilling = (string)postv('send_to_billing', '1') === '1';

if ($patientId <= 0 || $visitId <= 0) {
    http_response_code(422);
    exit('Pasien dan kunjungan belum valid.');
}

if ($toothNumber === '') {
    http_response_code(422);
    exit('Nomor gigi wajib diisi.');
}

if ($surfaceCode === '') {
    http_response_code(422);
    exit('Permukaan gigi wajib dipilih.');
}

if ($conditionCode === '' || $tindakanId <= 0) {
    http_response_code(422);
    exit('Tindakan odontogram wajib dipilih.');
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

    // simpan status permukaan bila tabel tersedia
    upsert_surface($conn, [
        'patient_id'      => $patientId,
        'pasien_id'       => $patientId,
        'visit_id'        => $visitId,
        'kunjungan_id'    => $visitId,
        'tooth_number'    => $toothNumber,
        'nomor_gigi'      => $toothNumber,
        'surface_code'    => $surfaceCode,
        'condition_code'  => $conditionCode,
        'status_type'     => $statusType,
        'status'          => $statusType
    ]);

    // simpan riwayat tindakan odontogram
    insert_adaptive($conn, 'odontogram_tindakan', [
        'patient_id'    => $patientId,
        'pasien_id'     => $patientId,
        'visit_id'      => $visitId,
        'kunjungan_id'  => $visitId,
        'tooth_number'  => $toothNumber,
        'nomor_gigi'    => $toothNumber,
        'surface_code'  => $surfaceCode,
        'tindakan_id'   => $tindakanId,
        'treatment_id'  => $tindakanId,
        'nama_tindakan' => $namaTindakan,
        'treatment_name'=> $namaTindakan,
        'harga'         => $harga,
        'qty'           => $qty,
        'subtotal'      => $subtotal,
        'satuan_harga'  => $satuanHarga,
        'catatan'       => $catatan,
        'notes'         => $catatan,
        'condition_code'=> $conditionCode,
        'status_type'   => $statusType,
        'created_at'    => date('Y-m-d H:i:s')
    ]);

    // hubungkan ke billing bila diminta
    if ($sendToBilling && function_exists('table_exists') && table_exists($conn, 'invoice')) {
        $invoiceId = ensure_invoice_exists_for_visit($patientId, $visitId);

        insert_invoice_item_adaptive(
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

        $sum = db_fetch_one(
            "SELECT COALESCE(SUM(subtotal),0) AS subtotal FROM invoice_items WHERE invoice_id=?",
            [$invoiceId]
        );

        $inv = db_fetch_one("SELECT diskon FROM invoice WHERE id=? LIMIT 1", [$invoiceId]);
        $diskon = (float)($inv['diskon'] ?? 0);
        $invoiceSubtotal = (float)($sum['subtotal'] ?? 0);
        $invoiceTotal = $invoiceSubtotal - $diskon;
        if ($invoiceTotal < 0) $invoiceTotal = 0;

        db_run("UPDATE invoice SET subtotal=?, total=? WHERE id=?", [$invoiceSubtotal, $invoiceTotal, $invoiceId]);
    }

    $conn->commit();
    exit('Tindakan odontogram berhasil disimpan.');
} catch (Throwable $e) {
    $conn->rollback();
    http_response_code(500);
    exit('Gagal menyimpan odontogram: ' . $e->getMessage());
}
