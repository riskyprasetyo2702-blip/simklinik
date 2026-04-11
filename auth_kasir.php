<?php
session_start();

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'kasir'])) {
    echo "<script>alert('Akses hanya untuk admin atau kasir'); window.location='dashboard.php';</script>";
    exit;
}
