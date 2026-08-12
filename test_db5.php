<?php
$conn = new mysqli('mariadb', 'root', 'root', 'siakad');
$res = $conn->query("SELECT * FROM penugasan WHERE id_guru = 1");
while($row = $res->fetch_assoc()) print_r($row);
?>
