<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

/*
|--------------------------------------------------------------------------
| KONFIGURASI KLINIK
|--------------------------------------------------------------------------
*/
define('KLINIK_NAMA', 'Klinik Praktek Mandiri Dokter Gigi Andreas Aryo Risky Prasetyo');
define('KLINIK_ALAMAT', 'Alamat klinik');
define('KLINIK_TELP', 'Telepon klinik');
define('QRIS_IMAGE_URL', '');
define('QRIS_PAYLOAD', '');

/*
|--------------------------------------------------------------------------
| DATABASE CONNECTION RESOLVER
|--------------------------------------------------------------------------
*/
function db()
{
    global $conn, $koneksi, $mysqli, $db, $pdo;

    if (isset($conn) && $conn instanceof mysqli) return $conn;
    if (isset($koneksi) && $koneksi instanceof mysqli) return $koneksi;
    if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
    if (isset($db) && $db instanceof mysqli) return $db;

    return null;
}

/*
|--------------------------------------------------------------------------
| AUTH / SESSION
|--------------------------------------------------------------------------
*/
function ensure_logged_in()
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

function current_user_name()
{
    if (!empty($_SESSION['username'])) return $_SESSION['username'];
    if (!empty($_SESSION['nama'])) return $_SESSION['nama'];

    if (!empty($_SESSION['user']) && is_string($_SESSION['user'])) {
        return $_SESSION['user'];
    }

    if (!empty($_SESSION['user']) && is_array($_SESSION['user']) && !empty($_SESSION['user']['username'])) {
        return $_SESSION['user']['username'];
    }

    return 'Administrator';
}

