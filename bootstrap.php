<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

function db() {
    global $conn, $koneksi, $mysqli, $db, $pdo;
    if (isset($conn) && $conn instanceof mysqli) return $conn;
    if (isset($koneksi) && $koneksi instanceof mysqli) return $koneksi;
    if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
    if (isset($db) && $db instanceof mysqli) return $db;
    return null;
}

function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

function ensure_logged_in() {
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
        header('Location: login.php');
        exit;
    }
}

function current_user_name() {
    return $_SESSION['username'] ?? 'Administrator';
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

function ensure_odontogram_tables(mysqli $conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS odontogram (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pasien_id INT NOT NULL,
        kunjungan_id INT DEFAULT NULL,
        tanggal DATE NOT NULL,
        keluhan_utama TEXT NULL,
        diagnosa_icd10 VARCHAR(20) NULL,
        nama_diagnosa VARCHAR(255) NULL,
        catatan TEXT NULL,
        total_tagihan DECIMAL(12,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_pasien (pasien_id),
        INDEX idx_kunjungan (kunjungan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS odontogram_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        odontogram_id INT NOT NULL,
        nomor_gigi VARCHAR(10) NOT NULL,
        kondisi VARCHAR(100) NULL,
        tindakan VARCHAR(100) NULL,
        tarif DECIMAL(12,2) DEFAULT 0,
        FOREIGN KEY (odontogram_id) REFERENCES odontogram(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function get_pasien_options(mysqli $conn) {
    $out = [];
    if (!table_exists($conn, 'pasien')) return $out;

    $sql = "SELECT * FROM pasien ORDER BY id DESC LIMIT 500";
    $res = $conn->query($sql);
    if (!$res) return $out;

    while ($row = $res->fetch_assoc()) {
        $id = $row['id'] ?? null;
        if (!$id) continue;
        $nama = $row['nama'] ?? $row['nama_pasien'] ?? $row['nama_lengkap'] ?? ('Pasien #' . $id);
        $no_rm = $row['no_rm'] ?? $row['no_rekam_medis'] ?? $row['rekam_medis'] ?? '';
        $out[] = [
            'id' => $id,
            'nama' => $nama,
            'no_rm' => $no_rm,
        ];
    }
    return $out;
}

function get_kunjungan_options(mysqli $conn) {
    $out = [];
    if (!table_exists($conn, 'kunjungan')) return $out;

    $sql = "SELECT * FROM kunjungan ORDER BY id DESC LIMIT 500";
    $res = $conn->query($sql);
    if (!$res) return $out;

    while ($row = $res->fetch_assoc()) {
        $id = $row['id'] ?? null;
        if (!$id) continue;
        $pasien_id = $row['pasien_id'] ?? 0;
        $tanggal = $row['tanggal'] ?? $row['tgl_kunjungan'] ?? $row['created_at'] ?? '';
        $keluhan = $row['keluhan'] ?? $row['keluhan_utama'] ?? '';
        $out[] = [
            'id' => $id,
            'pasien_id' => $pasien_id,
            'label' => 'Kunjungan #' . $id . ' - ' . $tanggal . ($keluhan ? ' - ' . $keluhan : '')
        ];
    }
    return $out;
}

function get_icd10_list() {
    return [
        ['code' => 'K02.1', 'name' => 'Caries of dentine'],
        ['code' => 'K02.9', 'name' => 'Dental caries, unspecified'],
        ['code' => 'K03.6', 'name' => 'Deposits on teeth'],
        ['code' => 'K04.0', 'name' => 'Pulpitis'],
        ['code' => 'K04.1', 'name' => 'Necrosis of pulp'],
        ['code' => 'K04.7', 'name' => 'Periapical abscess without sinus'],
        ['code' => 'K05.1', 'name' => 'Chronic gingivitis'],
        ['code' => 'K05.3', 'name' => 'Chronic periodontitis'],
        ['code' => 'K05.6', 'name' => 'Periodontal disease, unspecified'],
        ['code' => 'K06.0', 'name' => 'Gingival recession'],
        ['code' => 'K08.1', 'name' => 'Loss of teeth due to accident, extraction or local periodontal disease'],
        ['code' => 'K08.8', 'name' => 'Other specified disorders of teeth and supporting structures'],
        ['code' => 'K08.9', 'name' => 'Disorder of teeth and supporting structures, unspecified'],
        ['code' => 'K12.0', 'name' => 'Recurrent oral aphthae'],
        ['code' => 'Z01.2', 'name' => 'Dental examination'],
    ];
}
// ================= QUERY FUNCTION =================

function db_fetch_all($query, $params = [])
{
    $conn = db();
    if (!$conn) die("Koneksi database tidak ditemukan");

    $stmt = $conn->prepare($query);
    if (!$stmt) die($conn->error);

    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }

    return $data;
}

function db_fetch_one($query, $params = [])
{
    $data = db_fetch_all($query, $params);
    return $data[0] ?? null;
}

// ================= FLASH MESSAGE =================

function flash_message()
{
    if (!empty($_SESSION['success'])) {
        echo "<div style='background:#d1fae5;padding:10px;border-radius:10px;margin-bottom:10px'>" . $_SESSION['success'] . "</div>";
        unset($_SESSION['success']);
    }

    if (!empty($_SESSION['error'])) {
        echo "<div style='background:#fee2e2;padding:10px;border-radius:10px;margin-bottom:10px'>" . $_SESSION['error'] . "</div>";
        unset($_SESSION['error']);
    }
}
