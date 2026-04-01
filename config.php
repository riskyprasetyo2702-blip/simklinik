<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* =========================================================
 | Ambil ENV (App Platform)
 * ========================================================= */
$host = getenv('DB_HOST') ?: 'localhost';
$port = (int)(getenv('DB_PORT') ?: 3306);
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'simklinik';

/* =========================================================
 | Koneksi MySQL (pakai TCP + SSL untuk DO)
 * ========================================================= */
<?php
require_once __DIR__ . '/config.php';
}

/* Aktifkan SSL (DO Managed MySQL butuh SSL) */
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die("DB CONNECT ERROR: " . mysqli_connect_error());
}

$conn->set_charset('utf8mb4');

/* =========================================================
 | Konfigurasi Klinis (boleh tetap seperti sebelumnya)
 * ========================================================= */
if (!defined('LOGO_KLINIK')) {
    define('LOGO_KLINIK', __DIR__ . '/assets/logo-klinik.png');
}
if (!defined('QRIS_IMAGE')) {
    define('QRIS_IMAGE', __DIR__ . '/assets/qris.png');
}
if (!defined('NAMA_KLINIK')) {
    define('NAMA_KLINIK', 'Poli Gigi');
}
if (!defined('TAGLINE_KLINIK')) {
    define('TAGLINE_KLINIK', 'Praktek Mandiri Dokter Gigi Andreas Aryo');
}
if (!defined('ALAMAT_KLINIK')) {
    define('ALAMAT_KLINIK', 'Bukit Nusa Indah 77');
}
if (!defined('TELP_KLINIK')) {
    define('TELP_KLINIK', '08111-18-17-18');
}
if (!defined('EMAIL_KLINIK')) {
    define('EMAIL_KLINIK', 'tigadental@gmail.com');
}
if (!defined('DOKTER_KLINIK')) {
    define('DOKTER_KLINIK', 'drg. Andreas Aryo R.P');
}
if (!defined('SIP_DOKTER')) {
    define('SIP_DOKTER', 'SIP.446/DRG/1/440-CPMptsp/2024');
}
if (!defined('BANK_KLINIK')) {
    define('BANK_KLINIK', 'Transfer / Tunai / Debit / QRIS');
}
if (!defined('QRIS_INFO')) {
    define('QRIS_INFO', 'Scan QRIS tersedia di resepsionis');
}