/*
|--------------------------------------------------------------------------
| HELPER UMUM
|--------------------------------------------------------------------------
*/
function e($str)
{
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function rupiah($n)
{
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function flash_message()
{
    if (!empty($_SESSION['success'])) {
        echo '<div style="background:#dcfce7;color:#166534;padding:12px 14px;border-radius:12px;margin-bottom:14px;border:1px solid #86efac;">'
            . e($_SESSION['success']) .
            '</div>';
        unset($_SESSION['success']);
    }

    if (!empty($_SESSION['error'])) {
        echo '<div style="background:#fee2e2;color:#991b1b;padding:12px 14px;border-radius:12px;margin-bottom:14px;border:1px solid #fca5a5;">'
            . e($_SESSION['error']) .
            '</div>';
        unset($_SESSION['error']);
    }
}

/*
|--------------------------------------------------------------------------
| DB STRUCTURE CHECKER
|--------------------------------------------------------------------------
*/
function table_exists($conn, $table)
{
    if (!$conn) return false;

    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function column_exists($conn, $table, $column)
{
    if (!$conn) return false;
    if (!table_exists($conn, $table)) return false;

    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);

    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

/*
|--------------------------------------------------------------------------
| QUERY HELPERS
|--------------------------------------------------------------------------
*/
function db_fetch_all($query, $params = [])
{
    $conn = db();
    if (!$conn) return [];

    $stmt = $conn->prepare($query);
    if (!$stmt) return [];

    if (!empty($params)) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $stmt->close();
    return $rows;
}

function db_fetch_one($query, $params = [])
{
    $rows = db_fetch_all($query, $params);
    return $rows[0] ?? null;
}

function db_run($query, $params = [])
{
    $conn = db();
    if (!$conn) return false;

    $stmt = $conn->prepare($query);
    if (!$stmt) return false;

    if (!empty($params)) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        $stmt->bind_param($types, ...$params);
    }

    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

function db_insert($query, $params = [])
{
    $conn = db();
    if (!$conn) return false;

    $stmt = $conn->prepare($query);
    if (!$stmt) return false;

    if (!empty($params)) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        $stmt->bind_param($types, ...$params);
    }

    $ok = $stmt->execute();
    $id = $ok ? $conn->insert_id : false;

    $stmt->close();
    return $id;
}

/*
|--------------------------------------------------------------------------
| NOMOR OTOMATIS
|--------------------------------------------------------------------------
*/
function next_rm()
{
    $conn = db();
    if (!$conn || !table_exists($conn, 'pasien')) {
        return 'RM000001';
    }

    $row = db_fetch_one("SELECT no_rm FROM pasien ORDER BY id DESC LIMIT 1");
    $num = 1;

    if (!empty($row['no_rm']) && preg_match('/(\d+)$/', $row['no_rm'], $m)) {
        $num = ((int)$m[1]) + 1;
    }

    return 'RM' . str_pad((string)$num, 6, '0', STR_PAD_LEFT);
}

function next_invoice_no()
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
    if (!empty($row['no_invoice']) && preg_match('/-(\d+)$/', $row['no_invoice'], $m)) {
        $num = ((int)$m[1]) + 1;
    }

    return 'INV-' . $date . '-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
}

function next_nomor_surat()
{
    return 'SK-' . date('Ymd-His');
}

/*
|--------------------------------------------------------------------------
| OPTION HELPERS
|--------------------------------------------------------------------------
*/
function pasien_options()
{
    $conn = db();
    if (!$conn || !table_exists($conn, 'pasien')) return [];

    return db_fetch_all("SELECT id, no_rm, nama FROM pasien ORDER BY nama ASC");
}

function tindakan_options()
{
    $conn = db();
    if (!$conn || !table_exists($conn, 'tindakan')) return [];

    $sql = "SELECT * FROM tindakan";
    if (column_exists($conn, 'tindakan', 'aktif')) {
        $sql .= " WHERE aktif='yes'";
    }

    if (column_exists($conn, 'tindakan', 'kategori') && column_exists($conn, 'tindakan', 'nama_tindakan')) {
        $sql .= " ORDER BY kategori ASC, nama_tindakan ASC, id ASC";
    } else {
        $sql .= " ORDER BY id ASC";
    }

    return db_fetch_all($sql);
}

function icd10_options($keyword = '')
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

/*
|--------------------------------------------------------------------------
| BILLING -> KEUANGAN SYNC
|--------------------------------------------------------------------------
| Penting:
| - invoice TIDAK boleh gagal hanya karena keuangan bermasalah
| - fungsi ini dibuat aman
|--------------------------------------------------------------------------
*/
function sync_invoice_finance($invoiceId)
{
    $conn = db();
    if (!$conn) return true;
    if (!table_exists($conn, 'invoice')) return true;

    $inv = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [(int)$invoiceId]);
    if (!$inv) return true;

    if (!table_exists($conn, 'keuangan')) {
        return true;
    }

    if (column_exists($conn, 'keuangan', 'invoice_id')) {
        db_run("DELETE FROM keuangan WHERE invoice_id = ?", [(int)$invoiceId]);
    }

    $status = strtolower(trim((string)($inv['status_bayar'] ?? '')));
    if (!in_array($status, ['lunas', 'paid'])) {
        return true;
    }

    $data = [];

    if (column_exists($conn, 'keuangan', 'tanggal')) {
        $data['tanggal'] = date('Y-m-d H:i:s');
    }
    if (column_exists($conn, 'keuangan', 'jenis')) {
        $data['jenis'] = 'pemasukan';
    }
    if (column_exists($conn, 'keuangan', 'deskripsi')) {
        $data['deskripsi'] = 'Pembayaran invoice ' . ($inv['no_invoice'] ?? '');
    }
    if (column_exists($conn, 'keuangan', 'nominal')) {
        $data['nominal'] = (float)($inv['total'] ?? 0);
    }
    if (column_exists($conn, 'keuangan', 'invoice_id')) {
        $data['invoice_id'] = (int)$invoiceId;
    }
    if (column_exists($conn, 'keuangan', 'pasien_id')) {
        $data['pasien_id'] = (int)($inv['pasien_id'] ?? 0);
    }
    if (column_exists($conn, 'keuangan', 'created_at')) {
        $data['created_at'] = date('Y-m-d H:i:s');
    }

    if (empty($data)) {
        return true;
    }

    $cols = [];
    $placeholders = [];
    $params = [];

    foreach ($data as $col => $val) {
        $cols[] = "`$col`";
        $placeholders[] = "?";
        $params[] = $val;
    }

    $sql = "INSERT INTO keuangan (" . implode(', ', $cols) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return true;
    }

    $types = '';
    foreach ($params as $p) {
        if (is_int($p)) {
            $types .= 'i';
        } elseif (is_float($p)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stmt->close();

    return true;
}
