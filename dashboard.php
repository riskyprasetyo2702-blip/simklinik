<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
$conn = db();
$pasienCount = 0;
$kunjunganCount = 0;
$invoiceCount = 0;
$odontoCount = 0;

if ($conn instanceof mysqli) {
    if (table_exists($conn, 'pasien')) {
        $r = $conn->query("SELECT COUNT(*) AS total FROM pasien");
        $pasienCount = $r ? (int)($r->fetch_assoc()['total'] ?? 0) : 0;
    }
    if (table_exists($conn, 'kunjungan')) {
        $r = $conn->query("SELECT COUNT(*) AS total FROM kunjungan");
        $kunjunganCount = $r ? (int)($r->fetch_assoc()['total'] ?? 0) : 0;
    }
    if (table_exists($conn, 'invoice')) {
        $r = $conn->query("SELECT COUNT(*) AS total FROM invoice");
        $invoiceCount = $r ? (int)($r->fetch_assoc()['total'] ?? 0) : 0;
    }
    ensure_odontogram_tables($conn);
    if (table_exists($conn, 'odontogram')) {
        $r = $conn->query("SELECT COUNT(*) AS total FROM odontogram");
        $odontoCount = $r ? (int)($r->fetch_assoc()['total'] ?? 0) : 0;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Klinik Pro</title>
    <style>
        :root {
            --bg1:#0f172a; --bg2:#1e293b; --card:#ffffff; --muted:#64748b; --line:#e2e8f0; --blue:#2563eb; --cyan:#06b6d4; --green:#16a34a; --violet:#7c3aed;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:Inter,Arial,sans-serif;background:linear-gradient(135deg,var(--bg1),#111827 55%,var(--bg2));color:#0f172a}
        .shell{max-width:1350px;margin:0 auto;padding:26px}
        .hero{background:linear-gradient(135deg,rgba(37,99,235,.95),rgba(124,58,237,.92));color:#fff;border-radius:28px;padding:28px;box-shadow:0 18px 60px rgba(0,0,0,.28)}
        .hero-top{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap}
        .hero h1{margin:0;font-size:44px;line-height:1.05}
        .hero p{margin:10px 0 0;font-size:18px;opacity:.92}
        .top-actions{display:flex;gap:12px;flex-wrap:wrap}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:13px 18px;border-radius:14px;text-decoration:none;font-weight:700;border:1px solid rgba(255,255,255,.24);transition:.2s}
        .btn-light{background:#fff;color:#1e3a8a}
        .btn-ghost{background:rgba(255,255,255,.15);color:#fff}
        .btn:hover{transform:translateY(-1px)}
        .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-top:22px}
        .stat{background:rgba(255,255,255,.14);backdrop-filter: blur(10px);padding:18px;border-radius:20px;border:1px solid rgba(255,255,255,.14)}
        .stat .n{font-size:34px;font-weight:800;margin-top:10px}
        .grid{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-top:20px}
        .panel{background:var(--card);border-radius:24px;padding:24px;box-shadow:0 16px 50px rgba(2,6,23,.16)}
        .panel h2{margin:0 0 6px;font-size:26px}
        .sub{color:var(--muted);margin:0 0 18px}
        .menu-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
        .menu-card{display:block;padding:22px;border-radius:22px;border:1px solid var(--line);text-decoration:none;color:#0f172a;background:linear-gradient(180deg,#fff,#f8fafc);min-height:140px;transition:.18s;position:relative;overflow:hidden}
        .menu-card:hover{transform:translateY(-3px);box-shadow:0 14px 30px rgba(15,23,42,.1)}
        .menu-card .tag{display:inline-block;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:800;letter-spacing:.02em;background:#dbeafe;color:#1d4ed8}
        .menu-card h3{margin:14px 0 8px;font-size:24px}
        .menu-card p{margin:0;color:var(--muted);line-height:1.55}
        .menu-card::after{content:'';position:absolute;right:-22px;bottom:-22px;width:110px;height:110px;border-radius:50%;background:linear-gradient(135deg,rgba(37,99,235,.09),rgba(124,58,237,.12))}
        .quick{display:grid;gap:12px}
        .quick a{display:flex;justify-content:space-between;align-items:center;text-decoration:none;color:#0f172a;border:1px solid var(--line);padding:16px 18px;border-radius:16px;background:#fff}
        .quick small{color:var(--muted)}
        .banner{margin-top:18px;padding:18px 20px;border-radius:18px;background:linear-gradient(135deg,#ecfeff,#eff6ff);border:1px solid #bfdbfe}
        .banner strong{display:block;margin-bottom:6px;font-size:18px;color:#0f172a}
        @media (max-width: 1024px){.grid{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.menu-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media (max-width: 700px){.hero h1{font-size:32px}.stats,.menu-grid{grid-template-columns:1fr}.shell{padding:16px}}
    </style>
</head>
<body>
<div class="shell">
    <section class="hero">
        <div class="hero-top">
            <div>
                <h1>Dashboard Klinik Pro</h1>
                <p>Selamat datang, <?= e(current_user_name()) ?>. Menu pasien, kunjungan, odontogram, ICD-10, dan billing sudah dipusatkan agar alur cloud tetap cepat dan rapi.</p>
            </div>
            <div class="top-actions">
                <a class="btn btn-light" href="odontogram.php">Buka Odontogram</a>
                <a class="btn btn-ghost" href="logout.php">Logout</a>
            </div>
        </div>
        <div class="stats">
            <div class="stat"><div>Total Pasien</div><div class="n"><?= $pasienCount ?></div></div>
            <div class="stat"><div>Total Kunjungan</div><div class="n"><?= $kunjunganCount ?></div></div>
            <div class="stat"><div>Total Invoice</div><div class="n"><?= $invoiceCount ?></div></div>
            <div class="stat"><div>Total Odontogram</div><div class="n"><?= $odontoCount ?></div></div>
        </div>
    </section>

    <section class="grid">
        <div class="panel">
            <h2>Modul Utama</h2>
            <p class="sub">Alur kerja dipertahankan: pasien → kunjungan → odontogram/diagnosa → billing/invoice.</p>
            <div class="menu-grid">
                <a class="menu-card" href="pasien.php">
                    <span class="tag">Master Data</span>
                    <h3>Data Pasien</h3>
                    <p>Kelola identitas pasien, nomor RM, pencarian cepat, dan edit data dasar.</p>
                </a>
                <a class="menu-card" href="kunjungan.php">
                    <span class="tag">Transaksi</span>
                    <h3>Kunjungan</h3>
                    <p>Buat kunjungan, pilih pasien, simpan keluhan utama, dan teruskan ke tindakan.</p>
                </a>
                <a class="menu-card" href="odontogram.php">
                    <span class="tag">Klinis</span>
                    <h3>Odontogram Pro</h3>
                    <p>Pilih gigi, tindakan, tarif, diagnosa ICD-10, lalu teruskan otomatis ke billing.</p>
                </a>
                <a class="menu-card" href="invoice.php">
                    <span class="tag">Billing</span>
                    <h3>Invoice</h3>
                    <p>Lihat transaksi, tagihan, status pembayaran, diskon, dan metode bayar.</p>
                </a>
                <a class="menu-card" href="resume_medis.php">
                    <span class="tag">Dokumen</span>
                    <h3>Resume Medis</h3>
                    <p>Cetak ringkasan medis dari data kunjungan dan tindakan yang sudah tersimpan.</p>
                </a>
                <a class="menu-card" href="surat_sakit.php">
                    <span class="tag">Dokumen</span>
                    <h3>Surat Sakit</h3>
                    <p>Buat surat sakit berbasis pasien dan kunjungan yang sudah tersedia.</p>
                </a>
            </div>
            <div class="banner">
                <strong>Integrasi billing</strong>
                Modul odontogram pada paket ini menyimpan diagnosa ICD-10, detail tindakan per gigi, total tarif, lalu mengarahkan ke invoice dengan parameter pasien, kunjungan, diagnosa, dan total billing awal.
            </div>
        </div>

        <div class="panel">
            <h2>Akses Cepat</h2>
            <p class="sub">Shortcut paling sering dipakai di praktik harian.</p>
            <div class="quick">
                <a href="odontogram.php"><span><strong>Buat Odontogram Baru</strong><br><small>Pilih pasien, gigi, tindakan, ICD-10</small></span><span>→</span></a>
                <a href="pasien.php"><span><strong>Tambah Pasien</strong><br><small>Input data pasien baru</small></span><span>→</span></a>
                <a href="kunjungan.php"><span><strong>Input Kunjungan</strong><br><small>Keluhan dan pemeriksaan awal</small></span><span>→</span></a>
                <a href="invoice.php"><span><strong>Buka Billing</strong><br><small>Finalisasi invoice dan pembayaran</small></span><span>→</span></a>
            </div>
        </div>
    </section>
</div>
</body>
</html>
