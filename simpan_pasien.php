<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    $_SESSION['error'] = 'Koneksi database tidak tersedia.';
    header('Location: pasien.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: pasien.php');
    exit;
}

$id            = (int)($_POST['id'] ?? 0);
$no_rm         = trim($_POST['no_rm'] ?? '');
$nik           = trim($_POST['nik'] ?? '');
$nama          = trim($_POST['nama'] ?? '');
$jk            = trim($_POST['jk'] ?? '');
$tempat_lahir  = trim($_POST['tempat_lahir'] ?? '');
$tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
$telepon       = trim($_POST['telepon'] ?? '');
$alamat        = trim($_POST['alamat'] ?? '');
$alergi        = trim($_POST['alergi'] ?? '');

if ($no_rm === '' || $nama === '') {
    $_SESSION['error'] = 'No. RM dan Nama Pasien wajib diisi.';
    header('Location: pasien.php' . ($id > 0 ? '?edit=' . $id : ''));
    exit;
}

if (!table_exists($conn, 'pasien')) {
    $_SESSION['error'] = 'Tabel pasien tidak ditemukan di database.';
    header('Location: pasien.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Cek duplikasi No RM
|--------------------------------------------------------------------------
*/
if ($id > 0) {
    $cek = db_fetch_one(
        "SELECT id FROM pasien WHERE no_rm = ? AND id != ? LIMIT 1",
        [$no_rm, $id]
    );
} else {
    $cek = db_fetch_one(
        "SELECT id FROM pasien WHERE no_rm = ? LIMIT 1",
        [$no_rm]
    );
}

if ($cek) {
    $_SESSION['error'] = 'No. RM sudah digunakan. Gunakan No. RM lain.';
    header('Location: pasien.php' . ($id > 0 ? '?edit=' . $id : ''));
    exit;
}

/*
|--------------------------------------------------------------------------
| Susun data hanya untuk kolom yang benar-benar ada
|--------------------------------------------------------------------------
*/
$dataMap = [
    'no_rm'         => $no_rm,
    'nik'           => $nik,
    'nama'          => $nama,
    'jk'            => $jk,
    'tempat_lahir'  => $tempat_lahir,
    'tanggal_lahir' => $tanggal_lahir,
    'telepon'       => $telepon,
    'alamat'        => $alamat,
    'alergi'        => $alergi,
];

$columns = [];
foreach ($dataMap as $col => $val) {
    if (column_exists($conn, 'pasien', $col)) {
        $columns[$col] = $val;
    }
}

if (empty($columns)) {
    $_SESSION['error'] = 'Struktur tabel pasien tidak sesuai.';
    header('Location: pasien.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| UPDATE
|--------------------------------------------------------------------------
*/
if ($id > 0) {
    $setParts = [];
    $params = [];

    foreach ($columns as $col => $val) {
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

    $types = '';
    for ($i = 0; $i < count($params) - 1; $i++) {
        if (is_int($params[$i])) {
            $types .= 'i';
        } elseif (is_float($params[$i])) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    $types .= 'i';

    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $_SESSION['success'] = 'Data pasien berhasil diperbarui.';
        header('Location: pasien.php');
        exit;
    } else {
        $_SESSION['error'] = 'Gagal memperbarui data pasien.';
        header('Location: pasien.php?edit=' . $id);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| INSERT
|--------------------------------------------------------------------------
*/
$insertCols = [];
$placeholders = [];
$params = [];

foreach ($columns as $col => $val) {
    $insertCols[] = "`$col`";
    $placeholders[] = "?";
    $params[] = $val;
}

if (column_exists($conn, 'pasien', 'created_at')) {
    $insertCols[] = "`created_at`";
    $placeholders[] = "NOW()";
}

if (column_exists($conn, 'pasien', 'updated_at')) {
    $insertCols[] = "`updated_at`";
    $placeholders[] = "NOW()";
}

$sql = "INSERT INTO pasien (" . implode(', ', $insertCols) . ")
        VALUES (" . implode(', ', $placeholders) . ")";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $_SESSION['error'] = 'Gagal menyiapkan simpan pasien: ' . $conn->error;
    header('Location: pasien.php');
    exit;
}

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

if ($ok) {
    $_SESSION['success'] = 'Pasien berhasil disimpan.';
    header('Location: pasien.php');
    exit;
} else {
    $_SESSION['error'] = 'Gagal menyimpan pasien.';
    header('Location: pasien.php');
    exit;
}
