<?php
$conn = new mysqli('mariadb', 'root', 'root', 'siakad');
$res = $conn->query("SELECT * FROM mapel WHERE nama_mapel LIKE '%KKA%'");
while($row = $res->fetch_assoc()) print_r($row);
?>
