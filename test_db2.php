<?php
$conn = new mysqli('mariadb', 'root', 'root', 'siakad');
$user_id = 1;
$jam_q = $conn->query("SELECT COUNT(j.id) as total_slot FROM jadwal_pelajaran j JOIN penugasan p ON j.id_mapel = p.id_mapel AND j.id_kelas = p.id_kelas WHERE p.id_guru = '$user_id'");
print_r($jam_q->fetch_assoc());
?>
