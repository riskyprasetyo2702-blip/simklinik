<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: index.php");
    exit;
}

echo "<h1>Dashboard Klinik</h1>";
echo "<p>Login berhasil</p>";
echo "<p>User ID: " . htmlspecialchars((string)($_SESSION['user_id'] ?? '-')) . "</p>";
echo "<p>Nama: " . htmlspecialchars((string)($_SESSION['nama'] ?? '-')) . "</p>";
echo '<p><a href="logout.php">Logout</a></p>';
exit;
