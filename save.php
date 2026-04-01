<?php
$conn = new mysqli("localhost", "root", "", "simklinik");
if ($conn->connect_error) {
    die("Koneksi database gagal");
}

$visit_id = $_POST['visit_id'] ?? '';
$tooth_number = $_POST['tooth_number'] ?? '';
$surface_code = $_POST['surface_code'] ?? '';
$condition_code = $_POST['condition_code'] ?? '';
$status_type = $_POST['status_type'] ?? 'existing';
$send_to_billing = $_POST['send_to_billing'] ?? '0';

if ($visit_id === '' || $tooth_number === '' || $surface_code === '' || $condition_code === '') {
    exit('Data tidak lengkap');
}

$stmt = $conn->prepare("
    INSERT INTO odontogram_surfaces (visit_id, tooth_number, surface_code, condition_code, status_type)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
        condition_code = VALUES(condition_code),
        status_type = VALUES(status_type),
        updated_at = CURRENT_TIMESTAMP
");
$stmt->bind_param("issss", $visit_id, $tooth_number, $surface_code, $condition_code, $status_type);
$stmt->execute();
$stmt->close();

if ($send_to_billing === '1') {
    $map = $conn->prepare("
        SELECT treatment_id, auto_add_invoice_item, qty_default
        FROM odontogram_condition_billing
        WHERE condition_code = ?
        LIMIT 1
    ");
    $map->bind_param("s", $condition_code);
    $map->execute();
    $mapResult = $map->get_result();
    $billingMap = $mapResult->fetch_assoc();
    $map->close();

    if ($billingMap && (int)$billingMap['auto_add_invoice_item'] === 1 && !empty($billingMap['treatment_id'])) {
        $invoiceCheck = $conn->prepare("SELECT id FROM invoices WHERE visit_id = ? LIMIT 1");
        $invoiceCheck->bind_param("i", $visit_id);
        $invoiceCheck->execute();
        $invoiceRes = $invoiceCheck->get_result();
        $invoice = $invoiceRes->fetch_assoc();
        $invoiceCheck->close();

        if (!$invoice) {
            $nomor_invoice = 'INV' . time() . rand(10,99);
            $tanggal_invoice = date('Y-m-d H:i:s');

            $createInvoice = $conn->prepare("
                INSERT INTO invoices (visit_id, nomor_invoice, subtotal, diskon, total, metode_bayar, status_bayar, tanggal_invoice)
                VALUES (?, ?, 0, 0, 0, NULL, 'belum_bayar', ?)
            ");
            $createInvoice->bind_param("iss", $visit_id, $nomor_invoice, $tanggal_invoice);
            $createInvoice->execute();
            $invoice_id = $conn->insert_id;
            $createInvoice->close();
        } else {
            $invoice_id = $invoice['id'];
        }

        $treatment_id = (int)$billingMap['treatment_id'];
        $qty = (int)$billingMap['qty_default'];

        $hargaStmt = $conn->prepare("SELECT harga FROM treatments WHERE id = ? LIMIT 1");
        $hargaStmt->bind_param("i", $treatment_id);
        $hargaStmt->execute();
        $hargaRes = $hargaStmt->get_result();
        $treatment = $hargaRes->fetch_assoc();
        $hargaStmt->close();

        if ($treatment) {
            $harga = (float)$treatment['harga'];
            $subtotal = $qty * $harga;

            $checkItem = $conn->prepare("
                SELECT id
                FROM invoice_items
                WHERE invoice_id = ? AND treatment_id = ? AND tooth_number = ? AND surface_code = ? AND sumber = 'odontogram'
                LIMIT 1
            ");
            $checkItem->bind_param("iiss", $invoice_id, $treatment_id, $tooth_number, $surface_code);
            $checkItem->execute();
            $checkRes = $checkItem->get_result();
            $existingItem = $checkRes->fetch_assoc();
            $checkItem->close();

            if (!$existingItem) {
                $insertItem = $conn->prepare("
                    INSERT INTO invoice_items (invoice_id, treatment_id, qty, harga, subtotal, tooth_number, surface_code, sumber)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'odontogram')
                ");
                $insertItem->bind_param("iiiddss", $invoice_id, $treatment_id, $qty, $harga, $subtotal, $tooth_number, $surface_code);
                $insertItem->execute();
                $insertItem->close();

                $sumStmt = $conn->prepare("
                    SELECT COALESCE(SUM(subtotal),0) AS subtotal_total
                    FROM invoice_items
                    WHERE invoice_id = ?
                ");
                $sumStmt->bind_param("i", $invoice_id);
                $sumStmt->execute();
                $sumRes = $sumStmt->get_result();
                $sumData = $sumRes->fetch_assoc();
                $sumStmt->close();

                $subtotal_total = (float)$sumData['subtotal_total'];

                $updateInvoice = $conn->prepare("
                    UPDATE invoices
                    SET subtotal = ?, total = ?
                    WHERE id = ?
                ");
                $updateInvoice->bind_param("ddi", $subtotal_total, $subtotal_total, $invoice_id);
                $updateInvoice->execute();
                $updateInvoice->close();
            }
        }
    }
}

echo "OK";