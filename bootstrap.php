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

function ensure_keuangan_schema() {
    $conn = db();
    if (!$conn) return;

    if (!table_exists($conn, 'keuangan')) {
        $conn->query("
            CREATE TABLE keuangan (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tanggal DATETIME NOT NULL,
                jenis VARCHAR(30) NOT NULL,
                kategori VARCHAR(100) DEFAULT NULL,
                deskripsi TEXT DEFAULT NULL,
                nominal DECIMAL(15,2) NOT NULL DEFAULT 0,
                invoice_id INT DEFAULT NULL,
                pasien_id INT DEFAULT NULL,
                referensi_no VARCHAR(100) DEFAULT NULL,
                metode_bayar VARCHAR(50) DEFAULT NULL,
                status VARCHAR(50) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tanggal (tanggal),
                INDEX idx_jenis (jenis),
                INDEX idx_invoice_id (invoice_id),
                INDEX idx_pasien_id (pasien_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        return;
    }

    $cols = array(
        'kategori'     => "ALTER TABLE keuangan ADD COLUMN kategori VARCHAR(100) DEFAULT NULL AFTER jenis",
        'referensi_no' => "ALTER TABLE keuangan ADD COLUMN referensi_no VARCHAR(100) DEFAULT NULL AFTER pasien_id",
        'metode_bayar' => "ALTER TABLE keuangan ADD COLUMN metode_bayar VARCHAR(50) DEFAULT NULL AFTER referensi_no",
        'status'       => "ALTER TABLE keuangan ADD COLUMN status VARCHAR(50) DEFAULT NULL AFTER metode_bayar",
        'created_at'   => "ALTER TABLE keuangan ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP",
        'updated_at'   => "ALTER TABLE keuangan ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    );

    foreach ($cols as $col => $sql) {
        if (!column_exists($conn, 'keuangan', $col)) {
            @$conn->query($sql);
        }
    }
}

function sync_invoice_finance($invoiceId) {
    $conn = db();
    if (!$conn || !table_exists($conn, 'invoice')) return;

    ensure_keuangan_schema();

    $inv = db_fetch_one("SELECT * FROM invoice WHERE id = ?", array((int)$invoiceId));
    if (!$inv) return;

    db_run("DELETE FROM keuangan WHERE invoice_id = ?", array((int)$invoiceId));

    $status = strtolower(trim((string)($inv['status_bayar'] ?? '')));
    if ($status === 'lunas' || $status === 'paid') {
        db_insert(
            "INSERT INTO keuangan
            (tanggal, jenis, kategori, deskripsi, nominal, invoice_id, pasien_id, referensi_no, metode_bayar, status)
            VALUES (?, 'pemasukan', 'Pembayaran Invoice', ?, ?, ?, ?, ?, ?, ?)",
            array(
                !empty($inv['tanggal']) ? $inv['tanggal'] : date('Y-m-d H:i:s'),
                'Pembayaran invoice ' . ($inv['no_invoice'] ?? ''),
                (float)($inv['total'] ?? 0),
                (int)$invoiceId,
                (int)($inv['pasien_id'] ?? 0),
                (string)($inv['no_invoice'] ?? ''),
                (string)($inv['metode_bayar'] ?? ''),
                (string)($inv['status_bayar'] ?? '')
            )
        );
    }
}

function tambah_pengeluaran($tanggal, $kategori, $deskripsi, $nominal) {
    ensure_keuangan_schema();
    return db_insert(
        "INSERT INTO keuangan (tanggal, jenis, kategori, deskripsi, nominal)
         VALUES (?, 'pengeluaran', ?, ?, ?)",
        array($tanggal, $kategori, $deskripsi, (float)$nominal)
    );
}

function keuangan_ringkasan($bulan = null) {
    $conn = db();
    if (!$conn) return array('pemasukan' => 0, 'pengeluaran' => 0, 'saldo' => 0);

    ensure_keuangan_schema();

    $bulan = $bulan ?: date('Y-m');
    $mulai = $bulan . '-01 00:00:00';
    $akhir = date('Y-m-t 23:59:59', strtotime($mulai));

    $row = db_fetch_one("
        SELECT
            COALESCE(SUM(CASE WHEN jenis='pemasukan' THEN nominal ELSE 0 END),0) AS pemasukan,
            COALESCE(SUM(CASE WHEN jenis='pengeluaran' THEN nominal ELSE 0 END),0) AS pengeluaran
        FROM keuangan
        WHERE tanggal BETWEEN ? AND ?
    ", array($mulai, $akhir));

    $pemasukan = (float)($row['pemasukan'] ?? 0);
    $pengeluaran = (float)($row['pengeluaran'] ?? 0);

    return array(
        'pemasukan' => $pemasukan,
        'pengeluaran' => $pengeluaran,
        'saldo' => $pemasukan - $pengeluaran
    );
}
    }
}
