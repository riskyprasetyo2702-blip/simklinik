<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: index.php");
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
        body{font-family:Arial;background:#f4f7fb;margin:0;padding:40px}
        .box{max-width:900px;margin:auto;background:#fff;padding:30px;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08)}
        .top{display:flex;justify-content:space-between;align-items:center}
        .btn{padding:10px 14px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px}
        .menu{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-top:24px}
        .card{padding:18px;border:1px solid #e5e7eb;border-radius:12px;background:#fafafa;text-align:center}
    </style>
</head>
<body>
    <div class="box">
        <div class="top">
            <div>
                <h1>Dashboard Klinik</h1>
                <p>Selamat datang, <?= htmlspecialchars($_SESSION['nama']) ?></p>
            </div>
            <a class="btn" href="logout.php">Logout</a>
        </div>

        <div class="menu">
            <div class="card">Data Pasien</div>
            <div class="card">Kunjungan</div>
            <div class="card">Invoice</div>
            <div class="card">Resume Medis</div>
            <div class="card">Surat Sakit</div>
        </div>
    </div>
</body>
</html>
