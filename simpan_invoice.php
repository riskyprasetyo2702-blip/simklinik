<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $id = (int)($_POST['id'] ?? 0);
    $no_invoice = trim($_POST['no_invoice'] ?? '');
    $pasien_id = (int)($_POST['pasien_id'] ?? 0);
    $kunjungan_id = (int)($_POST['kunjungan_id'] ?? 0);
    $tanggal = trim($_POST['tanggal'] ?? '');
    $diskon = (float)($_POST['diskon'] ?? 0);
    $status_bayar = trim($_POST['status_bayar'] ?? 'belum terbayar');
    $metode_bayar = trim($_POST['metode_bayar'] ?? 'tunai');
    $catatan = trim($_POST['catatan'] ?? '');

    $nama_item = $_POST['nama_item'] ?? [];
    $qty = $_POST['qty'] ?? [];
    $harga = $_POST['harga'] ?? [];
    $keterangan_item = $_POST['keterangan_item'] ?? [];

    if ($no_invoice === '' || $pasien_id <= 0 || $tanggal === '') {
        throw new Exception('No invoice, pasien, dan tanggal wajib diisi.');
    }

    $cek = db_fetch_one("SELECT id FROM invoice WHERE no_invoice = ? AND id <> ?", [$no_invoice, $id]);
    if ($cek) {
        throw new Exception('No invoice sudah dipakai.');
    }

    $subtotal = 0;
    $items = [];
    foreach ($nama_item as $i => $nama) {
        $nama = trim($nama);
        if ($nama === '') continue;
        $q = (float)($qty[$i] ?? 1);
        $h = (float)($harga[$i] ?? 0);
        $sub = $q * $h;
        $subtotal += $sub;
        $items[] = [
            'nama_item' => $nama,
            'qty' => $q,
            'harga' => $h,
            'subtotal' => $sub,
            'keterangan' => trim($keterangan_item[$i] ?? '')
        ];
    }

    if (!$items) {
        throw new Exception('Minimal 1 item invoice harus diisi.');
    }

    $total = max(0, $subtotal - $diskon);
    $tanggal = str_replace('T', ' ', $tanggal) . ':00';
    $kunjungan_id = $kunjungan_id > 0 ? $kunjungan_id : null;

    if ($id > 0) {
        db_execute("UPDATE invoice SET no_invoice=?, pasien_id=?, kunjungan_id=?, tanggal=?, subtotal=?, diskon=?, total=?, status_bayar=?, metode_bayar=?, catatan=? WHERE id=?", [
            $no_invoice, $pasien_id, $kunjungan_id, $tanggal, $subtotal, $diskon, $total, $status_bayar, $metode_bayar, $catatan, $id
        ]);
        db_execute("DELETE FROM invoice_items WHERE invoice_id=?", [$id]);
        $invoiceId = $id;
    } else {
        db_execute("INSERT INTO invoice (no_invoice, pasien_id, kunjungan_id, tanggal, subtotal, diskon, total, status_bayar, metode_bayar, catatan) VALUES (?,?,?,?,?,?,?,?,?,?)", [
            $no_invoice, $pasien_id, $kunjungan_id, $tanggal, $subtotal, $diskon, $total, $status_bayar, $metode_bayar, $catatan
        ]);
        $invoiceId = db_last_id();
    }

    foreach ($items as $it) {
        db_execute("INSERT INTO invoice_items (invoice_id, nama_item, qty, harga, subtotal, keterangan) VALUES (?,?,?,?,?,?)", [
            $invoiceId, $it['nama_item'], $it['qty'], $it['harga'], $it['subtotal'], $it['keterangan']
        ]);
    }

    redirect_with_message('invoice.php?edit=' . $invoiceId, 'Invoice berhasil disimpan.');
} catch (Throwable $e) {
    redirect_with_message('invoice.php?pasien_id=' . (int)($_POST['pasien_id'] ?? 0), 'Gagal simpan invoice: ' . $e->getMessage(), 'danger');
}
