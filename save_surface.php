<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = new mysqli("localhost", "root", "", "simklinik");
if ($conn->connect_error) {
    exit("Koneksi database gagal: " . $conn->connect_error);
}

$visit_id        = (int)($_POST['visit_id'] ?? 0);
$patient_id      = (int)($_POST['patient_id'] ?? 0);
$tooth_number    = trim($_POST['tooth_number'] ?? '');
$surface_code    = trim($_POST['surface_code'] ?? '');
$condition_code  = trim($_POST['condition_code'] ?? '');
$status_type     = trim($_POST['status_type'] ?? 'completed');
$send_to_billing = trim($_POST['send_to_billing'] ?? '0');

$tindakan_id = (int)($_POST['tindakan_id'] ?? 0);
$harga       = (int)($_POST['harga'] ?? 0);
$qty         = (int)($_POST['qty'] ?? 1);
$satuan      = trim($_POST['satuan_harga'] ?? 'per tindakan');
$catatan     = trim($_POST['catatan'] ?? '');

if ($visit_id <= 0 || $tooth_number === '' || $surface_code === '' || $condition_code === '' || $tindakan_id <= 0) {
    exit("Data tidak lengkap");
}

if ($qty <= 0) {
    $qty = 1;
}

if ($harga < 0) {
    $harga = 0;
}

