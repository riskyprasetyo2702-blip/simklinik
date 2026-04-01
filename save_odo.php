<?php
$conn = new mysqli("localhost","root","","simklinik");

$visit_id = $_POST['visit_id'];
$tooth = $_POST['tooth'];
$kondisi = $_POST['kondisi'];

$conn->query("INSERT INTO odontogram(visit_id,tooth_number,kondisi)
VALUES('$visit_id','$tooth','$kondisi')");
?>