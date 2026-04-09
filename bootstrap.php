<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

mysqli_report(MYSQLI_REPORT_OFF);

require_once __DIR__ . '/config.php';

if (!defined('KLINIK_NAMA')) define('KLINIK_NAMA', 'Klinik Praktek Mandiri Dokter Gigi Andreas Aryo Risky Prasetyo');
if (!defined('KLINIK_ALAMAT')) define('KLINIK_ALAMAT', 'Jln. Illago Boulevard Ruko Mendrisio blok e16-17');
if (!defined('KLINIK_TELP')) define('KLINIK_TELP', '0811-118-17-18');

function db(): ?mysqli
{
    global $conn, $koneksi, $mysqli, $db;

    if (isset($conn) && $conn instanceof mysqli) return $conn;
    if (isset($koneksi) && $koneksi instanceof mysqli) return $koneksi;
    if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
    if (isset($db) && $db instanceof mysqli) return $db;

    return null;
}

function table_exists(?mysqli $conn, string $table): bool
{
    if (!$conn) return false;
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function column_exists(?mysqli $conn, string $table, string $column): bool
{
    if (!$conn || !table_exists($conn, $table)) return false;

    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");

    return $res instanceof mysqli_result && $res->num_rows > 0;
}

function ensure_logged_in(): void
{
    if (
        !isset($_SESSION['user_id']) &&
        !isset($_SESSION['username']) &&
        !isset($_SESSION['nama']) &&
        !isset($_SESSION['user'])
    ) {
        header('Location: login.php');
        exit;
    }
}

function current_user_name(): string
{
    if (!empty($_SESSION['username'])) return (string)$_SESSION['username'];
    if (!empty($_SESSION['nama'])) return (string)$_SESSION['nama'];
    if (!empty($_SESSION['user']) && is_string($_SESSION['user'])) return $_SESSION['user'];
    if (!empty($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['username'])) {
        return (string)$_SESSION['user']['username'];
    }
    return 'Administrator';
}

function e($str): string
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($n): string
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function flash_message(): void
{
    if (!empty($_SESSION['success'])) {
        echo '<div style="background:#dcfce7;color:#166534;padding:12px 14px;border-radius:12px;margin-bottom:14px;border:1px solid #86efac;">' . e($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }

    if (!empty($_SESSION['error'])) {
        echo '<div style="background:#fee2e2;color:#991b1b;padding:12px 14px;border-radius:12px;margin-bottom:14px;border:1px solid #fca5a5;">' . e($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
}

function db_prepare_and_bind(mysqli $conn, string $query, array $params = []): mysqli_stmt|false
{
    $stmt = $conn->prepare($query);
    if (!$stmt) return false;

    if (!empty($params)) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            else $types .= 's';
        }
        $stmt->bind_param($types, ...$params);
    }

    return $stmt;
}

function db_fetch_all(string $query, array $params = []): array
{
    $conn = db();
    if (!$conn) return [];

    $stmt = db_prepare_and_bind($conn, $query, $params);
    if (!$stmt) return [];

    $ok = $stmt->execute();
    if (!$ok) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $rows = [];

    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $stmt->close();
    return $rows;
}

function db_fetch_one(string $query, array $params = []): ?array
{
    $rows = db_fetch_all($query, $params);
    return $rows[0] ?? null;
}

function db_run(string $query, array $params = []): bool
{
    $conn = db();
    if (!$conn) return false;

    $stmt = db_prepare_and_bind($conn, $query, $params);
    if (!$stmt) return false;

    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function db_insert(string $query, array $params = []): int|false
{
    $conn = db();
    if (!$conn) return false;

    $stmt = db_prepare_and_bind($conn, $query, $params);
    if (!$stmt) return false;

    $ok = $stmt->execute();
    $id = $ok ? (int)$conn->insert_id : false;
    $stmt->close();

    return $id;
}

function next_rm(): string
{
    $conn = db();
    if (!$conn || !table_exists($conn, 'pasien')) return 'RM000001';

    $row = db_fetch_one("SELECT no_rm FROM pasien ORDER BY id DESC LIMIT 1");
    $num = 1;

    if (!empty($row['no_rm']) && preg_match('/(\d+)$/', (string)$row['no_rm'], $m)) {
        $num = ((int)$m[1]) + 1;
    }

    return 'RM' . str_pad((string)$num, 6, '0', STR_PAD_LEFT);
}

function next_invoice_no(): string
{
    $conn = db();
    $date = date('Ymd');

    if (!$conn || !table_exists($conn, 'invoice')) {
        return 'INV-' . $date . '-0001';
    }

    $row = db_fetch_one(
        "SELECT no_invoice FROM invoice WHERE no_invoice LIKE ? ORDER BY id DESC LIMIT 1",
        ["INV-$date-%"]
    );

    $num = 1;
    if (!empty($row['no_invoice']) && preg_match('/-(\d+)$/', (string)$row['no_invoice'], $m)) {
        $num = ((int)$m[1]) + 1;
    }

    return 'INV-' . $date . '-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
}

function next_nomor_surat(): string
{
    return 'SK-' . date('Ymd-His');
}

function pasien_options(): array
{
    $conn = db();
    if (!$conn || !table_exists($conn, 'pasien')) return [];

    return db_fetch_all("SELECT id, no_rm, nama FROM pasien ORDER BY nama ASC");
}

function tindakan_options(): array
{
    $conn = db();
    if (!$conn || !table_exists($conn, 'tindakan')) return [];

    $sql = "SELECT * FROM tindakan";
    if (column_exists($conn, 'tindakan', 'aktif')) {
        $sql .= " WHERE aktif = 'yes'";
    }
    $sql .= " ORDER BY kategori ASC, nama_tindakan ASC, id ASC";

    return db_fetch_all($sql);
}

function icd10_options(string $keyword = ''): array
{
    $conn = db();
    if (!$conn || !table_exists($conn, 'icd10')) return [];

    if ($keyword !== '') {
        return db_fetch_all(
            "SELECT * FROM icd10 WHERE kode LIKE ? OR diagnosis LIKE ? ORDER BY kode ASC LIMIT 100",
            ["%$keyword%", "%$keyword%"]
        );
    }

    return db_fetch_all("SELECT * FROM icd10 ORDER BY kode ASC LIMIT 100");
}

function klinik_profile(): array
{
    $conn = db();
    if (!$conn || !table_exists($conn, 'settings_klinik')) {
        return [
            'nama_klinik' => KLINIK_NAMA,
            'alamat_klinik' => KLINIK_ALAMAT,
            'telepon_klinik' => KLINIK_TELP,
            'email_klinik' => '',
            'logo_path' => '',
            'qris_path' => '',
            'qris_payload' => ''
        ];
    }

    $row = db_fetch_one("SELECT * FROM settings_klinik ORDER BY id ASC LIMIT 1");

    return [
        'nama_klinik' => $row['nama_klinik'] ?? KLINIK_NAMA,
        'alamat_klinik' => $row['alamat_klinik'] ?? KLINIK_ALAMAT,
        'telepon_klinik' => $row['telepon_klinik'] ?? KLINIK_TELP,
        'email_klinik' => $row['email_klinik'] ?? '',
        'logo_path' => $row['logo_path'] ?? '',
        'qris_path' => $row['qris_path'] ?? '',
        'qris_payload' => $row['qris_payload'] ?? ''
    ];
}

function sync_invoice_finance(int $invoice_id): bool
{
    $conn = db();
    if (!$conn) return true;
    if (!table_exists($conn, 'invoice') || !table_exists($conn, 'keuangan')) return true;

    $inv = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [$invoice_id]);
    if (!$inv) return true;

    $status = strtolower(trim((string)($inv['status_bayar'] ?? '')));
    $total = (float)($inv['total'] ?? 0);

    if ($status !== 'lunas') {
        if (column_exists($conn, 'keuangan', 'invoice_id')) {
            db_run("DELETE FROM keuangan WHERE invoice_id = ?", [$invoice_id]);
        }
        return true;
    }

    $cek = null;
    if (column_exists($conn, 'keuangan', 'invoice_id')) {
        $cek = db_fetch_one("SELECT id FROM keuangan WHERE invoice_id = ?", [$invoice_id]);
    }

    $data = [];
    if (column_exists($conn, 'keuangan', 'invoice_id')) $data['invoice_id'] = $invoice_id;
    if (column_exists($conn, 'keuangan', 'pasien_id')) $data['pasien_id'] = (int)($inv['pasien_id'] ?? 0);
    if (column_exists($conn, 'keuangan', 'tanggal')) $data['tanggal'] = $inv['tanggal'] ?? date('Y-m-d H:i:s');
    if (column_exists($conn, 'keuangan', 'deskripsi')) $data['deskripsi'] = 'Pembayaran Invoice ' . ($inv['no_invoice'] ?? '');
    if (column_exists($conn, 'keuangan', 'metode_bayar')) $data['metode_bayar'] = $inv['metode_bayar'] ?? 'tunai';
    if (column_exists($conn, 'keuangan', 'nominal')) $data['nominal'] = $total;
    if (column_exists($conn, 'keuangan', 'jenis')) $data['jenis'] = 'pemasukan';
    if (column_exists($conn, 'keuangan', 'created_at')) $data['created_at'] = date('Y-m-d H:i:s');

    if (empty($data)) return true;

    if ($cek) {
        $setParts = [];
        $params = [];

        foreach ($data as $col => $val) {
            if ($col === 'invoice_id') continue;
            $setParts[] = "`$col` = ?";
            $params[] = $val;
        }

        $params[] = $invoice_id;

        $sql = "UPDATE keuangan SET " . implode(', ', $setParts) . " WHERE invoice_id = ?";
        return db_run($sql, $params);
    }

    $cols = [];
    $holders = [];
    $params = [];

    foreach ($data as $col => $val) {
        $cols[] = "`$col`";
        $holders[] = "?";
        $params[] = $val;
    }

    $sql = "INSERT INTO keuangan (" . implode(',', $cols) . ") VALUES (" . implode(',', $holders) . ")";
    return db_insert($sql, $params) !== false;
}
