<?php
// Bootstrap untuk semua halaman cloud.
// Menggunakan config.php yang sudah ada di server/cloud.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

// Kompatibilitas beberapa nama variabel koneksi yang umum.
$db = null;
foreach (['conn', 'koneksi', 'mysqli', 'db', 'pdo'] as $varName) {
    if (isset($$varName)) {
        $db = $$varName;
        break;
    }
}

if (!$db) {
    die('Koneksi database dari config.php tidak ditemukan. Pastikan config.php menyediakan $conn / $koneksi / $mysqli / $db / $pdo.');
}

function is_pdo_connection($db) {
    return $db instanceof PDO;
}

function db_query($sql, $params = []) {
    global $db;

    if (is_pdo_connection($db)) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare gagal: ' . $db->error);
    }

    if (!empty($params)) {
        $types = '';
        $refs = [];
        foreach ($params as $p) {
            if (is_int($p)) $types .= 'i';
            elseif (is_float($p)) $types .= 'd';
            else $types .= 's';
            $refs[] = $p;
        }
        $stmt->bind_param($types, ...$refs);
    }

    if (!$stmt->execute()) {
        throw new Exception('Execute gagal: ' . $stmt->error);
    }
    return $stmt;
}

function db_fetch_all($sql, $params = []) {
    $stmt = db_query($sql, $params);
    if ($stmt instanceof PDOStatement) {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $res = $stmt->get_result();
    return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}

function db_fetch_one($sql, $params = []) {
    $rows = db_fetch_all($sql, $params);
    return $rows[0] ?? null;
}

function db_execute($sql, $params = []) {
    global $db;
    $stmt = db_query($sql, $params);
    if ($stmt instanceof PDOStatement) {
        return true;
    }
    return $stmt->affected_rows >= 0;
}

function db_last_id() {
    global $db;
    if (is_pdo_connection($db)) {
        return $db->lastInsertId();
    }
    return $db->insert_id;
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function redirect_with_message($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: ' . $url);
    exit;
}

function flash_message() {
    if (!empty($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        echo '<div class="alert alert-' . e($type) . '" style="padding:12px 14px;margin-bottom:16px;border-radius:12px;background:' . ($type === 'danger' ? '#fee2e2' : '#dcfce7') . ';color:#111827;">' . e($msg) . '</div>';
    }
}

function require_login() {
    if (empty($_SESSION['user_id']) && empty($_SESSION['login']) && empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}

function table_exists($table) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    if ($table === '') return false;
    try {
        $row = db_fetch_one("SHOW TABLES LIKE ?", [$table]);
        return !empty($row);
    } catch (Throwable $e) {
        return false;
    }
}

function column_exists($table, $column) {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($table === '' || $column === '') return false;
    try {
        $rows = db_fetch_all("SHOW COLUMNS FROM `$table` LIKE ?", [$column]);
        return !empty($rows);
    } catch (Throwable $e) {
        return false;
    }
}

function ensure_master_tables() {
    // Tidak mengubah alur kerja, hanya memastikan tabel inti tersedia jika belum ada.
    db_execute("CREATE TABLE IF NOT EXISTS pasien (
        id INT AUTO_INCREMENT PRIMARY KEY,
        no_rm VARCHAR(30) NOT NULL UNIQUE,
        nik VARCHAR(30) DEFAULT NULL,
        nama VARCHAR(150) NOT NULL,
        jk ENUM('L','P') DEFAULT 'L',
        tempat_lahir VARCHAR(100) DEFAULT NULL,
        tanggal_lahir DATE DEFAULT NULL,
        telepon VARCHAR(30) DEFAULT NULL,
        alamat TEXT DEFAULT NULL,
        alergi TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    db_execute("CREATE TABLE IF NOT EXISTS kunjungan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pasien_id INT NOT NULL,
        tanggal DATETIME NOT NULL,
        keluhan TEXT DEFAULT NULL,
        diagnosa VARCHAR(255) DEFAULT NULL,
        odontogram TEXT DEFAULT NULL,
        tindakan TEXT DEFAULT NULL,
        dokter VARCHAR(150) DEFAULT NULL,
        catatan TEXT DEFAULT NULL,
        status_kunjungan VARCHAR(30) DEFAULT 'selesai',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_kunjungan_pasien FOREIGN KEY (pasien_id) REFERENCES pasien(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    db_execute("CREATE TABLE IF NOT EXISTS invoice (
        id INT AUTO_INCREMENT PRIMARY KEY,
        no_invoice VARCHAR(40) NOT NULL UNIQUE,
        pasien_id INT NOT NULL,
        kunjungan_id INT DEFAULT NULL,
        tanggal DATETIME NOT NULL,
        subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
        diskon DECIMAL(15,2) NOT NULL DEFAULT 0,
        total DECIMAL(15,2) NOT NULL DEFAULT 0,
        status_bayar ENUM('lunas','pending','belum terbayar') DEFAULT 'belum terbayar',
        metode_bayar VARCHAR(50) DEFAULT 'tunai',
        catatan TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_invoice_pasien FOREIGN KEY (pasien_id) REFERENCES pasien(id) ON DELETE CASCADE,
        CONSTRAINT fk_invoice_kunjungan FOREIGN KEY (kunjungan_id) REFERENCES kunjungan(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    db_execute("CREATE TABLE IF NOT EXISTS invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        nama_item VARCHAR(255) NOT NULL,
        qty DECIMAL(10,2) NOT NULL DEFAULT 1,
        harga DECIMAL(15,2) NOT NULL DEFAULT 0,
        subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
        keterangan VARCHAR(255) DEFAULT NULL,
        CONSTRAINT fk_invoice_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoice(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

ensure_master_tables();
require_login();
