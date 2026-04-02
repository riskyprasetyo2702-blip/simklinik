<?php
echo "START<br>";

$host = getenv("DB_HOST") ?: "";
$port = (int)(getenv("DB_PORT") ?: 3306);
$user = getenv("DB_USER") ?: "";
$pass = getenv("DB_PASS") ?: "";
$db   = getenv("DB_NAME") ?: "";

echo "HOST: $host<br>";
echo "PORT: $port<br>";
echo "USER: $user<br>";
echo "DB: $db<br>";

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 8);

if (!@mysqli_real_connect($conn, $host, $user, $pass, $db, $port)) {
    die("DB ERROR: " . mysqli_connect_error());
}

echo "DB CONNECTED OK";
