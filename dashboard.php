<?php
session_start();

if (
    !isset($_SESSION['user_id']) &&
    !isset($_SESSION['username']) &&
    !isset($_SESSION['nama']) &&
    !isset($_SESSION['user'])
) {
    header('Location: login.php');
    exit;
}

$nama_user = 'Administrator';
if (!empty($_SESSION['username'])) {
    $nama_user = $_SESSION['username'];
} elseif (!empty($_SESSION['nama'])) {
    $nama_user = $_SESSION['nama'];
} elseif (!empty($_SESSION['user']) && is_string($_SESSION['user'])) {
    $nama_user = $_SESSION['user'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Klinik</title>
    <style>
        *{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
        body{margin:0;background:#eef3fb;color:#0f172a}
        .wrap{max-width:1320px;margin:24px auto;padding:0 16px}
        .hero{
            background:linear-gradient(135deg,#0f172a,#2563eb);
            color:#fff;
            border-radius:28px;
            padding:28px;
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:16px;
            flex-wrap:wrap;
            margin-bottom:22px;
        }
        .hero h1{margin:0 0 8px;font-size:28px;line-height:1.2}
        .hero p{margin:0;font-size:18px;opacity:.95}
        .logout{
            display:inline-block;
            padding:12px 18px;
            background:#fff;
            color:#111827;
            text-decoration:none;
            border-radius:14px;
            font-weight:700;
        }
        .grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
            gap:18px;
        }
        .card{
            background:#fff;
            border-radius:22px;
            padding:24px;
            text-decoration:none;
            color:#0f172a;
            box-shadow:0 12px 28px rgba(15,23,42,.08);
            border:1px solid #e2e8f0;
            min-height:140px;
            display:block;
        }
        .card:hover{transform:translateY(-2px)}
        .card h3{margin:0 0 10px;font-size:20px}
        .card p{margin:0;color:#64748b;font-size:15px;line-height:1.5}
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <div>
                <h1>Klinik Praktek Mandiri Dokter Gigi Andreas Aryo Risky Prasetyo</h1>
                <p>SIMRS Klinik Gigi Cloud • Selamat datang, <?= htmlspecialchars($nama_user) ?></p>
            </div>
            <a class="logout" href="logout.php">Logout</a>
        </div>

        <div class="grid">
            <a class="card" href="pasien.php">
                <h3>Data Pasien</h3>
                <p>Registrasi, edit, pencarian, dan data pasien.</p>
            </a>

            <a class="card" href="kunjungan.php">
                <h3>Kunjungan</h3>
                <p>Keluhan, diagnosa ICD-10, tindakan, dokter, dan catatan kunjungan.</p>
            </a>

            <a class="card" href="odontogram.php">
                <h3>Odontogram Pro</h3>
                <p>Input tindakan per gigi lalu dorong ke billing.</p>
            </a>

            <a class="card" href="invoice.php">
                <h3>Billing & Invoice</h3>
                <p>Invoice, QRIS, status bayar, dan print PDF.</p>
            </a>

            <a class="card" href="resume_medis.php">
                <h3>Resume Medis</h3>
                <p>Ringkasan medis kunjungan siap cetak.</p>
            </a>

            <a class="card" href="surat_sakit.php">
                <h3>Surat Sakit</h3>
                <p>Pembuatan surat sakit dan format print.</p>
            </a>
        </div>
    </div>
</body>
</html>
