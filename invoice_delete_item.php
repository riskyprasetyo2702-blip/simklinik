<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$conn = new mysqli("localhost","root","","simklinik");
if ($conn->connect_error) {
    die("Koneksi gagal");
}

$id = (int)($_GET['id'] ?? 0);

$get = $conn->prepare("
    SELECT invoice_id
    FROM invoice_items
    WHERE id = ?
    LIMIT 1
");
$get->bind_param("i", $id);
$get->execute();
$res = $get->get_result();
$item = $res->fetch_assoc();
$get->close();

if (!$item) {
    header("Location: invoice.php");
    exit;
}

$invoice_id = (int)$item['invoice_id'];

$del = $conn->prepare("DELETE FROM invoice_items WHERE id = ?");
$del->bind_param("i", $id);
$del->execute();
$del->close();

$sum = $conn->prepare("
    SELECT COALESCE(SUM(subtotal),0) AS subtotal_baru
    FROM invoice_items
    WHERE invoice_id = ?
");
$sum->bind_param("i", $invoice_id);
$sum->execute();
$sumRes = $sum->get_result()->fetch_assoc();
$sum->close();

$getInv = $conn->prepare("
    SELECT COALESCE(diskon,0) AS diskon
    FROM invoices
    WHERE id = ?
");
$getInv->bind_param("i", $invoice_id);
$getInv->execute();
$invRes = $getInv->get_result()->fetch_assoc();
$getInv->close();

$subtotalBaru = (float)$sumRes['subtotal_baru'];
$diskon = (float)$invRes['diskon'];
$totalBaru = $subtotalBaru - $diskon;
if ($totalBaru < 0) $totalBaru = 0;

$updInv = $conn->prepare("
    UPDATE invoices
    SET subtotal = ?, total = ?
    WHERE id = ?
");
$updInv->bind_param("ddi", $subtotalBaru, $totalBaru, $invoice_id);
$updInv->execute();
$updInv->close();

header("Location: invoice.php");
exit;