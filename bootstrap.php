<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';

const KLINIK_NAMA   = 'Klinik Praktek Mandiri Dokter Gigi Andreas Aryo Risky Prasetyo';
const KLINIK_ALAMAT = 'Alamat klinik dapat diatur di bootstrap.php';
const KLINIK_TELP   = 'Telepon klinik dapat diatur di bootstrap.php';
const QRIS_IMAGE_URL = '';
const QRIS_PAYLOAD   = '';

/* =========================================================
 * KONEKSI
 * ========================================================= */
function db() {
    global $conn, $koneksi, $mysqli, $db;

    if (isset($conn) && $conn instanceof mysqli) return $conn;
    if (isset($koneksi) && $koneksi instanceof mysqli) return $koneksi;
    if (isset($mysqli) && $mysqli instanceof mysqli) return $mysqli;
    if (isset($db) && $db instanceof mysqli) return $db;

    return null;
}

/* =========================================================
 * SESSION / AUTH
 * ========================================================= */
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

/* =========================================================
 * HELPER
 * ========================================================= */
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

function rupiah($n) {
    return 'Rp ' . number_format((float)$n, 0, ',', '.');
}

function post_val($key, $default = '') {
    return trim($_POST[$key] ?? $default);
}

/* =========================================================
 * DB UTILITY
 * ========================================================= */
function table_exists(mysqli $conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    return $res && $res->num_rows > 0;
}

function column_exists(mysqli $conn, $table, $column) {
    if (!table_exists($conn, $table)) return false;
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    return $res && $res->num_rows > 0;
}

