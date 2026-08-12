<?php
$conn = new mysqli('mariadb', 'root', 'root', 'siakad');
$res = $conn->query("SELECT * FROM jadwal_pelajaran WHERE id_mapel = 69");
while($row = $res->fetch_assoc()) print_r($row);
?>
