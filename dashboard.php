<?php
session_start();

/*
|--------------------------------------------------------------------------
| Dashboard aman untuk cloud
|--------------------------------------------------------------------------
| Tujuan:
| - Tidak mengubah alur kerja lama
| - Tetap kompatibel dengan config.php cloud yang sudah ada
| - Tidak bergantung pada bootstrap/helper tambahan
| - Tetap bisa jalan walau nama session login berbeda
|--------------------------------------------------------------------------
*/

// Aktifkan ini sementara kalau mau lihat error langsung
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

// Load config kalau ada
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// Ambil nama user dari session yang mungkin berbeda-beda
$nama_user = 'Administrator';

if (isset($_SESSION['username']) && $_SESSION['username'] !== '') {
    $nama_user = $_SESSION['username'];
} elseif (isset($_SESSION['nama']) && $_SESSION['nama'] !== '') {
    $nama_user = $_SESSION['nama'];
} elseif (isset($_SESSION['name']) && $_SESSION['name'] !== '') {
    $nama_user = $_SESSION['name'];
} elseif (isset($_SESSION['user']) && $_SESSION['user'] !== '') {
    $nama_user = is_array($_SESSION['user']) && isset($_SESSION['user']['username'])
        ? $_SESSION['user']['username']
        : (is_string($_SESSION['user']) ? $_SESSION['user'] : 'Administrator');
}

// Cek login fleksibel
$logged_in = false;
if (isset($_SESSION['user_id']) || isset($_SESSION['username']) || isset($_SESSION['nama']) || isset($_SESSION['user'])) {
    $logged_in = true;
}

// Kalau ingin dashboard tetap wajib login, aktifkan blok ini
if (!$logged_in) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Klinik</title>
    <style>
        *{
            box-sizing:border-box;
            margin:0;
            padding:0;
            font-family:Arial, Helvetica, sans-serif;
        }
        body{
            background:linear-gradient(135deg,#eef2ff 0%,#f8fafc 100%);
            color:#1f2937;
        }
        .container{
            max-width:1200px;
            margin:40px auto;
            padding:0 20px;
        }
        .panel{
            background:#ffffff;
            border-radius:24px;
            box-shadow:0 20px 40px rgba(15,23,42,.08);
            padding:32px;
            border:1px solid #e5e7eb;
        }
        .topbar{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:16px;
            flex-wrap:wrap;
            margin-bottom:28px;
        }
        .title h1{
            font-size:42px;
            line-height:1.15;
            color:#0f172a;
            margin-bottom:10px;
        }
        .title p{
            font-size:18px;
            color:#475569;
        }
        .logout-btn{
            display:inline-block;
            text-decoration:none;
            background:#2563eb;
            color:#fff;
            padding:14px 22px;
            border-radius:12px;
            font-weight:700;
            transition:.2s ease;
        }
        .logout-btn:hover{
            background:#1d4ed8;
        }
        .hero{
            background:linear-gradient(135deg,#2563eb,#1e40af);
            color:#fff;
            border-radius:20px;
            padding:24px;
            margin-bottom:24px;
        }
        .hero h2{
            font-size:26px;
            margin-bottom:8px;
        }
        .hero p{
            font-size:15px;
            opacity:.95;
            line-height:1.6;
        }
        .menu-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:18px;
        }
        .menu-card{
            display:flex;
            flex-direction:column;
            justify-content:center;
            min-height:110px;
            padding:20px;
            border-radius:18px;
            text-decoration:none;
            background:#f8fafc;
            border:1px solid #dbeafe;
            color:#0f172a;
            box-shadow:0 8px 18px rgba(37,99,235,.06);
            transition:.2s ease;
        }
        .menu-card:hover{
            transform:translateY(-3px);
            background:#eff6ff;
            border-color:#93c5fd;
        }
        .menu-card .label{
            font-size:22px;
            font-weight:700;
            margin-bottom:8px;
        }
        .menu-card .desc{
            font-size:14px;
            color:#475569;
            line-height:1.5;
        }
        .section-title{
            margin:28px 0 14px;
            font-size:20px;
            font-weight:700;
            color:#0f172a;
        }
        .note{
            margin-top:24px;
            background:#fff7ed;
            color:#9a3412;
            border:1px solid #fdba74;
            border-radius:14px;
            padding:16px 18px;
            line-height:1.6;
            font-size:14px;
        }
        @media (max-width:768px){
            .title h1{font-size:32px;}
            .logout-btn{width:100%; text-align:center;}
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="panel">
            <div class="topbar">
                <div class="title">
                    <h1>Dashboard Klinik</h1>
                    <p>Selamat datang, <?php echo htmlspecialchars($nama_user); ?></p>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>

            <div class="hero">
                <h2>SIM Klinik Cloud</h2>
                <p>Kelola pasien, kunjungan, invoice, resume medis, surat sakit, dan odontogram dalam satu alur kerja yang rapi dan terhubung.</p>
            </div>

            <div class="menu-grid">
                <a class="menu-card" href="pasien.php">
                    <div class="label">Data Pasien</div>
                    <div class="desc">Input, edit, pencarian, dan riwayat data pasien.</div>
                </a>

                <a class="menu-card" href="kunjungan.php">
                    <div class="label">Kunjungan</div>
                    <div class="desc">Pemeriksaan, diagnosa, tindakan, dan kunjungan pasien.</div>
                </a>

                <a class="menu-card" href="invoice.php">
                    <div class="label">Invoice</div>
                    <div class="desc">Billing, status lunas, cetak invoice, dan riwayat transaksi.</div>
                </a>

                <a class="menu-card" href="resume_medis.php">
                    <div class="label">Resume Medis</div>
                    <div class="desc">Cetak ringkasan medis berdasarkan data kunjungan.</div>
                </a>

                <a class="menu-card" href="surat_sakit.php">
                    <div class="label">Surat Sakit</div>
                    <div class="desc">Buat dan cetak surat sakit pasien dengan cepat.</div>
                </a>

                <a class="menu-card" href="odontogram.php">
                    <div class="label">Odontogram</div>
                    <div class="desc">Odontogram, tindakan gigi, ICD-10, dan integrasi billing.</div>
                </a>
            </div>

            <div class="note">
                Menu <strong>Resume Medis</strong>, <strong>Surat Sakit</strong>, dan <strong>Odontogram</strong> akan bekerja optimal jika data pasien dan kunjungan sudah tersedia lebih dulu.
            </div>
        </div>
    </div>
</body>
</html>
