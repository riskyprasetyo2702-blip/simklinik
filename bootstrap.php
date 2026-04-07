<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ===== KONEKSI DATABASE =====
 * Pastikan file config.php benar
 */
require_once __DIR__ . '/config.php';

/**
 * ===== KONSTANTA KLINIK =====
 */
define('KLINIK_NAMA', 'AR Dental Clinic');
define('KLINIK_ALAMAT', 'Alamat Klinik');
define('KLINIK_TELP', '08111181718');
define('QRIS_IMAGE_URL', '');
define('QRIS_PAYLOAD', '');

/**
 * ===== AMBIL KONEKSI =====
 */
function db() {
    global $conn, $koneksi, $mysqli, $db;

    if (isset($conn) && $conn instanceof mysqli) return $conn;
    if (isset($koneksi) && $koneksi instanceof mysqli) return $koneksi;
    if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
    if (isset($db) && $db instanceof mysqli) return $db;

    die('❌ Koneksi database tidak ditemukan di config.php');
}

/**
 * ===== LOGIN CHECK =====
 */
function ensure_logged_in() {
    if (!isset($_SESSION['user']) && !isset($_SESSION['username'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * ===== HELPER =====
 */
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function rupiah($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

/**
 * ===== DB QUERY =====
 */
function db_fetch_all($query, $params = []) {
    $conn = db();

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die('Query error: ' . $conn->error);
    }

    if (!empty($params)) {
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $res = $stmt->get_result();

    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }

    return $rows;
}

function db_fetch_one($query, $params = []) {
    $rows = db_fetch_all($query, $params);
    return $rows[0] ?? null;
}

function db_run($query, $params = []) {
    $conn = db();

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die('Query error: ' . $conn->error);
    }

    if (!empty($params)) {
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$params);
    }

    return $stmt->execute();
}

function db_insert($query, $params = []) {
    $conn = db();

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die('Insert error: ' . $conn->error);
    }

    if (!empty($params)) {
        $types = '';
        foreach ($params as $p) {
            $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
        }
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    return $conn->insert_id;
}

/**
 * ===== FLASH MESSAGE =====
 */
function flash_message() {
    if (!empty($_SESSION['success'])) {
        echo '<div style="background:#dcfce7;color:#166534;padding:12px;border-radius:10px;margin-bottom:10px">'
            . e($_SESSION['success']) . '</div>';
        unset($_SESSION['success']);
    }

    if (!empty($_SESSION['error'])) {
        echo '<div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:10px">'
            . e($_SESSION['error']) . '</div>';
        unset($_SESSION['error']);
    }
}

/**
 * ===== INVOICE NUMBER =====
 */
function next_invoice_no() {
    $row = db_fetch_one("SELECT no_invoice FROM invoice ORDER BY id DESC LIMIT 1");

    $num = 1;
    if (!empty($row['no_invoice']) && preg_match('/(\d+)$/', $row['no_invoice'], $m)) {
        $num = (int)$m[1] + 1;
    }

    return 'INV-' . date('Ymd') . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

/**
 * ===== KEUANGAN SYNC =====
 */
function sync_invoice_finance($invoiceId) {
    $inv = db_fetch_one("SELECT * FROM invoice WHERE id=?", [$invoiceId]);
    if (!$inv) return;

    db_run("DELETE FROM keuangan WHERE invoice_id=?", [$invoiceId]);

    if (strtolower($inv['status_bayar']) === 'lunas') {
        db_insert(
            "INSERT INTO keuangan (tanggal, jenis, deskripsi, nominal, invoice_id, pasien_id)
             VALUES (?, 'pemasukan', ?, ?, ?, ?)",
            [
                $inv['tanggal'],
                'Pembayaran ' . $inv['no_invoice'],
                $inv['total'],
                $invoiceId,
                $inv['pasien_id']
            ]
        );
    }
}
