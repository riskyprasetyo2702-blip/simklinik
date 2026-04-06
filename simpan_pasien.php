<?php
require_once __DIR__ . '/bootstrap.php';

ensure_logged_in();

$conn = db();
if (!$conn) {
    $_SESSION['error'] = 'Koneksi database tidak ditemukan.';
    header('Location: pasien.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pasien.php');
    exit;
}

function post($key, $default = '')
{
    return trim($_POST[$key] ?? $default);
}

$id            = (int)($_POST['id'] ?? 0);
$no_rm         = post('no_rm');
$nik           = post('nik');
$nama          = post('nama');
$jk            = post('jk');
$tempat_lahir  = post('tempat_lahir');
$tanggal_lahir = post('tanggal_lahir');
$telepon       = post('telepon');
$alamat        = post('alamat');
$alergi        = post('alergi');

if ($no_rm === '' || $nama === '') {
    $_SESSION['error'] = 'No. RM dan Nama Pasien wajib diisi.';
    header('Location: pasien.php' . ($id > 0 ? '?edit=' . $id : ''));
    exit;
}

if (!table_exists($conn, 'pasien')) {
    $_SESSION['error'] = 'Tabel pasien tidak ditemukan di database cloud.';
    header('Location: pasien.php');
    exit;
}

// Cek duplikasi No RM
if ($id > 0) {
    $cek = db_fetch_one("SELECT id FROM pasien WHERE no_rm = ? AND id != ?", [$no_rm, $id]);
} else {
    $cek = db_fetch_one("SELECT id FROM pasien WHERE no_rm = ?", [$no_rm]);
}

if ($cek) {
    $_SESSION['error'] = 'No. RM sudah digunakan. Silakan pakai No. RM lain.';
    header('Location: pasien.php' . ($id > 0 ? '?edit=' . $id : ''));
    exit;
}

// Susun kolom yang benar-benar ada di tabel
$allowedColumns = [
    'no_rm' => $no_rm,
    'nik' => $nik,
    'nama' => $nama,
    'jk' => $jk,
    'tempat_lahir' => $tempat_lahir,
    'tanggal_lahir' => $tanggal_lahir,
    'telepon' => $telepon,
    'alamat' => $alamat,
    'alergi' => $alergi,
];

$existingColumns = [];
foreach ($allowedColumns as $col => $val) {
    if (column_exists($conn, 'pasien', $col)) {
        $existingColumns[$col] = $val;
    }
}

if (empty($existingColumns)) {
    $_SESSION['error'] = 'Struktur tabel pasien tidak cocok. Tidak ada kolom yang bisa disimpan.';
    header('Location: pasien.php');
    exit;
}

if ($id > 0) {
    // UPDATE
    $setParts = [];
    $params = [];

    foreach ($existingColumns as $col => $val) {
        $setParts[] = "`$col` = ?";
        $params[] = $val;
    }

    if (column_exists($conn, 'pasien', 'updated_at')) {
        $setParts[] = "`updated_at` = NOW()";
    }

    $params[] = $id;

    $sql = "UPDATE pasien SET " . implode(', ', $setParts) . " WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = 'Gagal menyiapkan update pasien: ' . $conn->error;
        header('Location: pasien.php?edit=' . $id);
        exit;
    }

    $types = str_repeat('s', count($params) - 1) . 'i';
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Data pasien berhasil diperbarui.';
        header('Location: pasien.php');
        exit;
    } else {
        $_SESSION['error'] = 'Gagal memperbarui pasien: ' . $stmt->error;
        header('Location: pasien.php?edit=' . $id);
        exit;
    }
} else {
    // INSERT
    $columns = array_keys($existingColumns);
    $placeholders = array_fill(0, count($columns), '?');
    $params = array_values($existingColumns);

    if (column_exists($conn, 'pasien', 'created_at')) {
        $columns[] = 'created_at';
        $placeholders[] = 'NOW()';
    }

    if (column_exists($conn, 'pasien', 'updated_at')) {
        $columns[] = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    $quotedColumns = array_map(fn($c) => "`$c`", $columns);

    $sql = "INSERT INTO pasien (" . implode(', ', $quotedColumns) . ")
            VALUES (" . implode(', ', $placeholders) . ")";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $_SESSION['error'] = 'Gagal menyiapkan simpan pasien: ' . $conn->error;
        header('Location: pasien.php');
        exit;
    }

    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Pasien berhasil disimpan.';
        header('Location: pasien.php');
        exit;
    } else {
        $_SESSION['error'] = 'Gagal menyimpan pasien: ' . $stmt->error;
        header('Location: pasien.php');
        exit;
    }
}
