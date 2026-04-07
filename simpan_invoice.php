<?php
declare(strict_types=1);
session_start();

/**
 * GANTI sesuai file koneksi project kamu
 * Pastikan variabel koneksi adalah $conn (mysqli)
 */
require_once __DIR__ . '/config/koneksi.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Koneksi database tidak tersedia.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function post($key, $default = null) {
    return $_POST[$key] ?? $default;
}

function normalize_number($value): float {
    if ($value === null || $value === '') return 0;
    $value = str_replace(['Rp', 'rp', '.', ' '], '', (string)$value);
    $value = str_replace(',', '.', $value);
    return (float)$value;
}

function table_exists(mysqli $conn, string $table): bool {
    $sql = "SHOW TABLES LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows > 0;
}

function column_exists(mysqli $conn, string $table, string $column): bool {
    $sql = "SHOW COLUMNS FROM `$table` LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->num_rows > 0;
}

function ensure_column(mysqli $conn, string $table, string $column, string $definition): void {
    if (table_exists($conn, $table) && !column_exists($conn, $table, $column)) {
        $conn->query("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function ensure_keuangan_structure(mysqli $conn): void {
    if (!table_exists($conn, 'keuangan')) {
        return;
    }

    ensure_column($conn, 'keuangan', 'invoice_id', 'BIGINT NULL');
    ensure_column($conn, 'keuangan', 'pasien_id', 'BIGINT NULL');
    ensure_column($conn, 'keuangan', 'kunjungan_id', 'BIGINT NULL');
    ensure_column($conn, 'keuangan', 'metode_pembayaran', 'VARCHAR(100) NULL');
    ensure_column($conn, 'keuangan', 'status', 'VARCHAR(50) NULL');
    ensure_column($conn, 'keuangan', 'masuk', 'DECIMAL(15,2) NOT NULL DEFAULT 0');
    ensure_column($conn, 'keuangan', 'keluar', 'DECIMAL(15,2) NOT NULL DEFAULT 0');
    ensure_column($conn, 'keuangan', 'kategori', 'VARCHAR(100) NULL');
    ensure_column($conn, 'keuangan', 'keterangan', 'TEXT NULL');
    ensure_column($conn, 'keuangan', 'tanggal', 'DATETIME NULL');
    ensure_column($conn, 'keuangan', 'updated_at', 'DATETIME NULL');

    // Tambah unique index invoice_id kalau belum ada
    $checkIdx = $conn->query("
        SHOW INDEX FROM keuangan
        WHERE Key_name = 'uniq_keuangan_invoice'
    ");
    if ($checkIdx && $checkIdx->num_rows === 0 && column_exists($conn, 'keuangan', 'invoice_id')) {
        try {
            $conn->query("ALTER TABLE keuangan ADD UNIQUE KEY uniq_keuangan_invoice (invoice_id)");
        } catch (Throwable $e) {
            // abaikan jika gagal karena data lama duplikat
        }
    }
}

function ensure_invoice_items_structure(mysqli $conn): void {
    if (!table_exists($conn, 'invoice_items')) {
        $conn->query("
            CREATE TABLE invoice_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                invoice_id BIGINT NOT NULL,
                sumber VARCHAR(50) NULL,
                referensi_id BIGINT NULL,
                deskripsi VARCHAR(255) NOT NULL,
                qty DECIMAL(10,2) NOT NULL DEFAULT 1,
                harga DECIMAL(15,2) NOT NULL DEFAULT 0,
                subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_invoice_id (invoice_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}

function get_next_invoice_number(mysqli $conn): string {
    $prefix = 'INV/' . date('Ymd') . '/';
    $like = $prefix . '%';

    $stmt = $conn->prepare("SELECT no_invoice FROM invoice WHERE no_invoice LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    $next = 1;
    if ($res && !empty($res['no_invoice'])) {
        $last = $res['no_invoice'];
        $parts = explode('/', $last);
        $lastNo = (int)end($parts);
        $next = $lastNo + 1;
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function fetch_pasien(mysqli $conn, int $pasienId): ?array {
    if (!table_exists($conn, 'pasien')) return null;
    $stmt = $conn->prepare("SELECT * FROM pasien WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $pasienId);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc() ?: null;
}

function fetch_kunjungan(mysqli $conn, int $kunjunganId): ?array {
    if (!table_exists($conn, 'kunjungan')) return null;
    $stmt = $conn->prepare("SELECT * FROM kunjungan WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $kunjunganId);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res->fetch_assoc() ?: null;
}

function get_kunjungan_tindakan(mysqli $conn, int $kunjunganId): array {
    $candidates = [
        ['tabel' => 'kunjungan_tindakan', 'nama' => 'nama_tindakan', 'qty' => 'qty', 'harga' => 'harga'],
        ['tabel' => 'tindakan_kunjungan', 'nama' => 'nama_tindakan', 'qty' => 'qty', 'harga' => 'harga'],
        ['tabel' => 'detail_tindakan', 'nama' => 'tindakan', 'qty' => 'qty', 'harga' => 'harga'],
    ];

    foreach ($candidates as $c) {
        if (!table_exists($conn, $c['tabel'])) continue;
        if (!column_exists($conn, $c['tabel'], 'kunjungan_id')) continue;

        $sql = "SELECT 
                    id,
                    `" . $c['nama'] . "` AS nama_item,
                    COALESCE(`" . $c['qty'] . "`, 1) AS qty,
                    COALESCE(`" . $c['harga'] . "`, 0) AS harga
                FROM `" . $c['tabel'] . "`
                WHERE kunjungan_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $kunjunganId);
        $stmt->execute();
        $res = $stmt->get_result();

        $items = [];
        while ($row = $res->fetch_assoc()) {
            $qty = (float)$row['qty'];
            $harga = (float)$row['harga'];
            $items[] = [
                'sumber' => 'tindakan',
                'referensi_id' => (int)$row['id'],
                'deskripsi' => $row['nama_item'],
                'qty' => $qty,
                'harga' => $harga,
                'subtotal' => $qty * $harga,
            ];
        }
        return $items;
    }

    return [];
}

function get_odontogram_items(mysqli $conn, int $kunjunganId): array {
    $tables = ['odontogram', 'odontogram_detail', 'kunjungan_odontogram'];

    foreach ($tables as $table) {
        if (!table_exists($conn, $table)) continue;
        if (!column_exists($conn, $table, 'kunjungan_id')) continue;

        $availableCols = [];
        foreach (['nomor_gigi', 'gigi', 'tindakan', 'keterangan', 'harga'] as $col) {
            $availableCols[$col] = column_exists($conn, $table, $col);
        }

        $selects = ["id"];
        $selects[] = $availableCols['nomor_gigi'] ? "nomor_gigi" : ($availableCols['gigi'] ? "gigi" : "NULL AS nomor_gigi");
        $selects[] = $availableCols['tindakan'] ? "tindakan" : "NULL AS tindakan";
        $selects[] = $availableCols['keterangan'] ? "keterangan" : "NULL AS keterangan";
        $selects[] = $availableCols['harga'] ? "COALESCE(harga,0) AS harga" : "0 AS harga";

        $sql = "SELECT " . implode(', ', $selects) . " FROM `$table` WHERE kunjungan_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $kunjunganId);
        $stmt->execute();
        $res = $stmt->get_result();

        $items = [];
        while ($row = $res->fetch_assoc()) {
            $desc = 'Odontogram';
            if (!empty($row['nomor_gigi'])) $desc .= ' Gigi ' . $row['nomor_gigi'];
            if (!empty($row['tindakan'])) $desc .= ' - ' . $row['tindakan'];
            elseif (!empty($row['keterangan'])) $desc .= ' - ' . $row['keterangan'];

            $harga = (float)$row['harga'];
            if ($harga <= 0) continue;

            $items[] = [
                'sumber' => 'odontogram',
                'referensi_id' => (int)$row['id'],
                'deskripsi' => $desc,
                'qty' => 1,
                'harga' => $harga,
                'subtotal' => $harga,
            ];
        }
        return $items;
    }

    return [];
}

function get_manual_items_from_post(): array {
    $nama  = $_POST['item_nama'] ?? [];
    $qty   = $_POST['item_qty'] ?? [];
    $harga = $_POST['item_harga'] ?? [];

    $items = [];
    if (!is_array($nama)) return $items;

    foreach ($nama as $i => $n) {
        $deskripsi = trim((string)$n);
        $q = isset($qty[$i]) ? (float)normalize_number($qty[$i]) : 1;
        $h = isset($harga[$i]) ? (float)normalize_number($harga[$i]) : 0;

        if ($deskripsi === '') continue;
        if ($q <= 0) $q = 1;

        $items[] = [
            'sumber' => 'manual',
            'referensi_id' => null,
            'deskripsi' => $deskripsi,
            'qty' => $q,
            'harga' => $h,
            'subtotal' => $q * $h,
        ];
    }

    return $items;
}

function merge_invoice_items(array ...$groups): array {
    $result = [];
    foreach ($groups as $group) {
        foreach ($group as $item) {
            $result[] = $item;
        }
    }
    return $result;
}

function save_invoice_items(mysqli $conn, int $invoiceId, array $items): void {
    $stmtDelete = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
    $stmtDelete->bind_param('i', $invoiceId);
    $stmtDelete->execute();

    $stmt = $conn->prepare("
        INSERT INTO invoice_items
        (invoice_id, sumber, referensi_id, deskripsi, qty, harga, subtotal, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    foreach ($items as $item) {
        $sumber = $item['sumber'];
        $refId = $item['referensi_id'];
        $deskripsi = $item['deskripsi'];
        $qty = (float)$item['qty'];
        $harga = (float)$item['harga'];
        $subtotal = (float)$item['subtotal'];

        $stmt->bind_param(
            'isissdd',
            $invoiceId,
            $sumber,
            $refId,
            $deskripsi,
            $qty,
            $harga,
            $subtotal
        );
        $stmt->execute();
    }
}

function delete_keuangan_by_invoice(mysqli $conn, int $invoiceId): void {
    if (!table_exists($conn, 'keuangan')) return;
    if (!column_exists($conn, 'keuangan', 'invoice_id')) return;

    $stmt = $conn->prepare("DELETE FROM keuangan WHERE invoice_id = ?");
    $stmt->bind_param('i', $invoiceId);
    $stmt->execute();
}

function upsert_keuangan_for_invoice(
    mysqli $conn,
    int $invoiceId,
    int $pasienId,
    int $kunjunganId,
    string $noInvoice,
    float $total,
    string $metodePembayaran,
    string $statusPembayaran
): void {
    if (!table_exists($conn, 'keuangan')) return;

    $kategori = 'Pemasukan Invoice';
    $keterangan = 'Pembayaran invoice ' . $noInvoice;
    $tanggal = date('Y-m-d H:i:s');

    // cari existing
    $existing = null;
    if (column_exists($conn, 'keuangan', 'invoice_id')) {
        $stmt = $conn->prepare("SELECT id FROM keuangan WHERE invoice_id = ? LIMIT 1");
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
    }

    if ($existing) {
        $id = (int)$existing['id'];
        $stmt = $conn->prepare("
            UPDATE keuangan
            SET tanggal = ?, kategori = ?, keterangan = ?, masuk = ?, keluar = 0,
                pasien_id = ?, kunjungan_id = ?, metode_pembayaran = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param(
            'sssdiissi',
            $tanggal,
            $kategori,
            $keterangan,
            $total,
            $pasienId,
            $kunjunganId,
            $metodePembayaran,
            $statusPembayaran,
            $id
        );
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("
            INSERT INTO keuangan
            (tanggal, kategori, keterangan, masuk, keluar, invoice_id, pasien_id, kunjungan_id, metode_pembayaran, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param(
            'sssdiiiss',
            $tanggal,
            $kategori,
            $keterangan,
            $total,
            $invoiceId,
            $pasienId,
            $kunjunganId,
            $metodePembayaran,
            $statusPembayaran
        );
        $stmt->execute();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Metode tidak valid.');
}

try {
    ensure_keuangan_structure($conn);
    ensure_invoice_items_structure($conn);

    $invoiceId         = (int) post('invoice_id', 0);
    $pasienId          = (int) post('pasien_id', 0);
    $kunjunganId       = (int) post('kunjungan_id', 0);
    $tanggalInvoice    = trim((string) post('tanggal_invoice', date('Y-m-d')));
    $statusPembayaran  = strtolower(trim((string) post('status_pembayaran', 'pending')));
    $metodePembayaran  = trim((string) post('metode_pembayaran', ''));
    $diskon            = normalize_number(post('diskon', 0));
    $catatan           = trim((string) post('catatan', ''));
    $qrisString        = trim((string) post('qris_string', ''));
    $qrisImage         = trim((string) post('qris_image', ''));
    $autoTindakan      = (int) post('auto_tindakan', 1);
    $autoOdontogram    = (int) post('auto_odontogram', 1);

    if ($pasienId <= 0 || $kunjunganId <= 0) {
        throw new Exception('Pasien / kunjungan belum valid.');
    }

    if (!in_array($statusPembayaran, ['lunas', 'pending', 'belum lunas'], true)) {
        $statusPembayaran = 'pending';
    }

    $conn->begin_transaction();

    $pasien = fetch_pasien($conn, $pasienId);
    $kunjungan = fetch_kunjungan($conn, $kunjunganId);

    if (!$pasien) {
        throw new Exception('Data pasien tidak ditemukan.');
    }
    if (!$kunjungan) {
        throw new Exception('Data kunjungan tidak ditemukan.');
    }

    $manualItems = get_manual_items_from_post();
    $tindakanItems = $autoTindakan ? get_kunjungan_tindakan($conn, $kunjunganId) : [];
    $odontogramItems = $autoOdontogram ? get_odontogram_items($conn, $kunjunganId) : [];

    $allItems = merge_invoice_items($tindakanItems, $odontogramItems, $manualItems);

    if (count($allItems) === 0) {
        throw new Exception('Item invoice kosong. Tambahkan tindakan / item manual terlebih dahulu.');
    }

    $subtotal = 0;
    foreach ($allItems as $it) {
        $subtotal += (float)$it['subtotal'];
    }

    $total = $subtotal - $diskon;
    if ($total < 0) $total = 0;

    // pastikan tabel invoice punya kolom penting
    ensure_column($conn, 'invoice', 'pasien_id', 'BIGINT NULL');
    ensure_column($conn, 'invoice', 'kunjungan_id', 'BIGINT NULL');
    ensure_column($conn, 'invoice', 'tanggal_invoice', 'DATE NULL');
    ensure_column($conn, 'invoice', 'subtotal', 'DECIMAL(15,2) NOT NULL DEFAULT 0');
    ensure_column($conn, 'invoice', 'diskon', 'DECIMAL(15,2) NOT NULL DEFAULT 0');
    ensure_column($conn, 'invoice', 'total', 'DECIMAL(15,2) NOT NULL DEFAULT 0');
    ensure_column($conn, 'invoice', 'status_pembayaran', 'VARCHAR(50) NULL');
    ensure_column($conn, 'invoice', 'metode_pembayaran', 'VARCHAR(100) NULL');
    ensure_column($conn, 'invoice', 'catatan', 'TEXT NULL');
    ensure_column($conn, 'invoice', 'qris_string', 'TEXT NULL');
    ensure_column($conn, 'invoice', 'qris_image', 'VARCHAR(255) NULL');
    ensure_column($conn, 'invoice', 'updated_at', 'DATETIME NULL');

    if ($invoiceId > 0) {
        $stmt = $conn->prepare("
            UPDATE invoice
            SET pasien_id = ?, kunjungan_id = ?, tanggal_invoice = ?, subtotal = ?, diskon = ?, total = ?,
                status_pembayaran = ?, metode_pembayaran = ?, catatan = ?, qris_string = ?, qris_image = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param(
            'iisdddsssssi',
            $pasienId,
            $kunjunganId,
            $tanggalInvoice,
            $subtotal,
            $diskon,
            $total,
            $statusPembayaran,
            $metodePembayaran,
            $catatan,
            $qrisString,
            $qrisImage,
            $invoiceId
        );
        $stmt->execute();

        $stmt = $conn->prepare("SELECT no_invoice FROM invoice WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $invoiceId);
        $stmt->execute();
        $inv = $stmt->get_result()->fetch_assoc();
        $noInvoice = $inv['no_invoice'] ?? ('INV-' . $invoiceId);
    } else {
        $noInvoice = get_next_invoice_number($conn);

        $stmt = $conn->prepare("
            INSERT INTO invoice
            (no_invoice, pasien_id, kunjungan_id, tanggal_invoice, subtotal, diskon, total, status_pembayaran, metode_pembayaran, catatan, qris_string, qris_image, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->bind_param(
            'siisdddsssss',
            $noInvoice,
            $pasienId,
            $kunjunganId,
            $tanggalInvoice,
            $subtotal,
            $diskon,
            $total,
            $statusPembayaran,
            $metodePembayaran,
            $catatan,
            $qrisString,
            $qrisImage
        );
        $stmt->execute();
        $invoiceId = (int)$conn->insert_id;
    }

    save_invoice_items($conn, $invoiceId, $allItems);

    // sinkron ke keuangan
    if ($statusPembayaran === 'lunas') {
        upsert_keuangan_for_invoice(
            $conn,
            $invoiceId,
            $pasienId,
            $kunjunganId,
            $noInvoice,
            $total,
            $metodePembayaran,
            $statusPembayaran
        );
    } else {
        // kalau pending / belum lunas -> hapus dulu agar tidak dobel pemasukan
        delete_keuangan_by_invoice($conn, $invoiceId);
    }

    $conn->commit();

    $_SESSION['success'] = 'Invoice berhasil disimpan.';
    header('Location: invoice_pdf.php?id=' . $invoiceId);
    exit;

} catch (Throwable $e) {
    if ($conn->errno || $conn->thread_id) {
        try { $conn->rollback(); } catch (Throwable $rollbackError) {}
    }

    $_SESSION['error'] = 'Gagal simpan invoice: ' . $e->getMessage();
    header('Location: invoice.php?kunjungan_id=' . (int)post('kunjungan_id', 0));
    exit;
}