<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
$stats = [
  'pasien' => db_fetch_one("SELECT COUNT(*) jml FROM pasien")['jml'] ?? 0,
  'kunjungan' => db_fetch_one("SELECT COUNT(*) jml FROM kunjungan")['jml'] ?? 0,
  'invoice' => db_fetch_one("SELECT COUNT(*) jml FROM invoice")['jml'] ?? 0,
  'pendapatan' => db_fetch_one("SELECT COALESCE(SUM(nominal),0) total FROM keuangan WHERE jenis='pemasukan'")['total'] ?? 0,
];
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e(KLINIK_NAMA) ?></title>
<style>
*{box-sizing:border-box;font-family:Inter,Arial,sans-serif}body{margin:0;background:linear-gradient(135deg,#e8f0ff,#f8fbff);color:#0f172a}.wrap{max-width:1380px;margin:0 auto;padding:24px}.hero{background:linear-gradient(135deg,#0f172a,#1d4ed8);padding:28px;border-radius:28px;color:#fff;box-shadow:0 30px 60px rgba(29,78,216,.25)}.hero h1{margin:0 0 8px;font-size:34px}.sub{opacity:.9}.top{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap}.btn{display:inline-block;text-decoration:none;background:#fff;color:#0f172a;padding:12px 18px;border-radius:14px;font-weight:700}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px;margin-top:22px}.stat,.card{background:#fff;border-radius:24px;padding:22px;box-shadow:0 16px 32px rgba(15,23,42,.08);border:1px solid #e2e8f0}.stat .n{font-size:34px;font-weight:800}.menu{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;margin-top:22px}.menu a{text-decoration:none;color:#0f172a;background:#fff;border-radius:24px;padding:24px;box-shadow:0 16px 32px rgba(15,23,42,.08);border:1px solid #e2e8f0}.menu a:hover{transform:translateY(-2px)}.menu .t{font-size:22px;font-weight:800;margin-bottom:10px}.muted{color:#64748b}@media(max-width:768px){.hero h1{font-size:26px}}
</style></head><body><div class="wrap">
<div class="hero"><div class="top"><div><h1><?= e(KLINIK_NAMA) ?></h1><div class="sub">SIMRS Klinik Gigi Cloud • Selamat datang, <?= e(current_user_name()) ?></div></div><div><a class="btn" href="logout.php">Logout</a></div></div></div>
<div class="grid">
<div class="stat"><div class="muted">Total Pasien</div><div class="n"><?= e($stats['pasien']) ?></div></div>
<div class="stat"><div class="muted">Total Kunjungan</div><div class="n"><?= e($stats['kunjungan']) ?></div></div>
<div class="stat"><div class="muted">Total Invoice</div><div class="n"><?= e($stats['invoice']) ?></div></div>
<div class="stat"><div class="muted">Pendapatan Tercatat</div><div class="n" style="font-size:28px"><?= e(rupiah($stats['pendapatan'])) ?></div></div>
</div>
<div class="menu">
<a href="pasien.php"><div class="t">Data Pasien</div><div class="muted">Registrasi, edit, pencarian, dan riwayat transaksi pasien.</div></a>
<a href="kunjungan.php"><div class="t">Kunjungan</div><div class="muted">Keluhan, diagnosa ICD-10, tindakan, dokter, dan catatan kunjungan.</div></a>
<a href="odontogram.php"><div class="t">Odontogram Pro</div><div class="muted">Input gigi, permukaan, tindakan, tarif, lalu dorong otomatis ke billing.</div></a>
<a href="invoice.php"><div class="t">Billing & Invoice</div><div class="muted">Manual pricing, QRIS, status lunas, print invoice, dan riwayat lengkap.</div></a>
<a href="resume_medis.php"><div class="t">Resume Medis</div><div class="muted">Ringkasan medis kunjungan yang siap cetak.</div></a>
<a href="surat_sakit.php"><div class="t">Surat Sakit</div><div class="muted">Pembuatan surat sakit dengan nomor otomatis dan format print.</div></a>
</div></div></body></html>