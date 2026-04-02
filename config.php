<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "CONFIG START<br>";

$host = getenv("DB_HOST") ?: "localhost";
$user = getenv("DB_USER") ?: "root";
$pass = getenv("DB_PASS") ?: "";
$db   = getenv("DB_NAME") ?: "simklinik";
$port = (int)(getenv("DB_PORT") ?: 3306);

echo "HOST=$host<br>";
echo "PORT=$port<br>";
echo "USER=$user<br>";
echo "DB=$db<br>";

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die("Database error: " . $conn->connect_error);
}

echo "DB OK<br>";

$conn->set_charset("utf8mb4");
