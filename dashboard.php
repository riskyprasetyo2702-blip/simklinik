<?php
session_start();
require_once 'config.php';

// Cek login
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$nama_user = $_SESSION['username'] ?? 'Administrator';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Klinik</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            background: #eef2f7;
            color: #1f2937;
        }

        .wrapper {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .card {
            background: #ffffff;
            border-radius: 22px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
        }

        .title h1 {
            font-size: 44px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #111827;
        }

        .title p {
            font-size: 22px;
            color: #374151;
        }

        .logout-btn {
            display: inline-block;
            background: #2563eb;
            color: #fff;
            text-decoration: none;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 600;
            transition: 0.2s ease;
        }

        .logout-btn:hover {
            background: #1d4ed8;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 18px;
            margin-top: 34px;
        }

        .menu-card {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 92px;
            background: #f9fafb;
            border: 1px solid #d1d5db;
            border-radius: 16px;
            text-decoration: none;
            color: #111827;
            font-size: 20px;
            font-weight: 600;
            transition: all 0.2s ease;
            text-align: center;
            padding: 18px;
        }

        .menu-card:hover {
            background: #eff6ff;
            border-color: #93c5fd;
            transform: translateY(-2px);
        }

        .info-box {
            margin-top: 30px;
            background: #f3f4f6;
            border-left: 5px solid #2563eb;
            padding: 18px 20px;
            border-radius: 12px;
            color: #374151;
            font-size: 16px;
            line-height: 1.6;
        }

        .info-box strong {
            color: #111827;
        }

        @media (max-width: 768px) {
            .title h1 {
                font-size: 32px;
            }

            .title p {
                font-size: 18px;
            }

            .menu-card {
                font-size: 18px;
                min-height: 80px;
            }

            .logout-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="topbar">
                <div class="title">
                    <h1>Dashboard Klinik</h1>
                    <p>Selamat datang, <?php echo htmlspecialchars($nama_user); ?></p>
                </div>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>

            <div class="menu-grid">
                <a class="menu-card" href="pasien.php">Data Pasien</a>
                <a class="menu-card" href="kunjungan.php">Kunjungan</a>
                <a class="menu-card" href="invoice.php">Invoice</a>
                <a class="menu-card" href="resume_medis.php">Resume Medis</a>
                <a class="menu-card" href="surat_sakit.php">Surat Sakit</a>
            </div>

            <div class="info-box">
                <strong>Catatan:</strong> menu <strong>Resume Medis</strong> dan <strong>Surat Sakit</strong>
                idealnya dipakai setelah data kunjungan tersedia. Jadi bila file Anda meminta
                <code>kunjungan_id</code>, akses utamanya tetap dari halaman <strong>Kunjungan</strong>.
            </div>
        </div>
    </div>
</body>
</html>
