<?php
require_once __DIR__ . '/bootstrap.php';
ensure_logged_in();
if($_SERVER['REQUEST_METHOD']!=='POST'){ header('Location: invoice.php'); exit; }
$id=(int)($_POST['id'] ?? 0); $pasien_id=(int)($_POST['pasien_id'] ?? 0); $kunjungan_id=(int)($_POST['kunjungan_id'] ?? 0); $no_invoice=trim($_POST['no_invoice'] ?? ''); $tanggal=trim($_POST['tanggal'] ?? date('Y-m-d H:i:s')); $status_bayar=trim($_POST['status_bayar'] ?? 'pending'); $metode_bayar=trim($_POST['metode_bayar'] ?? 'qris'); $subtotal=(float)($_POST['subtotal'] ?? 0); $diskon=(float)($_POST['diskon'] ?? 0); $total=(float)($_POST['total'] ?? 0); $catatan=trim($_POST['catatan'] ?? '');
$nama_item=$_POST['nama_item'] ?? []; $tindakan_id=$_POST['tindakan_id'] ?? []; $qty=$_POST['qty'] ?? []; $harga=$_POST['harga'] ?? []; $subtotal_item=$_POST['subtotal_item'] ?? []; $nomor_gigi=$_POST['nomor_gigi'] ?? []; $keterangan_item=$_POST['keterangan_item'] ?? [];
if($pasien_id<=0 || $no_invoice===''){ $_SESSION['error']='Pasien dan nomor invoice wajib diisi.'; header('Location: invoice.php'.($id?'?edit='.$id:'')); exit; }
$dup = $id>0 ? db_fetch_one("SELECT id FROM invoice WHERE no_invoice=? AND id<>?",[$no_invoice,$id]) : db_fetch_one("SELECT id FROM invoice WHERE no_invoice=?",[$no_invoice]);
if($dup){ $_SESSION['error']='Nomor invoice sudah digunakan.'; header('Location: invoice.php'.($id?'?edit='.$id:'')); exit; }
if($id>0){
  $ok=db_run("UPDATE invoice SET pasien_id=?, kunjungan_id=?, no_invoice=?, tanggal=?, subtotal=?, diskon=?, total=?, status_bayar=?, metode_bayar=?, catatan=? WHERE id=?",[$pasien_id,$kunjungan_id?:null,$no_invoice,$tanggal,$subtotal,$diskon,$total,$status_bayar,$metode_bayar,$catatan,$id]);
  db_run("DELETE FROM invoice_items WHERE invoice_id=?",[$id]);
  $invoiceId=$id;
}else{
  $invoiceId=db_insert("INSERT INTO invoice (pasien_id, kunjungan_id, no_invoice, tanggal, subtotal, diskon, total, status_bayar, metode_bayar, catatan) VALUES (?,?,?,?,?,?,?,?,?,?)",[$pasien_id,$kunjungan_id?:null,$no_invoice,$tanggal,$subtotal,$diskon,$total,$status_bayar,$metode_bayar,$catatan]);
  $ok=(bool)$invoiceId;
}
if($ok){
  $n=max(count($nama_item),count($qty),count($harga));
  for($i=0;$i<$n;$i++){
    $nm=trim($nama_item[$i] ?? ''); if($nm==='') continue;
    $tid=(int)($tindakan_id[$i] ?? 0); $q=(float)($qty[$i] ?? 1); $h=(float)($harga[$i] ?? 0); $st=(float)($subtotal_item[$i] ?? ($q*$h)); $g=trim($nomor_gigi[$i] ?? ''); $ket=trim($keterangan_item[$i] ?? '');
    db_insert("INSERT INTO invoice_items (invoice_id, tindakan_id, nama_item, qty, harga, subtotal, nomor_gigi, keterangan) VALUES (?,?,?,?,?,?,?,?)",[$invoiceId,$tid?:null,$nm,$q,$h,$st,$g,$ket]);
  }
  $sum=db_fetch_one("SELECT COALESCE(SUM(subtotal),0) subtotal FROM invoice_items WHERE invoice_id=?",[$invoiceId]);
  $subtotal=(float)($sum['subtotal'] ?? 0); $total=max(0,$subtotal-$diskon);
  db_run("UPDATE invoice SET subtotal=?, total=? WHERE id=?",[$subtotal,$total,$invoiceId]);
  sync_invoice_finance($invoiceId);
  $_SESSION['success']='Invoice berhasil disimpan.';
  header('Location: invoice.php?edit='.$invoiceId); exit;
}
$_SESSION['error']='Gagal menyimpan invoice.'; header('Location: invoice.php'.($id?'?edit='.$id:'')); exit;