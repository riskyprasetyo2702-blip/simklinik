<?php
require_once __DIR__ . '/bootstrap.php';

try {
    $id = (int)($_POST['id'] ?? 0);
    $no_rm = trim($_POST['no_rm'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $jk = ($_POST['jk'] ?? 'L') === 'P' ? 'P' : 'L';
    $tempat_lahir = trim($_POST['tempat_lahir'] ?? '');
    $tanggal_lahir = trim($_POST['tanggal_lahir'] ?? '');
    $telepon = trim($_POST['telepon'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $alergi = trim($_POST['alergi'] ?? '');

    if ($no_rm === '' || $nama === '') {
        throw new Exception('No RM dan nama pasien wajib diisi.');
    }

    $cek = db_fetch_one("SELECT id FROM pasien WHERE no_rm = ? AND id <> ?", [$no_rm, $id]);
    if ($cek) {
        throw new Exception('No RM sudah dipakai.');
    }

    if ($id > 0) {
        db_execute("UPDATE pasien SET no_rm=?, nik=?, nama=?, jk=?, tempat_lahir=?, tanggal_lahir=?, telepon=?, alamat=?, alergi=? WHERE id=?", [
            $no_rm, $nik, $nama, $jk, $tempat_lahir, $tanggal_lahir ?: null, $telepon, $alamat, $alergi, $id
        ]);
        redirect_with_message('pasien.php', 'Data pasien berhasil diperbarui.');
    } else {
        db_execute("INSERT INTO pasien (no_rm, nik, nama, jk, tempat_lahir, tanggal_lahir, telepon, alamat, alergi) VALUES (?,?,?,?,?,?,?,?,?)", [
            $no_rm, $nik, $nama, $jk, $tempat_lahir, $tanggal_lahir ?: null, $telepon, $alamat, $alergi
        ]);
        redirect_with_message('pasien.php', 'Data pasien berhasil ditambahkan.');
    }
} catch (Throwable $e) {
    redirect_with_message('pasien.php', 'Gagal simpan pasien: ' . $e->getMessage(), 'danger');
}