$stmt = $conn->prepare("
    INSERT INTO odontogram_surfaces
        (visit_id, tooth_number, surface_code, condition_code, status_type)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        condition_code = VALUES(condition_code),
        status_type = VALUES(status_type),
        updated_at = CURRENT_TIMESTAMP
");

if (!$stmt) {
    exit("Prepare odontogram_surfaces gagal: " . $conn->error);
}

$stmt->bind_param("issss", $visit_id, $tooth_number, $surface_code, $condition_code, $status_type);

if (!$stmt->execute()) {
    exit("Simpan odontogram gagal: " . $stmt->error);
}
$stmt->close();

$getTindakan = $conn->prepare("
    SELECT id, kode, nama_tindakan, kategori
    FROM tindakan
    WHERE id = ?
    LIMIT 1
");

if (!$getTindakan) {
    exit("Prepare tindakan gagal: " . $conn->error);
}

$getTindakan->bind_param("i", $tindakan_id);
$getTindakan->execute();
$tindakanRes = $getTindakan->get_result();
$tindakan = $tindakanRes->fetch_assoc();
$getTindakan->close();

if (!$tindakan) {
    exit("Master tindakan tidak ditemukan");
}

$kode_tindakan = $tindakan['kode'];
$nama_tindakan = $tindakan['nama_tindakan'];
$kategori      = $tindakan['kategori'];
$subtotal      = $harga * $qty;

$kode_db          = mysqli_real_escape_string($conn, $kode_tindakan);
$nama_tindakan_db = mysqli_real_escape_string($conn, $nama_tindakan);
$kategori_db      = mysqli_real_escape_string($conn, $kategori);
$tooth_db         = mysqli_real_escape_string($conn, $tooth_number);
$surface_db       = mysqli_real_escape_string($conn, $surface_code);
$catatan_db       = mysqli_real_escape_string($conn, $catatan);
$satuan_db        = mysqli_real_escape_string($conn, $satuan);

$cekOdo = mysqli_query($conn, "
    SELECT id
    FROM odontogram_tindakan
    WHERE kunjungan_id = $visit_id
      AND tindakan_id = $tindakan_id
      AND nomor_gigi = '$tooth_db'
      AND surface_code = '$surface_db'
    LIMIT 1
");

if (!$cekOdo) {
    exit("Cek odontogram_tindakan gagal: " . mysqli_error($conn));
}

if (mysqli_num_rows($cekOdo) === 0) {
    $sqlOdo = "
        INSERT INTO odontogram_tindakan
        (pasien_id, kunjungan_id, nomor_gigi, surface_code, tindakan_id, nama_tindakan, kategori, harga, qty, subtotal, satuan_harga, catatan)
        VALUES
        ($patient_id, $visit_id, '$tooth_db', '$surface_db', $tindakan_id, '$nama_tindakan_db', '$kategori_db', $harga, $qty, $subtotal, '$satuan_db', '$catatan_db')
    ";

    if (!mysqli_query($conn, $sqlOdo)) {
        exit("Gagal simpan odontogram tindakan: " . mysqli_error($conn));
    }
}

if ($send_to_billing !== '1') {
    echo "OK tersimpan tanpa billing";
    exit;
}

$treatment_id_billing = 0;

$cekTreatment = mysqli_query($conn, "
    SELECT id
    FROM treatments
    WHERE kode = '$kode_db'
    LIMIT 1
");

if (!$cekTreatment) {
    exit("Cek treatments gagal: " . mysqli_error($conn));
}

if (mysqli_num_rows($cekTreatment) > 0) {
    $tr = mysqli_fetch_assoc($cekTreatment);
    $treatment_id_billing = (int)$tr['id'];
} else {
    $insertTreatment = mysqli_query($conn, "
        INSERT INTO treatments (kode, nama_tindakan, kategori, harga)
        VALUES ('$kode_db', '$nama_tindakan_db', '$kategori_db', $harga)
    ");

    if (!$insertTreatment) {
        exit("Gagal membuat treatments: " . mysqli_error($conn));
    }

    $treatment_id_billing = (int)mysqli_insert_id($conn);
}

if ($treatment_id_billing <= 0) {
    exit("Mapping ke treatments gagal");
}

$invoice_id = 0;

$cekInvoice = mysqli_query($conn, "
    SELECT id
    FROM invoices
    WHERE visit_id = $visit_id
    LIMIT 1
");

if (!$cekInvoice) {
    exit("Cek invoices gagal: " . mysqli_error($conn));
}

if (mysqli_num_rows($cekInvoice) > 0) {
    $inv = mysqli_fetch_assoc($cekInvoice);
    $invoice_id = (int)$inv['id'];
} else {
    $nomor_invoice = 'INV' . date('YmdHis') . rand(10, 99);
    $tanggal_invoice = date('Y-m-d H:i:s');

    $createInvoice = mysqli_query($conn, "
        INSERT INTO invoices
        (visit_id, nomor_invoice, subtotal, diskon, total, metode_bayar, status_bayar, tanggal_invoice)
        VALUES
        ($visit_id, '$nomor_invoice', 0, 0, 0, NULL, 'pending', '$tanggal_invoice')
    ");

    if (!$createInvoice) {
        exit("Gagal membuat invoices: " . mysqli_error($conn));
    }

    $invoice_id = (int)mysqli_insert_id($conn);
}

$cekItem = mysqli_query($conn, "
    SELECT id
    FROM invoice_items
    WHERE invoice_id = $invoice_id
      AND treatment_id = $treatment_id_billing
      AND tooth_number = '$tooth_db'
      AND surface_code = '$surface_db'
      AND sumber = 'odontogram'
    LIMIT 1
");

if (!$cekItem) {
    exit("Cek invoice_items gagal: " . mysqli_error($conn));
}

if (mysqli_num_rows($cekItem) === 0) {
    $insertItem = mysqli_query($conn, "
        INSERT INTO invoice_items
        (invoice_id, treatment_id, nama_tindakan, qty, harga, subtotal, tooth_number, surface_code, sumber)
        VALUES
        ($invoice_id, $treatment_id_billing, '$nama_tindakan_db', $qty, $harga, $subtotal, '$tooth_db', '$surface_db', 'odontogram')
    ");

    if (!$insertItem) {
        exit("Gagal insert invoice_items: " . mysqli_error($conn));
    }
}

$sumQ = mysqli_query($conn, "
    SELECT COALESCE(SUM(subtotal), 0) AS subtotal_total
    FROM invoice_items
    WHERE invoice_id = $invoice_id
");

if (!$sumQ) {
    exit("Hitung subtotal invoice_items gagal: " . mysqli_error($conn));
}

$sumData = mysqli_fetch_assoc($sumQ);
$subtotal_total = (int)$sumData['subtotal_total'];

$invQ = mysqli_query($conn, "
    SELECT COALESCE(diskon, 0) AS diskon
    FROM invoices
    WHERE id = $invoice_id
    LIMIT 1
");

if (!$invQ) {
    exit("Ambil diskon invoices gagal: " . mysqli_error($conn));
}

$invData = mysqli_fetch_assoc($invQ);
$diskon = (int)$invData['diskon'];

$total = $subtotal_total - $diskon;
if ($total < 0) {
    $total = 0;
}

$updateInvoice = mysqli_query($conn, "
    UPDATE invoices
    SET subtotal = $subtotal_total,
        total = $total
    WHERE id = $invoice_id
");

if (!$updateInvoice) {
    exit("Gagal update total invoices: " . mysqli_error($conn));
}

echo "OK tindakan masuk ke odontogram dan billing";
exit;
