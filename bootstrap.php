<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

const KLINIK_NAMA = 'Klinik Praktek Mandiri Dokter Gigi Andreas Aryo Risky Prasetyo';
const KLINIK_ALAMAT = 'Alamat klinik';
const KLINIK_TELP = 'Telepon klinik';
const QRIS_IMAGE_URL = '';
const QRIS_PAYLOAD = '';

function db() {
    global $conn, $koneksi, $mysqli, $db;
    if (isset($conn) && $conn instanceof mysqli) return $conn;
    if (isset($koneksi) && $koneksi instanceof mysqli) return $koneksi;
    if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
    if (isset($db) && $db instanceof mysqli) return $db;
    return null;
}

function ensure_logged_in() {
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

function current_user_name() {
    if (!empty($_SESSION['username'])) return $_SESSION['username'];
    if (!empty($_SESSION['nama'])) return $_SESSION['nama'];
    if (!empty($_SESSION['user']) && is_string($_SESSION['user'])) return $_SESSION['user'];
    return 'Administrator';
}

function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function flash_message() {
    if (!empty($_SESSION['success'])) {
        echo '<div style="background:#dcfce7;color:#166534;padding:12px 14px;border-radius:12px;margin-bottom:14px;border:1px solid #86efac;">' . e($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }
    if (!empty($_SESSION['error'])) {
        echo '<div style="background:#fee2e2;color:#991b1b;padding:12px 14px;border-radius:12px;margin-bottom:14px;border:1px solid #fca5a5;">' . e($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
}

function table_exists(mysqli $conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

function db_fetch_all($query, $params = []) {
    $conn = db();
    if (!$conn) return [];

    $stmt = $conn->prepare($query);
    if (!$stmt) return [];

    if (!empty($params)) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            else $types .= 's';
        }
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $stmt->close();
    return $rows;
}

function db_fetch_one($query, $params = []) {
    $rows = db_fetch_all($query, $params);
    return $rows[0] ?? null;
}

function db_insert($query, $params = []) {
    $conn = db();
    if (!$conn) return false;

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

    $ok = $stmt->execute();
    $id = $ok ? $conn->insert_id : false;
    $stmt->close();
    return $id;
}

function db_run($query, $params = []) {
    $conn = db();
    if (!$conn) return false;

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

    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function rupiah($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function next_rm() {
    $row = db_fetch_one("SELECT no_rm FROM pasien ORDER BY id DESC LIMIT 1");
    $num = 1;
    if (!empty($row['no_rm']) && preg_match('/(\d+)$/', $row['no_rm'], $m)) {
        $num = ((int)$m[1]) + 1;
    }
    return 'RM' . str_pad((string)$num, 6, '0', STR_PAD_LEFT);
}

function next_invoice_no() {
    $date = date('Ymd');
    $row = db_fetch_one("SELECT no_invoice FROM invoice WHERE no_invoice LIKE ? ORDER BY id DESC LIMIT 1", ["INV-$date-%"]);
    $num = 1;
    if (!empty($row['no_invoice']) && preg_match('/-(\d+)$/', $row['no_invoice'], $m)) {
        $num = ((int)$m[1]) + 1;
    }
    return 'INV-' . $date . '-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
}

function pasien_options() {
    $conn = db();
    if (!$conn || !table_exists($conn, 'pasien')) return [];
    return db_fetch_all("SELECT id, no_rm, nama FROM pasien ORDER BY nama ASC");
}

function tindakan_options() {
    $conn = db();
    if (!$conn || !table_exists($conn, 'tindakan')) return [];

    $sql = "SELECT * FROM tindakan";
    if (column_exists($conn, 'tindakan', 'aktif')) {
        $sql .= " WHERE aktif='yes'";
    }
    $sql .= " ORDER BY id ASC";

    return db_fetch_all($sql);
}

function icd10_options($keyword = '') {
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

function sync_invoice_finance($invoiceId) {
    $conn = db();
    if (!$conn || !table_exists($conn, 'invoice') || !table_exists($conn, 'keuangan')) return;

    $inv = db_fetch_one("SELECT * FROM invoice WHERE id=?", [$invoiceId]);
    if (!$inv) return;

    db_run("DELETE FROM keuangan WHERE invoice_id=?", [$invoiceId]);

    if (in_array(strtolower($inv['status_bayar'] ?? ''), ['lunas', 'paid'])) {
        db_insert(
            "INSERT INTO keuangan (tanggal, jenis, deskripsi, nominal, invoice_id, pasien_id) VALUES (NOW(), 'pemasukan', ?, ?, ?, ?)",
            [
                'Pembayaran invoice ' . ($inv['no_invoice'] ?? ''),
                (float)($inv['total'] ?? 0),
                (int)$invoiceId,
                (int)($inv['pasien_id'] ?? 0)
            ]
        );
    }
}
?>
