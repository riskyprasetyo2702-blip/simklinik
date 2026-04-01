<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

/* autoload dompdf: support composer atau manual */
$autoloadComposer = __DIR__ . '/vendor/autoload.php';
$autoloadManual   = __DIR__ . '/vendor/dompdf/autoload.inc.php';

if (file_exists($autoloadComposer)) {
    require_once $autoloadComposer;
} elseif (file_exists($autoloadManual)) {
    require_once $autoloadManual;
} else {
    die("Dompdf belum terpasang.");
}

use Dompdf\Dompdf;
use Dompdf\Options;

$conn = new mysqli("localhost", "root", "", "simklinik");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("ID invoice tidak valid");
}

$qInv = $conn->query("
    SELECT 
        i.*,
        p.nama,
        p.no_rm,
        p.no_hp,
        p.alamat,
        v.tanggal_kunjungan
    FROM invoices i
    JOIN visits v ON v.id = i.visit_id
    JOIN patients p ON p.id = v.patient_id
    WHERE i.id = $id
    LIMIT 1
");

if (!$qInv) {
    die("Query invoice gagal: " . $conn->error);
}

$inv = $qInv->fetch_assoc();
if (!$inv) {
    die("Invoice tidak ditemukan");
}

$items = $conn->query("
    SELECT 
        ii.*,
        COALESCE(ii.nama_tindakan, t.nama_tindakan) AS nama_tindakan_final
    FROM invoice_items ii
    LEFT JOIN treatments t ON t.id = ii.treatment_id
    WHERE ii.invoice_id = $id
    ORDER BY ii.id ASC
");

if (!$items) {
    die("Query invoice_items gagal: " . $conn->error);
}

$namaKlinik    = defined('NAMA_KLINIK') ? NAMA_KLINIK : 'TIGA DENTAL';
$taglineKlinik = defined('TAGLINE_KLINIK') ? TAGLINE_KLINIK : 'Dental Care & Aesthetic Clinic';
$alamatKlinik  = defined('ALAMAT_KLINIK') ? ALAMAT_KLINIK : 'Alamat klinik belum diatur';
$telpKlinik    = defined('TELP_KLINIK') ? TELP_KLINIK : '-';
$emailKlinik   = defined('EMAIL_KLINIK') ? EMAIL_KLINIK : '-';
$dokterKlinik  = defined('DOKTER_KLINIK') ? DOKTER_KLINIK : 'drg. Nama Dokter';
$sipDokter     = defined('SIP_DOKTER') ? SIP_DOKTER : '-';

$logoPath = '';
if (defined('LOGO_KLINIK') && LOGO_KLINIK !== '' && file_exists(LOGO_KLINIK)) {
    $realLogo = realpath(LOGO_KLINIK);
    if ($realLogo !== false) {
        $logoPath = 'file://' . $realLogo;
    }
}

$statusBayar = strtolower(trim($inv['status_bayar'] ?? 'pending'));
$statusBg = '#f59e0b';
$statusColor = '#ffffff';
if ($statusBayar === 'lunas' || $statusBayar === 'paid') {
    $statusBg = '#16a34a';
} elseif ($statusBayar === 'belum_bayar' || $statusBayar === 'unpaid') {
    $statusBg = '#dc2626';
}

$rowsHtml = '';
$no = 1;
$subtotalHitung = 0;

while ($item = $items->fetch_assoc()) {
    $namaTindakan = $item['nama_tindakan_final'] ?: 'Tindakan';
    $gigi = $item['tooth_number'] ?? '-';
    $surface = $item['surface_code'] ?? '-';
    $qty = (int)($item['qty'] ?? 0);
    $harga = (float)($item['harga'] ?? 0);
    $subtotal = (float)($item['subtotal'] ?? 0);
    $subtotalHitung += $subtotal;

    $rowsHtml .= '
        <tr>
            <td style="border:1px solid #d8e0ea;padding:8px;text-align:center;">' . $no++ . '</td>
            <td style="border:1px solid #d8e0ea;padding:8px;">' . htmlspecialchars($namaTindakan) . '</td>
            <td style="border:1px solid #d8e0ea;padding:8px;text-align:center;">' . htmlspecialchars($gigi) . '</td>
            <td style="border:1px solid #d8e0ea;padding:8px;text-align:center;">' . htmlspecialchars($surface) . '</td>
            <td style="border:1px solid #d8e0ea;padding:8px;text-align:center;">' . $qty . '</td>
            <td style="border:1px solid #d8e0ea;padding:8px;text-align:right;">Rp ' . number_format($harga, 0, ',', '.') . '</td>
            <td style="border:1px solid #d8e0ea;padding:8px;text-align:right;">Rp ' . number_format($subtotal, 0, ',', '.') . '</td>
        </tr>
    ';
}

$subtotalInvoice = (float)($inv['subtotal'] ?? $subtotalHitung);
if ($subtotalInvoice <= 0) {
    $subtotalInvoice = $subtotalHitung;
}
$diskon = (float)($inv['diskon'] ?? 0);
$totalAkhir = (float)($inv['total'] ?? ($subtotalInvoice - $diskon));
if ($totalAkhir < 0) {
    $totalAkhir = 0;
}

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice PDF Premium</title>
</head>
<body style="font-family: Helvetica, Arial, sans-serif; font-size:11px; color:#1f2937;">

    <table width="100%" style="border-bottom:3px solid #0f172a; padding-bottom:12px; margin-bottom:18px;">
        <tr>
            <td width="85" style="border:none;">
                <?php if ($logoPath): ?>
                    <img src="<?= $logoPath ?>" style="width:68px;height:68px;object-fit:contain;">
                <?php else: ?>
                    <div style="width:68px;height:68px;background:#0f172a;color:#fff;text-align:center;line-height:68px;font-size:24px;font-weight:bold;border-radius:14px;">TD</div>
                <?php endif; ?>
            </td>
            <td style="border:none;">
                <div style="font-size:22px;font-weight:bold;color:#0f172a;"><?= htmlspecialchars($namaKlinik) ?></div>
                <div style="font-size:11px;color:#475569; margin-top:2px;"><?= htmlspecialchars($taglineKlinik) ?></div>
                <div style="font-size:10px;color:#475569; margin-top:6px; line-height:1.5;">
                    <?= htmlspecialchars($alamatKlinik) ?><br>
                    Telp: <?= htmlspecialchars($telpKlinik) ?> | Email: <?= htmlspecialchars($emailKlinik) ?>
                </div>
            </td>
            <td width="180" style="border:none; text-align:right; vertical-align:top;">
                <div style="font-size:24px;font-weight:bold;color:#0f172a;">INVOICE</div>
                <div style="display:inline-block; margin-top:6px; padding:6px 12px; border-radius:999px; background:<?= $statusBg ?>; color:<?= $statusColor ?>; font-size:10px; font-weight:bold;">
                    <?= htmlspecialchars(strtoupper($statusBayar)) ?>
                </div>
            </td>
        </tr>
    </table>

    <table width="100%" style="margin-bottom:18px;">
        <tr>
            <td width="50%" style="vertical-align:top; border:none; padding-right:8px;">
                <div style="border:1px solid #dbe4ee; border-radius:14px; padding:14px; background:#f8fbff;">
                    <div style="font-size:12px;font-weight:bold;color:#0f172a; margin-bottom:8px;">Informasi Pasien</div>
                    <div style="font-size:10px;color:#64748b;">Nama Pasien</div>
                    <div style="font-size:11px;font-weight:bold;"><?= htmlspecialchars($inv['nama'] ?? '-') ?></div>

                    <div style="height:8px;"></div>
                    <div style="font-size:10px;color:#64748b;">No. Rekam Medis</div>
                    <div style="font-size:11px;font-weight:bold;"><?= htmlspecialchars($inv['no_rm'] ?? '-') ?></div>

                    <div style="height:8px;"></div>
                    <div style="font-size:10px;color:#64748b;">No. HP</div>
                    <div style="font-size:11px;font-weight:bold;"><?= htmlspecialchars($inv['no_hp'] ?? '-') ?></div>

                    <div style="height:8px;"></div>
                    <div style="font-size:10px;color:#64748b;">Alamat</div>
                    <div style="font-size:11px;font-weight:bold;"><?= htmlspecialchars($inv['alamat'] ?? '-') ?></div>
                </div>
            </td>
            <td width="50%" style="vertical-align:top; border:none; padding-left:8px;">
                <div style="border:1px solid #dbe4ee; border-radius:14px; padding:14px; background:#f8fbff;">
                    <div style="font-size:12px;font-weight:bold;color:#0f172a; margin-bottom:8px;">Informasi Invoice</div>
                    <div style="font-size:10px;color:#64748b;">Nomor Invoice</div>
                    <div style="font-size:11px;font-weight:bold;"><?= htmlspecialchars($inv['nomor_invoice'] ?? ('INV-' . $inv['id'])) ?></div>

                    <div style="height:8px;"></div>
                    <div style="font-size:10px;color:#64748b;">Tanggal Invoice</div>
                    <div style="font-size:11px;font-weight:bold;"><?= htmlspecialchars($inv['tanggal_invoice'] ?? '-') ?></div>

                    <div style="height:8px;"></div>
                    <div style="font-size:10px;color:#64748b;">Tanggal Kunjungan</div>
                    <div style="font-size:11px;font-weight:bold;"><?= htmlspecialchars($inv['tanggal_kunjungan'] ?? '-') ?></div>

                    <div style="height:8px;"></div>
                    <div style="font-size:10px;color:#64748b;">Metode Pembayaran</div>
                    <div style="font-size:11px;font-weight:bold;"><?= htmlspecialchars($inv['metode_bayar'] ?? '-') ?></div>
                </div>
            </td>
        </tr>
    </table>

    <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse; margin-top:8px;">
        <thead>
            <tr>
                <th style="background:#0f172a;color:#fff;border:1px solid #0f172a;padding:9px 8px;">No</th>
                <th style="background:#0f172a;color:#fff;border:1px solid #0f172a;padding:9px 8px;">Tindakan</th>
                <th style="background:#0f172a;color:#fff;border:1px solid #0f172a;padding:9px 8px;">Gigi</th>
                <th style="background:#0f172a;color:#fff;border:1px solid #0f172a;padding:9px 8px;">Surface</th>
                <th style="background:#0f172a;color:#fff;border:1px solid #0f172a;padding:9px 8px;">Qty</th>
                <th style="background:#0f172a;color:#fff;border:1px solid #0f172a;padding:9px 8px;">Harga</th>
                <th style="background:#0f172a;color:#fff;border:1px solid #0f172a;padding:9px 8px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?= $rowsHtml ?>
        </tbody>
    </table>

    <table width="100%" style="margin-top:18px;">
        <tr>
            <td style="border:none;"></td>
            <td width="280" style="border:none;">
                <div style="border:1px solid #dbe4ee; border-radius:14px; overflow:hidden;">
                    <div style="background:#0f172a;color:#fff;padding:10px 14px;font-size:11px;font-weight:bold;">Ringkasan Pembayaran</div>
                    <div style="padding:10px 14px;">
                        <table width="100%">
                            <tr>
                                <td style="border:none;font-size:10px;color:#475569;">Subtotal</td>
                                <td style="border:none;text-align:right;font-size:10px;font-weight:bold;">Rp <?= number_format($subtotalInvoice, 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td style="border:none;font-size:10px;color:#475569;">Diskon</td>
                                <td style="border:none;text-align:right;font-size:10px;font-weight:bold;">Rp <?= number_format($diskon, 0, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td colspan="2" style="border:none; height:8px;"></td>
                            </tr>
                            <tr>
                                <td style="border:none;border-top:1px dashed #cbd5e1;padding-top:8px;font-size:11px;font-weight:bold;color:#0f172a;">Total Akhir</td>
                                <td style="border:none;border-top:1px dashed #cbd5e1;padding-top:8px;text-align:right;font-size:14px;font-weight:bold;color:#0f172a;">Rp <?= number_format($totalAkhir, 0, ',', '.') ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-top:18px; border:1px solid #dbe4ee; border-radius:14px; padding:12px 14px;">
        <div style="font-size:11px;font-weight:bold; margin-bottom:8px; color:#0f172a;">Catatan Pembayaran</div>
        <div style="font-size:10px; color:#475569; line-height:1.6;">
            <?= htmlspecialchars($inv['catatan'] ?? 'Terima kasih atas kepercayaan Anda kepada klinik kami.') ?>
        </div>
    </div>

    <table width="100%" style="margin-top:26px;">
        <tr>
            <td style="border:none;"></td>
            <td width="250" style="border:none; text-align:center;">
                <div style="font-size:10px; color:#475569;">Hormat kami,</div>
                <div style="margin-top:42px; border-top:1px solid #94a3b8; padding-top:6px; font-size:10px; color:#334155;">
                    <strong><?= htmlspecialchars($dokterKlinik) ?></strong><br>
                    SIP: <?= htmlspecialchars($sipDokter) ?>
                </div>
            </td>
        </tr>
    </table>

    <div style="margin-top:26px; border-top:1px solid #dbe4ee; padding-top:10px; font-size:9px; color:#64748b; text-align:center;">
        Dokumen ini dibuat otomatis oleh sistem klinik. Simpan invoice ini sebagai bukti transaksi resmi.
    </div>

</body>
</html>
<?php
$html = ob_get_clean();

/* siapkan folder temp/font biar dompdf tidak crash */
$dompdfTmp = __DIR__ . '/tmp/dompdf';
$dompdfFont = __DIR__ . '/tmp/dompdf-fonts';

if (!is_dir($dompdfTmp)) {
    @mkdir($dompdfTmp, 0777, true);
}
if (!is_dir($dompdfFont)) {
    @mkdir($dompdfFont, 0777, true);
}

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');
$options->setTempDir($dompdfTmp);
$options->setFontDir($dompdfFont);
$options->setFontCache($dompdfFont);
$options->setChroot(__DIR__);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream("invoice-" . ($inv['nomor_invoice'] ?? $inv['id']) . ".pdf", ["Attachment" => false]);
exit;