<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();

$conn = db();
if (!$conn) {
    die('Koneksi database tidak tersedia.');
}

$userName = current_user_name();

/*
|--------------------------------------------------------------------------
| Statistik dashboard
|--------------------------------------------------------------------------
*/
$totalPasien = 0;
$totalKunjunganHariIni = 0;
$totalInvoiceHariIni = 0;
$totalPemasukanHariIni = 0;

if (table_exists($conn, 'pasien')) {
    $row = db_fetch_one("SELECT COUNT(*) AS total FROM pasien");
    $totalPasien = (int)($row['total'] ?? 0);
}

if (table_exists($conn, 'kunjungan')) {
    $row = db_fetch_one("SELECT COUNT(*) AS total FROM kunjungan WHERE DATE(tanggal) = CURDATE()");
    $totalKunjunganHariIni = (int)($row['total'] ?? 0);
}

if (table_exists($conn, 'invoice')) {
    $row = db_fetch_one("SELECT COUNT(*) AS total FROM invoice WHERE DATE(tanggal) = CURDATE()");
    $totalInvoiceHariIni = (int)($row['total'] ?? 0);
}

if (table_exists($conn, 'keuangan')) {
    $row = db_fetch_one("
        SELECT COALESCE(SUM(nominal),0) AS total
        FROM keuangan
        WHERE DATE(tanggal) = CURDATE()
    ");
    $totalPemasukanHariIni = (float)($row['total'] ?? 0);
}

/*
|--------------------------------------------------------------------------
| Kunjungan terbaru
|--------------------------------------------------------------------------
*/
$kunjunganTerbaru = [];
if (table_exists($conn, 'kunjungan') && table_exists($conn, 'pasien')) {
    $kunjunganTerbaru = db_fetch_all("
        SELECT k.*, p.no_rm, p.nama
        FROM kunjungan k
        JOIN pasien p ON p.id = k.pasien_id
        ORDER BY k.tanggal DESC, k.id DESC
        LIMIT 8
    ");
}

/*
|--------------------------------------------------------------------------
| Invoice terbaru
|--------------------------------------------------------------------------
*/
$invoiceTerbaru = [];
if (table_exists($conn, 'invoice') && table_exists($conn, 'pasien')) {
    $invoiceTerbaru = db_fetch_all("
        SELECT i.*, p.nama
        FROM invoice i
        LEFT JOIN pasien p ON p.id = i.pasien_id
        ORDER BY i.tanggal DESC, i.id DESC
        LIMIT 8
    ");
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard Premium</title>
<style>
*{box-sizing:border-box;font-family:Inter,Arial,Helvetica,sans-serif}
body{
    margin:0;
    background:
        radial-gradient(circle at top left, #dbeafe 0%, transparent 28%),
        radial-gradient(circle at top right, #e9d5ff 0%, transparent 24%),
        linear-gradient(180deg,#f8fbff 0%,#eef4fb 100%);
    color:#0f172a;
}
.layout{
    max-width:1440px;
    margin:0 auto;
    padding:24px;
}
.topbar{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
    flex-wrap:wrap;
    margin-bottom:22px;
}
.brand-card{
    flex:1;
    min-width:300px;
    background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 100%);
    color:#fff;
    border-radius:28px;
    padding:28px;
    box-shadow:0 20px 40px rgba(15,23,42,.15);
}
.brand-card h1{
    margin:0 0 10px;
    font-size:34px;
    line-height:1.15;
}
.brand-card p{
    margin:0;
    font-size:15px;
    opacity:.92;
    line-height:1.7;
}
.top-actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}
.btn{
    display:inline-block;
    text-decoration:none;
    background:#0f172a;
    color:#fff;
    padding:12px 16px;
    border-radius:14px;
    font-weight:700;
    box-shadow:0 8px 18px rgba(15,23,42,.08);
}
.btn.secondary{
    background:#475569;
}
.btn.light{
    background:#fff;
    color:#111827;
}

.stats-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:22px;
}
.stat-card{
    background:#fff;
    border-radius:22px;
    padding:22px;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
    border:1px solid rgba(255,255,255,.7);
}
.stat-label{
    font-size:13px;
    color:#64748b;
    margin-bottom:8px;
}
.stat-value{
    font-size:34px;
    font-weight:800;
    color:#111827;
}
.stat-sub{
    margin-top:6px;
    font-size:13px;
    color:#94a3b8;
}

.quick-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:22px;
}
.quick-card{
    text-decoration:none;
    background:#fff;
    color:#0f172a;
    border-radius:22px;
    padding:22px;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
    transition:.2s ease;
    border:1px solid #e2e8f0;
}
.quick-card:hover{
    transform:translateY(-3px);
    box-shadow:0 20px 36px rgba(15,23,42,.12);
}
.quick-card .icon{
    width:52px;
    height:52px;
    border-radius:16px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:24px;
    margin-bottom:14px;
    background:#eff6ff;
}
.quick-card h3{
    margin:0 0 8px;
    font-size:18px;
}
.quick-card p{
    margin:0;
    color:#64748b;
    font-size:14px;
    line-height:1.6;
}

.content-grid{
    display:grid;
    grid-template-columns:1.15fr .85fr;
    gap:18px;
}
.panel{
    background:#fff;
    border-radius:22px;
    padding:22px;
    box-shadow:0 12px 28px rgba(15,23,42,.08);
}
.panel h2{
    margin:0 0 14px;
    font-size:20px;
}
.table-wrap{overflow:auto}
.table{
    width:100%;
    border-collapse:collapse;
}
.table th,.table td{
    padding:12px 10px;
    border-bottom:1px solid #e2e8f0;
    text-align:left;
    vertical-align:top;
    font-size:14px;
}
.table th{
    color:#64748b;
    font-size:13px;
    background:#f8fafc;
}
.badge{
    display:inline-block;
    padding:6px 10px;
    border-radius:999px;
    font-size:12px;
    background:#e2e8f0;
}
.badge.lunas{background:#dcfce7;color:#166534}
.badge.pending{background:#fef3c7;color:#92400e}
.badge.belum{background:#fee2e2;color:#991b1b}
.muted{
    color:#64748b;
    font-size:13px;
}
.footer-note{
    margin-top:18px;
    color:#64748b;
    font-size:13px;
}

@media(max-width:1200px){
    .stats-grid,.quick-grid{grid-template-columns:repeat(2,1fr)}
    .content-grid{grid-template-columns:1fr}
}
@media(max-width:640px){
    .layout{padding:16px}
    .stats-grid,.quick-grid{grid-template-columns:1fr}
    .brand-card h1{font-size:26px}
    .stat-value{font-size:28px}
}
</style>
</head>
<body>
<div class="layout">

    <div class="topbar">
        <div class="brand-card">
            <h1><?= e(KLINIK_NAMA) ?></h1>
            <p>
                Dashboard SIMRS Klinik Gigi berbasis cloud untuk mengelola pasien,
                kunjungan, odontogram, billing, resume medis, surat sakit, dan laporan keuangan.
            </p>
            <p style="margin-top:12px;">
                Selamat datang, <strong><?= e($userName) ?></strong>
            </p>
        </div>

        <div class="top-actions">
            <a class="btn light" href="pasien.php">Data Pasien</a>
            <a class="btn secondary" href="laporan_keuangan.php">Laporan</a>
            <a class="btn" href="logout.php">Logout</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-label">Total Pasien</div>
            <div class="stat-value"><?= number_format($totalPasien, 0, ',', '.') ?></div>
            <div class="stat-sub">Seluruh data pasien aktif</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Kunjungan Hari Ini</div>
            <div class="stat-value"><?= number_format($totalKunjunganHariIni, 0, ',', '.') ?></div>
            <div class="stat-sub">Jumlah kunjungan pada hari ini</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Invoice Hari Ini</div>
            <div class="stat-value"><?= number_format($totalInvoiceHariIni, 0, ',', '.') ?></div>
            <div class="stat-sub">Jumlah invoice yang dibuat hari ini</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Pemasukan Hari Ini</div>
            <div class="stat-value"><?= rupiah($totalPemasukanHariIni) ?></div>
            <div class="stat-sub">Dari invoice status lunas</div>
        </div>
    </div>

    <div class="quick-grid">
        <a class="quick-card" href="pasien.php">
            <div class="icon">👤</div>
            <h3>Data Pasien</h3>
            <p>Registrasi, edit, pencarian, dan histori lengkap pasien.</p>
        </a>

        <a class="quick-card" href="kunjungan.php">
            <div class="icon">🩺</div>
            <h3>Kunjungan</h3>
            <p>Keluhan, diagnosa, ICD-10, dokter, tindakan, dan catatan medis.</p>
        </a>

        <a class="quick-card" href="invoice.php">
            <div class="icon">💳</div>
            <h3>Billing & Invoice</h3>
            <p>Tambah item, tarik dari odontogram, QRIS, diskon, dan status bayar.</p>
        </a>

        <a class="quick-card" href="laporan_keuangan.php">
            <div class="icon">📊</div>
            <h3>Laporan Keuangan</h3>
            <p>Rekap pemasukan, metode bayar, dan status invoice.</p>
        </a>

        <a class="quick-card" href="odontogram.php">
            <div class="icon">🦷</div>
            <h3>Odontogram Pro</h3>
            <p>Input tindakan per gigi, surface, harga, qty, dan integrasi billing.</p>
        </a>

        <a class="quick-card" href="resume_medis.php?kunjungan_id=1">
            <div class="icon">📄</div>
            <h3>Resume Medis</h3>
            <p>Resume kunjungan, terapi, instruksi, dan print profesional.</p>
        </a>

        <a class="quick-card" href="surat_sakit.php?kunjungan_id=1">
            <div class="icon">📝</div>
            <h3>Surat Sakit</h3>
            <p>Surat sakit otomatis dari kunjungan dan siap cetak.</p>
        </a>

        <a class="quick-card" href="pasien_history.php?pasien_id=1">
            <div class="icon">📚</div>
            <h3>Riwayat Pasien</h3>
            <p>Riwayat kunjungan, odontogram, invoice, dan dokumen medis.</p>
        </a>
    </div>

    <div class="content-grid">
        <div class="panel">
            <h2>Kunjungan Terbaru</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Pasien</th>
                            <th>Diagnosa</th>
                            <th>Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kunjunganTerbaru as $k): ?>
                            <tr>
                                <td><?= e($k['tanggal'] ?? '-') ?></td>
                                <td>
                                    <strong><?= e($k['no_rm'] ?? '') ?></strong><br>
                                    <?= e($k['nama'] ?? '-') ?>
                                </td>
                                <td>
                                    <span class="muted"><?= e($k['icd10_code'] ?? '') ?></span><br>
                                    <?= e($k['diagnosa'] ?? '-') ?>
                                </td>
                                <td><?= e($k['tindakan'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$kunjunganTerbaru): ?>
                            <tr><td colspan="4">Belum ada data kunjungan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h2>Invoice Terbaru</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Invoice</th>
                            <th>Pasien</th>
                            <th>Status</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoiceTerbaru as $i): ?>
                            <?php
                            $status = strtolower($i['status_bayar'] ?? '');
                            $cls = 'belum';
                            if ($status === 'lunas') $cls = 'lunas';
                            elseif ($status === 'pending') $cls = 'pending';
                            ?>
                            <tr>
                                <td><?= e($i['no_invoice'] ?? '-') ?></td>
                                <td><?= e($i['nama'] ?? '-') ?></td>
                                <td><span class="badge <?= $cls ?>"><?= e($i['status_bayar'] ?? '-') ?></span></td>
                                <td><?= rupiah($i['total'] ?? 0) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$invoiceTerbaru): ?>
                            <tr><td colspan="4">Belum ada data invoice.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="footer-note">
                Untuk membuka resume medis, surat sakit, dan riwayat pasien secara tepat,
                akses sebaiknya dilakukan dari data kunjungan atau data pasien yang sudah dipilih.
            </div>
        </div>
    </div>

</div>
</body>
</html>
