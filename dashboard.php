<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

echo "DASHBOARD START<br>";

require_once __DIR__ . '/config.php';
echo "CONFIG OK<br>";

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    die("SESSION LOGIN TIDAK ADA");
}

echo "SESSION OK<br>";
echo "USER ID: " . htmlspecialchars((string)($_SESSION['user_id'] ?? '-')) . "<br>";
echo "NAMA: " . htmlspecialchars((string)($_SESSION['nama'] ?? '-')) . "<br>";

exit;
