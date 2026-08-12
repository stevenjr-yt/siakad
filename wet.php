<?php
// Hash yang tersimpan di database
$hash = '$2y$10$FGLqzwxfsiZmfmG65wsbv.5zZUYnwErT6oYSJm0r5pe.pLkRCvvvq';

// Input plaintext dari user yang ingin diuji
$password_input = 'tebakan_password_disini'; 

// Memverifikasi apakah input menghasilkan hash yang sama
if (password_verify($password_input, $hash)) {
    echo "Login Berhasil: Password cocok!";
} else {
    echo "Login Gagal: Password salah!";
}
?>