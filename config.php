<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');

$conn = mysqli_init();

if (!$conn) {
    die("mysqli init gagal");
}

/* SSL WAJIB untuk DigitalOcean */
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

if (!mysqli_real_connect($conn, $host, $user, $pass, $db, (int)$port, NULL, MYSQLI_CLIENT_SSL)) {
    die("DB CONNECT ERROR: " . mysqli_connect_error());
}

$conn->set_charset('utf8mb4');
