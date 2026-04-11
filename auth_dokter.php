<?php
session_start();

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'dokter'])) {
    echo "<script>alert('Akses hanya untuk admin atau dokter'); window.location='dashboard.php';</script>";
    exit;
}
