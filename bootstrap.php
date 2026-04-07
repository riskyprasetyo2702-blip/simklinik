<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

define('KLINIK_NAMA', 'Klinik Praktek Mandiri Dokter Gigi Andreas Aryo Risky Prasetyo');
define('KLINIK_ALAMAT', 'Alamat klinik');
define('KLINIK_TELP', 'Telepon klinik');
define('QRIS_IMAGE_URL', '');
define('QRIS_PAYLOAD', '');

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
        echo '<div style="background:#dcfce7;color:#166534;padding:12px 14px;border-radius:12px;margin-bottom:14px;">' . e($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }

    if (!empty($_SESSION['error'])) {
        echo '<div style="background:#fee2e2;color:#991b1b;padding:12px 14px;border-radius:12px;margin-bottom:14px;">' . e($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
}

function table_exists($conn, $table) {
    if (!$conn) return false;
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function column_exists($conn, $table, $column) {
    if (!$conn) return false;
    if (!table_exists($conn, $table)) return false;
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

function db_fetch_all($query, $params = array()) {
    $conn = db();
    if (!$conn) return array();

    $stmt = $conn->prepare($query);
    if (!$stmt) return array();

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

    $rows = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    $stmt->close();
    return $rows;
}

function db_fetch_one($query, $params = array()) {
    $rows = db_fetch_all($query, $params);
    return isset($rows[0]) ? $rows[0] : null;
}

function db_run($query, $params = array()) {
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

function db_insert($query, $params = array()) {
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

function rupiah($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function next_rm() {
    if (!table_exists(db(), 'pasien')) return 'RM000001';
    $row = db_fetch_one("SELECT no_rm FROM pasien ORDER BY id DESC LIMIT 1");
    $num = 1;
    if (!empty($row['no_rm']) && preg_match('/(\d+)$/', $row['no_rm'], $m)) {
        $num = ((int)$m[1]) + 1;
    }
    return 'RM' . str_pad((string)$num, 6, '0', STR_PAD_LEFT);
}

function next_invoice_no() {
    if (!table_exists(db(), 'invoice')) {
        return 'INV-' . date('Ymd') . '-0001';
    }

    $date = date('Ymd');
    $row = db_fetch_one("SELECT no_invoice FROM invoice WHERE no_invoice LIKE ? ORDER BY id DESC LIMIT 1", array("INV-$date-%"));
    $num = 1;
    if (!empty($row['no_invoice']) && preg_match('/-(\d+)$/', $row['no_invoice'], $m)) {
        $num = ((int)$m[1]) + 1;
    }
    return 'INV-' . $date . '-' . str_pad((string)$num, 4, '0', STR_PAD_LEFT);
}

function pasien_options() {
    $conn = db();
    if (!table_exists($conn, 'pasien')) return array();
    return db_fetch_all("SELECT id, no_rm, nama FROM pasien ORDER BY nama ASC");
}

function tindakan_options() {
    $conn = db();
    if (!table_exists($conn, 'tindakan')) return array();

    $sql = "SELECT * FROM tindakan";
    if (column_exists($conn, 'tindakan', 'aktif')) {
        $sql .= " WHERE aktif='yes'";
    }
    $sql .= " ORDER BY id ASC";

    return db_fetch_all($sql);
}

function icd10_options($keyword = '') {
    $conn = db();
    if (!table_exists($conn, 'icd10')) return array();

    if ($keyword !== '') {
        return db_fetch_all(
            "SELECT * FROM icd10 WHERE kode LIKE ? OR diagnosis LIKE ? ORDER BY kode ASC LIMIT 100",
            array("%$keyword%", "%$keyword%")
        );
    }

    return db_fetch_all("SELECT * FROM icd10 ORDER BY kode ASC LIMIT 100");
}

function sync_invoice_finance($invoiceId) {
    $conn = db();
    if (!table_exists($conn, 'invoice') || !table_exists($conn, 'keuangan')) return;

    $inv = db_fetch_one("SELECT * FROM invoice WHERE id = ?", array((int)$invoiceId));
    if (!$inv) return;

    db_run("DELETE FROM keuangan WHERE invoice_id = ?", array((int)$invoiceId));

    $status = strtolower((string)($inv['status_bayar'] ?? ''));
    if ($status === 'lunas' || $status === 'paid') {
        db_insert(
            "INSERT INTO keuangan (tanggal, jenis, deskripsi, nominal, invoice_id, pasien_id) VALUES (NOW(), 'pemasukan', ?, ?, ?, ?)",
            array(
                'Pembayaran invoice ' . ($inv['no_invoice'] ?? ''),
                (float)($inv['total'] ?? 0),
                (int)$invoiceId,
                (int)($inv['pasien_id'] ?? 0)
            )
        );
    }
}
