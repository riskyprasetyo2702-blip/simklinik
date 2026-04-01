<?php
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$conn = new mysqli("localhost","root","","simklinik");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$totalPasien = $conn->query("SELECT COUNT(*) as total FROM patients")->fetch_assoc()['total'] ?? 0;
$totalKunjungan = $conn->query("SELECT COUNT(*) as total FROM visits")->fetch_assoc()['total'] ?? 0;
$totalInvoice = $conn->query("SELECT COUNT(*) as total FROM invoices")->fetch_assoc()['total'] ?? 0;

$today = date('Y-m-d');

$pasienHariIni = $conn->query("
    SELECT COUNT(DISTINCT patient_id) as total
    FROM visits
    WHERE DATE(tanggal_kunjungan) = '$today'
")->fetch_assoc()['total'] ?? 0;

$kunjunganHariIni = $conn->query("
    SELECT COUNT(*) as total
    FROM visits
    WHERE DATE(tanggal_kunjungan) = '$today'
")->fetch_assoc()['total'] ?? 0;

$lunasHariIni = $conn->query("
    SELECT COALESCE(SUM(total),0) as total
    FROM invoices
    WHERE DATE(tanggal_invoice) = '$today'
    AND status_bayar = 'lunas'
")->fetch_assoc()['total'] ?? 0;

$pendingHariIni = $conn->query("
    SELECT COALESCE(SUM(total),0) as total
    FROM invoices
    WHERE DATE(tanggal_invoice) = '$today'
    AND status_bayar = 'pending'
")->fetch_assoc()['total'] ?? 0;

$invoicePending = $conn->query("
    SELECT COUNT(*) as total
    FROM invoices
    WHERE status_bayar = 'pending'
")->fetch_assoc()['total'] ?? 0;

$invoiceTidakTerbayar = $conn->query("
    SELECT COUNT(*) as total
    FROM invoices
    WHERE status_bayar = 'tidak_terbayar'
")->fetch_assoc()['total'] ?? 0;

$pasienTerbaru = $conn->query("
    SELECT no_rm, nama, no_hp
    FROM patients
    ORDER BY id DESC
    LIMIT 5
");

$invoiceTerbaru = $conn->query("
    SELECT i.nomor_invoice, i.total, i.status_bayar, p.nama
    FROM invoices i
    JOIN visits v ON v.id = i.visit_id
    JOIN patients p ON p.id = v.patient_id
    ORDER BY i.id DESC
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Klinik Gigi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg: #eef3f9;
            --card: #ffffff;
            --text: #17202a;
            --muted: #6b7280;
            --line: #e5e7eb;
        }

        body {
            background:
                radial-gradient(circle at top right, rgba(13,110,253,0.10), transparent 28%),
                radial-gradient(circle at top left, rgba(25,135,84,0.08), transparent 25%),
                var(--bg);
            min-height: 100vh;
            color: var(--text);
        }

        .topbar {
            background: rgba(255,255,255,0.78);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 22px;
            box-shadow: 0 10px 28px rgba(0,0,0,0.06);
        }

        .welcome-card {
            border: 0;
            border-radius: 26px;
            color: #fff;
            background: linear-gradient(135deg, #0d6efd 0%, #4f8cff 50%, #6f42c1 100%);
            box-shadow: 0 20px 40px rgba(13,110,253,0.20);
            overflow: hidden;
            position: relative;
        }

        .welcome-card::after {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            border-radius: 999px;
            background: rgba(255,255,255,0.10);
            right: -60px;
            top: -60px;
        }

        .glass-card,
        .stat-card,
        .menu-card,
        .panel-card {
            background: rgba(255,255,255,0.88);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 22px;
            box-shadow: 0 10px 28px rgba(0,0,0,0.06);
        }

        .stat-card {
            padding: 22px;
            height: 100%;
            transition: .2s ease;
        }

        .stat-card:hover,
        .menu-card:hover {
            transform: translateY(-4px);
        }

        .stat-icon {
            width: 58px;
            height: 58px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 14px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 30px;
            font-weight: 700;
            line-height: 1.1;
        }

        .stat-sub {
            font-size: 13px;
            color: var(--muted);
            margin-top: 8px;
        }

        .section-title {
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 14px;
        }

        .menu-card {
            text-decoration: none;
            color: inherit;
            padding: 20px;
            display: block;
            height: 100%;
            transition: .2s ease;
        }

        .menu-icon {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 14px;
        }

        .menu-title {
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .menu-desc {
            font-size: 13px;
            color: var(--muted);
        }

        .panel-card {
            padding: 22px;
            height: 100%;
        }

        .table-modern th {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            border-bottom: 1px solid var(--line);
        }

        .table-modern td {
            vertical-align: middle;
            border-color: var(--line);
        }

        .badge-soft-success {
            background: rgba(25,135,84,0.12);
            color: #198754;
        }

        .badge-soft-warning {
            background: rgba(255,193,7,0.18);
            color: #8a6d00;
        }

        .badge-soft-danger {
            background: rgba(220,53,69,0.12);
            color: #dc3545;
        }

        .quick-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 14px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.18);
            font-size: 13px;
            margin-right: 8px;
            margin-bottom: 8px;
        }

        .small-muted {
            color: var(--muted);
            font-size: 13px;
        }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="topbar px-4 py-3 mb-4">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <div class="small-muted">Sistem Informasi Klinik Gigi</div>
                <h3 class="mb-0 fw-bold">Dashboard Utama</h3>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-light px-3 py-2">
                    <i class="bi bi-person-circle me-1"></i>
                    <?= htmlspecialchars($_SESSION['nama'] ?? 'User') ?>
                </span>
                <a href="logout.php" class="btn btn-danger">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </div>
    </div>

    <div class="welcome-card p-4 p-md-5 mb-4">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="small mb-2" style="opacity:.85;">Selamat datang kembali</div>
                <h2 class="fw-bold mb-3">Kelola operasional, rekam medis, billing, dan laporan klinik dalam satu dashboard.</h2>
                <p class="mb-3" style="max-width: 760px; opacity:.92;">
                    Pantau kunjungan pasien, status pembayaran, serta perkembangan layanan klinik secara cepat dan terstruktur.
                </p>

                <div class="d-flex flex-wrap">
                    <div class="quick-chip"><i class="bi bi-people"></i> <?= number_format($totalPasien,0,',','.') ?> pasien</div>
                    <div class="quick-chip"><i class="bi bi-clipboard2-pulse"></i> <?= number_format($totalKunjungan,0,',','.') ?> kunjungan</div>
                    <div class="quick-chip"><i class="bi bi-receipt"></i> <?= number_format($totalInvoice,0,',','.') ?> invoice</div>
                </div>
            </div>
            <div class="col-lg-4 text-lg-end mt-4 mt-lg-0">
                <div class="fs-1"><i class="bi bi-heart-pulse-fill"></i></div>
            </div>
        </div>
    </div>

    <div class="section-title">Statistik Hari Ini</div>
    <div class="row g-3 mb-4">

        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-primary text-white">
                    <i class="bi bi-person-plus"></i>
                </div>
                <div class="stat-label">Pasien Hari Ini</div>
                <div class="stat-value"><?= number_format($pasienHariIni,0,',','.') ?></div>
                <div class="stat-sub">Jumlah pasien unik yang datang hari ini</div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-success text-white">
                    <i class="bi bi-clipboard-check"></i>
                </div>
                <div class="stat-label">Kunjungan Hari Ini</div>
                <div class="stat-value"><?= number_format($kunjunganHariIni,0,',','.') ?></div>
                <div class="stat-sub">Total kunjungan tercatat hari ini</div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-info text-white">
                    <i class="bi bi-cash-coin"></i>
                </div>
                <div class="stat-label">Pendapatan Lunas Hari Ini</div>
                <div class="stat-value" style="font-size:24px;">Rp <?= number_format($lunasHariIni,0,',','.') ?></div>
                <div class="stat-sub">Total invoice yang sudah lunas</div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="stat-card">
                <div class="stat-icon bg-warning text-dark">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="stat-label">Pending Hari Ini</div>
                <div class="stat-value" style="font-size:24px;">Rp <?= number_format($pendingHariIni,0,',','.') ?></div>
                <div class="stat-sub">Nilai invoice yang masih pending</div>
            </div>
        </div>

    </div>

    <div class="section-title">Menu Utama Klinik</div>
    <div class="row g-3 mb-4">

        <div class="col-md-3 col-6">
            <a href="pasien.php" class="menu-card">
                <div class="menu-icon bg-primary text-white">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="menu-title">Pasien</div>
                <div class="menu-desc">Data pasien, edit, hapus, dan history perawatan</div>
            </a>
        </div>

        <div class="col-md-3 col-6">
            <a href="kunjungan.php" class="menu-card">
                <div class="menu-icon bg-success text-white">
                    <i class="bi bi-journal-medical"></i>
                </div>
                <div class="menu-title">Kunjungan</div>
                <div class="menu-desc">SOAP, diagnosa, ICD-10, dan rekam medis</div>
            </a>
        </div>

        <div class="col-md-3 col-6">
            <a href="odontogram_level4.php" class="menu-card">
                <div class="menu-icon bg-warning text-dark">
                    <i class="bi bi-grid-3x3-gap-fill"></i>
                </div>
                <div class="menu-title">Odontogram PRO</div>
                <div class="menu-desc">Mapping surface gigi, warna kondisi, dan billing</div>
            </a>
        </div>

        <div class="col-md-3 col-6">
            <a href="invoice.php" class="menu-card">
                <div class="menu-icon bg-dark text-white">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
                <div class="menu-title">Invoice</div>
                <div class="menu-desc">Billing, edit item, status pembayaran, dan print</div>
            </a>
        </div>

        <div class="col-md-3 col-6">
            <a href="laporan_keuangan.php" class="menu-card">
                <div class="menu-icon bg-info text-white">
                    <i class="bi bi-bar-chart-line-fill"></i>
                </div>
                <div class="menu-title">Laporan Keuangan</div>
                <div class="menu-desc">Laporan bulanan lengkap dengan grafik</div>
            </a>
        </div>

        <div class="col-md-3 col-6">
            <a href="invoice_print.php?id=1" class="menu-card">
                <div class="menu-icon" style="background:#6f42c1;color:#fff;">
                    <i class="bi bi-printer-fill"></i>
                </div>
                <div class="menu-title">Print Invoice</div>
                <div class="menu-desc">Cetak invoice A4 dan struk pembayaran</div>
            </a>
        </div>

    </div>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="panel-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="section-title mb-0">Pasien Terbaru</div>
                        <div class="small-muted">5 pasien terakhir yang terdaftar</div>
                    </div>
                    <a href="pasien.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>No RM</th>
                                <th>Nama</th>
                                <th>No HP</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($pasienTerbaru && $pasienTerbaru->num_rows > 0): ?>
                            <?php while($p = $pasienTerbaru->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['no_rm']) ?></td>
                                    <td><?= htmlspecialchars($p['nama']) ?></td>
                                    <td><?= htmlspecialchars($p['no_hp'] ?: '-') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center small-muted">Belum ada data pasien.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="panel-card">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="section-title mb-0">Invoice Terbaru</div>
                        <div class="small-muted">Monitoring status pembayaran terbaru</div>
                    </div>
                    <a href="invoice.php" class="btn btn-sm btn-outline-dark">Lihat Semua</a>
                </div>

                <div class="table-responsive">
                    <table class="table table-modern align-middle mb-0">
                        <thead>
                            <tr>
                                <th>No Invoice</th>
                                <th>Pasien</th>
                                <th>Status</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($invoiceTerbaru && $invoiceTerbaru->num_rows > 0): ?>
                            <?php while($i = $invoiceTerbaru->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($i['nomor_invoice']) ?></td>
                                    <td><?= htmlspecialchars($i['nama']) ?></td>
                                    <td>
                                        <?php if ($i['status_bayar'] === 'lunas'): ?>
                                            <span class="badge badge-soft-success">Lunas</span>
                                        <?php elseif ($i['status_bayar'] === 'pending'): ?>
                                            <span class="badge badge-soft-warning">Pending</span>
                                        <?php elseif ($i['status_bayar'] === 'tidak_terbayar'): ?>
                                            <span class="badge badge-soft-danger">Tidak Terbayar</span>
                                        <?php else: ?>
                                            <span class="badge text-bg-secondary"><?= htmlspecialchars($i['status_bayar']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>Rp <?= number_format((float)$i['total'],0,',','.') ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center small-muted">Belum ada invoice.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon bg-warning text-dark">
                    <i class="bi bi-clock-history"></i>
                </div>
                <div class="stat-label">Invoice Pending</div>
                <div class="stat-value"><?= number_format($invoicePending,0,',','.') ?></div>
                <div class="stat-sub">Jumlah invoice yang belum diselesaikan</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon bg-danger text-white">
                    <i class="bi bi-x-octagon"></i>
                </div>
                <div class="stat-label">Tidak Terbayar</div>
                <div class="stat-value"><?= number_format($invoiceTidakTerbayar,0,',','.') ?></div>
                <div class="stat-sub">Invoice gagal / batal bayar</div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="stat-card">
                <div class="stat-icon" style="background:#6f42c1;color:#fff;">
                    <i class="bi bi-hospital"></i>
                </div>
                <div class="stat-label">Total Operasional</div>
                <div class="stat-value"><?= number_format($totalKunjungan,0,',','.') ?></div>
                <div class="stat-sub">Total seluruh kunjungan klinik</div>
            </div>
        </div>
    </div>

</div>
</body>
</html>