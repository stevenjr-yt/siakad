<?php
$conn = new mysqli('mariadb', 'root', 'root', 'siakad');
if ($conn->connect_error) die("Koneksi gagal: " . $conn->connect_error);

echo "jadwal_pelajaran:\n";
$res = $conn->query("DESCRIBE jadwal_pelajaran");
if($res) while($row = $res->fetch_assoc()) print_r($row);

echo "\npenugasan:\n";
$res = $conn->query("DESCRIBE penugasan");
if($res) while($row = $res->fetch_assoc()) print_r($row);

echo "\njadwal_pelajaran DATA:\n";
$res = $conn->query("SELECT * FROM jadwal_pelajaran LIMIT 10");
if($res) while($row = $res->fetch_assoc()) print_r($row);

echo "\npenugasan DATA:\n";
$res = $conn->query("SELECT * FROM penugasan LIMIT 10");
if($res) while($row = $res->fetch_assoc()) print_r($row);
?>