function ensure_auto_increment_id($table) {
    $conn = db();
    if (!$conn) return;
    if (!table_exists($conn, $table)) return;

    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW COLUMNS FROM `$tableEsc` LIKE 'id'");
    if (!$res || $res->num_rows === 0) return;

    $col = $res->fetch_assoc();
    $type = strtolower($col['Type'] ?? '');
    $extra = strtolower($col['Extra'] ?? '');

    if (strpos($type, 'int') !== false && strpos($extra, 'auto_increment') === false) {
        @ $conn->query("ALTER TABLE `$tableEsc` MODIFY COLUMN `id` INT NOT NULL AUTO_INCREMENT");
    }
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

/* =========================================================
 * MASTER DATA
 * ========================================================= */
function tindakan_seed_data() {
    return [
        ['KONS-001','Konsultasi','Konservasi',100000,null,null,'per tindakan',''],
        ['KONS-002','Tambal Sementara','Konservasi',150000,null,null,'per tindakan',''],
        ['KONS-003','Tambal Komposit','Konservasi',200000,200000,400000,'range',''],
        ['KONS-004','Tambal Estetik/Anterior','Konservasi',250000,250000,500000,'range',''],
        ['KONS-005','Tambal GIC/Fuji','Konservasi',200000,200000,300000,'range',''],
        ['KONS-006','Pengisian Saluran Akar','Konservasi',300000,null,null,'per tindakan',''],
        ['KONS-007','Fissure Sealant','Konservasi',200000,null,null,'per tindakan',''],
        ['KONS-008','Perawatan Syaraf Gigi Tunggal / Endo','Konservasi',250000,null,null,'per visit',''],
        ['KONS-009','Perawatan Syaraf Gigi Ganda / Endo','Konservasi',300000,null,null,'per visit',''],
        ['KONS-010','Sementasi Crown','Konservasi',200000,null,null,'per tindakan',''],
        ['KONS-011','Onlay/Inlay metal','Konservasi',1600000,null,null,'per tindakan',''],
        ['KONS-012','Onlay All porcelain','Konservasi',2500000,null,null,'per tindakan',''],
        ['KONS-013','Bleaching','Konservasi',5000000,null,null,'per tindakan',''],
        ['KONS-014','Open Bur','Konservasi',150000,null,null,'per tindakan',''],

        ['PROS-001','Cetak Rubber base','Prostho',250000,null,null,'per tindakan',''],
        ['PROS-002','Cetak alginat','Prostho',150000,null,null,'per tindakan',''],
        ['PROS-003','Gigi Tiruan Akrilik','Prostho',1200000,null,null,'per tindakan',''],
        ['PROS-004','Gigi Tiruan Kerangka Logam','Prostho',1400000,null,null,'per tindakan',''],
        ['PROS-005','Tambahan Gigi','Prostho',200000,null,null,'per gigi',''],
        ['PROS-006','Gigi Tiruan Valplast','Prostho',1500000,null,null,'per tindakan',''],
        ['PROS-007','fiber post','Prostho',500000,null,null,'per tindakan',''],
        ['PROS-008','Gigi Tiruan Lengkap','Prostho',7000000,null,null,'per tindakan',''],
        ['PROS-009','Veneer crown metal','Prostho',1600000,null,null,'per tindakan',''],
        ['PROS-010','Veneer crown all porcelain','Prostho',3000000,null,null,'per tindakan',''],

        ['BED-001','Cabut gigi','Bedah Mulut',250000,null,null,'per tindakan',''],
        ['BED-002','Cabut gigi Komplikasi','Bedah Mulut',500000,null,null,'per tindakan',''],
        ['BED-003','Odontektomi kelas 1','Bedah Mulut',2000000,null,null,'per tindakan',''],
        ['BED-004','Odontektomi kelas 2 dan 3','Bedah Mulut',2500000,null,null,'per tindakan',''],
        ['BED-005','Implant','Bedah Mulut',15000000,null,null,'per tindakan',''],

        ['PER-001','Scalling','Perio',350000,null,null,'per tindakan',''],
        ['PER-002','Occlusal adjustment','Perio',150000,null,null,'per tindakan',''],
        ['PER-003','Kuretase/gigi','Perio',200000,null,null,'per gigi',''],
        ['PER-004','Splint wire with composite','Perio',350000,null,null,'per gigi',''],
        ['PER-005','flap operation','Perio',1500000,null,null,'per tindakan',''],

        ['ORT-001','Fixed Ortho (metal)','Ortho',5000000,null,null,'per tindakan',''],
        ['ORT-002','Fixed Ortho (ceramik/saphire)','Ortho',10000000,null,null,'per tindakan',''],
        ['ORT-003','Fixed ortho Damon Clear','Ortho',20000000,null,null,'per tindakan',''],
        ['ORT-004','Fixed ortho Damon Metal','Ortho',15000000,null,null,'per tindakan',''],
        ['ORT-005','Invisilign','Ortho',0,null,null,'manual','Harga manual'],
        ['ORT-006','Lepas Ortho + Polishing','Ortho',500000,null,null,'per tindakan',''],
        ['ORT-007','Retainer Ortho','Ortho',1500000,null,null,'per tindakan',''],
        ['ORT-008','Kontrol Ortho','Ortho',200000,null,null,'per tindakan',''],
        ['ORT-009','Buccal tube per gigi','Ortho',100000,null,null,'per gigi',''],
        ['ORT-010','Bracket per satuan','Ortho',100000,null,null,'per tindakan',''],
        ['ORT-011','Lem rebond','Ortho',100000,null,null,'per tindakan',''],

        ['PED-001','Ekstraksi tanpa injeksi','Pedo/Anak',200000,null,null,'per tindakan',''],
        ['PED-002','Ekstraksi dengan injeksi','Pedo/Anak',250000,null,null,'per tindakan',''],
        ['PED-003','Tambelan sementara','Pedo/Anak',150000,null,null,'per tindakan',''],
        ['PED-004','Fletcher eugenol','Pedo/Anak',200000,null,null,'per tindakan',''],
        ['PED-005','PSA','Pedo/Anak',200000,null,null,'per tindakan',''],
        ['PED-006','Pulpektomi formokresol','Pedo/Anak',250000,null,null,'per tindakan',''],
        ['PED-007','Pengisian saluran akar','Pedo/Anak',250000,null,null,'per tindakan',''],
        ['PED-008','Ortho lepasan anak','Pedo/Anak',1500000,null,null,'per tindakan',''],
        ['PED-009','Ortho cekat anak','Pedo/Anak',6000000,null,null,'per tindakan',''],
        ['PED-010','Space maintener','Pedo/Anak',1000000,null,null,'per tindakan',''],
    ];
}

function icd10_seed_data() {
    return [
        ['Z01.2','Dental examination'],
        ['K02.1','Caries of dentine'],
        ['K02.9','Dental caries, unspecified'],
        ['K03.6','Deposits on teeth'],
        ['K04.0','Pulpitis'],
        ['K04.1','Necrosis of pulp'],
        ['K04.7','Periapical abscess without sinus'],
        ['K05.1','Chronic gingivitis'],
        ['K05.3','Chronic periodontitis'],
        ['K05.6','Periodontal disease, unspecified'],
        ['K06.0','Gingival recession'],
        ['K08.1','Loss of teeth due to accident, extraction or local periodontal disease'],
        ['K08.8','Other specified disorders of teeth and supporting structures'],
        ['K08.9','Disorder of teeth and supporting structures, unspecified'],
        ['K12.0','Recurrent oral aphthae'],
    ];
}

/* =========================================================
 * SCHEMA
 * ========================================================= */
function ensure_core_schema() {
    $conn = db();
    if (!$conn) return;

    $conn->query("CREATE TABLE IF NOT EXISTS pasien (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      no_rm VARCHAR(50) NOT NULL UNIQUE,
      nama VARCHAR(150) NOT NULL,
      nik VARCHAR(50) NULL,
      jk VARCHAR(10) NULL,
      tempat_lahir VARCHAR(100) NULL,
      tanggal_lahir DATE NULL,
      telepon VARCHAR(50) NULL,
      alamat TEXT NULL,
      alergi TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS tindakan (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      kode VARCHAR(30) NULL,
      nama_tindakan VARCHAR(255) NOT NULL,
      kategori VARCHAR(100) NULL,
      harga DECIMAL(12,2) DEFAULT 0,
      harga_min DECIMAL(12,2) DEFAULT NULL,
      harga_max DECIMAL(12,2) DEFAULT NULL,
      satuan_harga VARCHAR(50) DEFAULT 'per tindakan',
      keterangan VARCHAR(255) DEFAULT NULL,
      aktif ENUM('yes','no') DEFAULT 'yes',
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS icd10 (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      kode VARCHAR(20) NOT NULL UNIQUE,
      diagnosis VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS kunjungan (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      pasien_id INT NOT NULL,
      tanggal DATETIME NOT NULL,
      keluhan TEXT NULL,
      diagnosa VARCHAR(255) NULL,
      icd10_code VARCHAR(20) NULL,
      dokter VARCHAR(150) NULL,
      tindakan TEXT NULL,
      catatan TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_pasien (pasien_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS odontogram_tindakan (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      pasien_id INT NOT NULL,
      kunjungan_id INT NOT NULL,
      nomor_gigi VARCHAR(10) NOT NULL,
      surface_code VARCHAR(10) DEFAULT NULL,
      tindakan_id INT DEFAULT NULL,
      nama_tindakan VARCHAR(255) NOT NULL,
      kategori VARCHAR(100) DEFAULT NULL,
      harga DECIMAL(12,2) DEFAULT 0,
      qty DECIMAL(12,2) DEFAULT 1,
      subtotal DECIMAL(12,2) DEFAULT 0,
      satuan_harga VARCHAR(50) DEFAULT 'per tindakan',
      catatan TEXT DEFAULT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_pasien (pasien_id),
      INDEX idx_kunjungan (kunjungan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS invoice (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      pasien_id INT NOT NULL,
      kunjungan_id INT DEFAULT NULL,
      no_invoice VARCHAR(50) NOT NULL UNIQUE,
      tanggal DATETIME NOT NULL,
      subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
      diskon DECIMAL(12,2) NOT NULL DEFAULT 0,
      total DECIMAL(12,2) NOT NULL DEFAULT 0,
      status_bayar VARCHAR(50) NOT NULL DEFAULT 'pending',
      metode_bayar VARCHAR(50) DEFAULT 'tunai',
      catatan TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_pasien (pasien_id),
      INDEX idx_kunjungan (kunjungan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS invoice_items (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      invoice_id INT NOT NULL,
      tindakan_id INT DEFAULT NULL,
      nama_item VARCHAR(255) NOT NULL,
      qty DECIMAL(12,2) NOT NULL DEFAULT 1,
      harga DECIMAL(12,2) NOT NULL DEFAULT 0,
      subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
      nomor_gigi VARCHAR(10) DEFAULT NULL,
      keterangan TEXT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_invoice (invoice_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS resume_medis (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      pasien_id INT NOT NULL,
      kunjungan_id INT NOT NULL,
      keluhan_utama TEXT NULL,
      pemeriksaan TEXT NULL,
      diagnosa VARCHAR(255) NULL,
      icd10_code VARCHAR(20) NULL,
      tindakan TEXT NULL,
      terapi TEXT NULL,
      instruksi TEXT NULL,
      catatan TEXT NULL,
      dokter_nama VARCHAR(150) NOT NULL,
      dokter_sip VARCHAR(100) NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_pasien (pasien_id),
      INDEX idx_kunjungan (kunjungan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS surat_sakit (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      pasien_id INT NOT NULL,
      kunjungan_id INT NOT NULL,
      nomor_surat VARCHAR(100) NOT NULL UNIQUE,
      tanggal_surat DATE NOT NULL,
      tanggal_mulai DATE NOT NULL,
      tanggal_selesai DATE NOT NULL,
      lama_istirahat INT NOT NULL DEFAULT 1,
      diagnosis_singkat VARCHAR(255) NULL,
      keterangan TEXT NULL,
      dokter_nama VARCHAR(150) NOT NULL,
      dokter_sip VARCHAR(100) NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_pasien (pasien_id),
      INDEX idx_kunjungan (kunjungan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS keuangan (
      id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      jenis VARCHAR(50) NOT NULL,
      deskripsi VARCHAR(255) NOT NULL,
      nominal DECIMAL(12,2) NOT NULL DEFAULT 0,
      invoice_id INT NULL,
      pasien_id INT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_invoice (invoice_id),
      INDEX idx_pasien (pasien_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    /* upgrade ringan tabel lama */
    if (table_exists($conn, 'tindakan')) {
        if (!column_exists($conn, 'tindakan', 'kode')) {
            @ $conn->query("ALTER TABLE tindakan ADD COLUMN kode VARCHAR(30) NULL");
        }
        if (!column_exists($conn, 'tindakan', 'nama_tindakan')) {
            @ $conn->query("ALTER TABLE tindakan ADD COLUMN nama_tindakan VARCHAR(255) NULL");
            if (column_exists($conn, 'tindakan', 'nama')) {
                @ $conn->query("UPDATE tindakan SET nama_tindakan = nama WHERE nama_tindakan IS NULL OR nama_tindakan=''");
            }
        }
        if (!column_exists($conn, 'tindakan', 'kategori')) {
            @ $conn->query("ALTER TABLE tindakan ADD COLUMN kategori VARCHAR(100) NULL");
        }
        if (!column_exists($conn, 'tindakan', 'harga')) {
            @ $conn->query("ALTER TABLE tindakan ADD COLUMN harga DECIMAL(12,2) DEFAULT 0");
        }
        if (!column_exists($conn, 'tindakan', 'harga_min')) {
            @ $conn->query("ALTER TABLE tindakan ADD COLUMN harga_min DECIMAL(12,2) DEFAULT NULL");
        }
        if (!column_exists($conn, 'tindakan', 'harga_max')) {
            @ $conn->query("ALTER TABLE tindakan ADD COLUMN harga_max DECIMAL(12,2) DEFAULT NULL");
        }
        if (!column_exists($conn, 'tindakan', 'satuan_harga')) {
            @ $conn->query("ALTER TABLE tindakan ADD COLUMN satuan_harga VARCHAR(50) DEFAULT 'per tindakan'");
        }
        if (!column_exists($conn, 'tindakan', 'keterangan')) {
            @ $conn->query("ALTER TABLE tindakan ADD COLUMN keterangan VARCHAR(255) DEFAULT NULL");
        }
        if (!column_exists($conn, 'tindakan', 'aktif')) {
            @ $conn->query("ALTER TABLE tindakan ADD COLUMN aktif ENUM('yes','no') DEFAULT 'yes'");
            @ $conn->query("UPDATE tindakan SET aktif='yes' WHERE aktif IS NULL");
        }
        if (column_exists($conn, 'tindakan', 'kode')) {
            @ $conn->query("UPDATE tindakan SET kode = CONCAT('TDK-', id) WHERE kode IS NULL OR kode=''");
        }
    }

    ensure_auto_increment_id('pasien');
    ensure_auto_increment_id('tindakan');
    ensure_auto_increment_id('icd10');
    ensure_auto_increment_id('kunjungan');
    ensure_auto_increment_id('odontogram_tindakan');
    ensure_auto_increment_id('invoice');
    ensure_auto_increment_id('invoice_items');
    ensure_auto_increment_id('resume_medis');
    ensure_auto_increment_id('surat_sakit');
    ensure_auto_increment_id('keuangan');

    seed_tindakan();
    seed_icd10();
}

/* =========================================================
 * SEED
 * ========================================================= */
function seed_tindakan() {
    $conn = db();
    if (!$conn || !table_exists($conn, 'tindakan')) return;

    $row = db_fetch_one("SELECT COUNT(*) AS jml FROM tindakan");
    if (($row['jml'] ?? 0) > 0) return;

    $sql = "INSERT INTO tindakan (kode, nama_tindakan, kategori, harga, harga_min, harga_max, satuan_harga, keterangan, aktif)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'yes')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return;

    foreach (tindakan_seed_data() as $it) {
        [$kode,$nama,$kategori,$harga,$min,$max,$satuan,$ket] = $it;
        $stmt->bind_param('sssdddss', $kode, $nama, $kategori, $harga, $min, $max, $satuan, $ket);
        $stmt->execute();
    }

    $stmt->close();
}

function seed_icd10() {
    $conn = db();
    if (!$conn || !table_exists($conn, 'icd10')) return;

    $row = db_fetch_one("SELECT COUNT(*) AS jml FROM icd10");
    if (($row['jml'] ?? 0) > 0) return;

    $stmt = $conn->prepare("INSERT INTO icd10 (kode, diagnosis) VALUES (?, ?)");
    if (!$stmt) return;

    foreach (icd10_seed_data() as $it) {
        [$kode, $diagnosis] = $it;
        $stmt->bind_param('ss', $kode, $diagnosis);
        $stmt->execute();
    }

    $stmt->close();
}

/* =========================================================
 * OPTIONS
 * ========================================================= */
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
    $sql .= " ORDER BY kategori ASC, nama_tindakan ASC, id ASC";

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

/* =========================================================
 * NOMOR OTOMATIS
 * ========================================================= */
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

function next_nomor_surat() {
    return 'SK-' . date('Ymd-His');
}

/* =========================================================
 * KEUANGAN
 * ========================================================= */
function sync_invoice_finance($invoiceId) {
    $conn = db();
    if (!$conn) return;
    if (!table_exists($conn, 'invoice') || !table_exists($conn, 'keuangan')) return;

    $inv = db_fetch_one("SELECT * FROM invoice WHERE id = ?", [(int)$invoiceId]);
    if (!$inv) return;

    db_run("DELETE FROM keuangan WHERE invoice_id = ?", [(int)$invoiceId]);

    $status = strtolower((string)($inv['status_bayar'] ?? ''));
    if (in_array($status, ['lunas', 'paid'])) {
        db_insert(
            "INSERT INTO keuangan (tanggal, jenis, deskripsi, nominal, invoice_id, pasien_id)
             VALUES (NOW(), 'pemasukan', ?, ?, ?, ?)",
            [
                'Pembayaran invoice ' . ($inv['no_invoice'] ?? ''),
                (float)($inv['total'] ?? 0),
                (int)$invoiceId,
                (int)($inv['pasien_id'] ?? 0)
            ]
        );
    }
}

ensure_core_schema();
