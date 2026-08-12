<?php
require "koneksi.php";
$sql = "ALTER TABLE users MODIFY COLUMN role ENUM('siswa','guru','kurikulum','kepsek','superadmin','alumni') NOT NULL DEFAULT 'siswa'";
$conn->query($sql);
if ($conn->error) {
    echo "Error: " . $conn->error;
} else {
    echo "OK";
}
?>
