<?php
$conn = new mysqli("localhost","root","","simklinik");

if(isset($_POST['simpan'])){
    $conn->query("INSERT INTO visits(patient_id,tanggal,keluhan,diagnosa)
    VALUES('1',NOW(),'$_POST[keluhan]','$_POST[diagnosa]')");
}
?>

<h2>Rekam Medis</h2>

<form method="POST">
<textarea name="keluhan" placeholder="Keluhan"></textarea><br>
<textarea name="diagnosa" placeholder="Diagnosa"></textarea><br>
<button name="simpan">Simpan</button>
</form>