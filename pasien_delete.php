<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$conn = new mysqli("localhost","root","","simklinik");
if ($conn->connect_error) die("Koneksi gagal");

$id = (int)($_GET['id'] ?? 0);

$del = $conn->prepare("DELETE FROM patients WHERE id = ?");
$del->bind_param("i", $id);
$del->execute();
$del->close();

header("Location: pasien.php");
exit;