<?php
require_once 'bootstrap.php';
ensure_logged_in();

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    die('Akses hanya untuk admin');
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Panel</title>
<style>
*{box-sizing:border-box;font-family:Arial,Helvetica,sans-serif}
body{margin:0;background:linear-gradient(180deg,#f8fbff 0%,#eef4fb 100%);color:#0f172a}
.wrap{max-width:1250px;margin:24px auto;padding:0 16px}
.hero{background:linear-gradient(135deg,#0f172a,#1d4ed8);color:#fff;border-radius:24px;padding:28px;box-shadow:0 20px 40px rgba(15,23,42,.15);margin-bottom:20px}
.hero h1{margin:0 0 8px;font-size:34px}
.hero p{margin:0;opacity:.92;line-height:1.7}
.top-actions{margin-top:18px;display:flex;gap:10px;flex-wrap:wrap}
.btn{display:inline-block;text-decoration:none;background:#fff;color:#111827;padding:12px 16px;border-radius:12px;font-weight:700}
.btn.dark{background:#0f172a;color:#fff}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.card{background:#fff;border-radius:22px;padding:22px;box-shadow:0 12px 28px rgba(15,23,42,.08);border:1px solid #e2e8f0}
.icon{width:54px;height:54px;border-radius:16px;background:#eff6ff;display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:14px}
.card h3{margin:0 0 8px}
.card p{margin:0;color:#64748b;line-height:1.6}
.card a{display:inline-block;margin-top:16px;text-decoration:none;background:#0f172a;color:#fff;padding:10px 14px;border-radius:10px;font-weight:700}
@media(max-width:900px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">

    <div class="hero">
        <h1>Admin Panel</h1>
        <p>Kelola user, widget tindakan, dan pengaturan inti sistem klinik dari satu panel yang rapi dan modern.</p>
        <div class="top-actions">
            <a class="btn" href="dashboard.php">Dashboard</a>
            <a class="btn dark" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <div class="icon">👤</div>
            <h3>Kelola User</h3>
            <p>Tambah akun admin dan dokter, atur role, dan siapkan akses sistem yang terpisah.</p>
            <a href="admin_users.php">Buka User Management</a>
        </div>

        <div class="card">
            <div class="icon">🧩</div>
            <h3>Widget Tindakan</h3>
            <p>Atur tombol tindakan cepat yang tampil di dashboard atau area kerja utama.</p>
            <a href="admin_widget.php">Buka Widget Management</a>
        </div>

        <div class="card">
            <div class="icon">🏥</div>
            <h3>Settings Klinik</h3>
            <p>Atur nama klinik, logo, dan QRIS agar seluruh dokumen terlihat lebih profesional.</p>
            <a href="settings_klinik.php">Buka Settings Klinik</a>
        </div>
    </div>

</div>
</body>
</html>
