<?php
$conn = new mysqli('mariadb', 'root', 'root', 'siakad');
$user_id = 1;
$res = $conn->query("SELECT j.*, p.id_guru FROM jadwal_pelajaran j JOIN penugasan p ON j.id_mapel = p.id_mapel AND j.id_kelas = p.id_kelas WHERE p.id_guru = '$user_id'");
while($row = $res->fetch_assoc()) print_r($row);
?>
