<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $id = (int)($_POST['id'] ?? 0);
    $pasien_id = (int)($_POST['pasien_id'] ?? 0);
    $tanggal = trim($_POST['tanggal'] ?? '');
    $keluhan = trim($_POST['keluhan'] ?? '');
    $diagnosa = trim($_POST['diagnosa'] ?? '');
    $odontogram = trim($_POST['odontogram'] ?? '');
    $tindakan = trim($_POST['tindakan'] ?? '');
    $dokter = trim($_POST['dokter'] ?? '');
    $catatan = trim($_POST['catatan'] ?? '');

    if ($pasien_id <= 0 || $tanggal === '') {
        throw new Exception('Pasien dan tanggal kunjungan wajib diisi.');
    }

    $tanggal = str_replace('T', ' ', $tanggal) . ':00';

    if ($id > 0) {
        db_execute("UPDATE kunjungan SET pasien_id=?, tanggal=?, keluhan=?, diagnosa=?, odontogram=?, tindakan=?, dokter=?, catatan=? WHERE id=?", [
            $pasien_id, $tanggal, $keluhan, $diagnosa, $odontogram, $tindakan, $dokter, $catatan, $id
        ]);
        redirect_with_message('kunjungan.php?pasien_id=' . $pasien_id, 'Kunjungan berhasil diperbarui.');
    } else {
        db_execute("INSERT INTO kunjungan (pasien_id, tanggal, keluhan, diagnosa, odontogram, tindakan, dokter, catatan) VALUES (?,?,?,?,?,?,?,?)", [
            $pasien_id, $tanggal, $keluhan, $diagnosa, $odontogram, $tindakan, $dokter, $catatan
        ]);
        redirect_with_message('kunjungan.php?pasien_id=' . $pasien_id, 'Kunjungan berhasil ditambahkan.');
    }
} catch (Throwable $e) {
    redirect_with_message('kunjungan.php?pasien_id=' . (int)($_POST['pasien_id'] ?? 0), 'Gagal simpan kunjungan: ' . $e->getMessage(), 'danger');
}
