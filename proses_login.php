<?php
session_start();
include 'koneksi.php';

// CEK LANGSUNG DATA INPUT (Bukan nama tombolnya) BIAR KEBAL REDIRECT
if (isset($_POST['username']) && isset($_POST['password'])) {
    
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    $query = "SELECT * FROM users WHERE username = '$username'";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        die("Error Database: " . mysqli_error($conn));
    }

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Verifikasi password (Bisa hash, bisa plain text)
        if (password_verify($password, $row['password']) || $password == $row['password']) {
            
            // Set session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['nama_lengkap'] = $row['nama_lengkap'];
            $_SESSION['id_kelas'] = $row['id_kelas'] ?? 0;
            $_SESSION['foto_profil'] = $row['foto_profil'] ?? 'default.png';

            // Redirect ke dashboard TANPA .php
            header("Location: dashboard");
            exit();
            
        } else {
            // Password Salah
            echo "<script>alert('Password yang Anda masukkan salah!'); window.location.href='/';</script>";
            exit();
        }
    } else {
        // Username Tidak Ada
        echo "<script>alert('Username tidak ditemukan di database!'); window.location.href='/';</script>";
        exit();
    }
} else {
    // Kalau benar-benar diredirect paksa sampai form inputnya hilang
    echo "<script>alert('SISTEM ERROR: Data POST hilang ditengah jalan akibat routing server CasaOS. Pastikan URL form action sama dengan URL file!'); window.location.href='/';</script>";
    exit();
}
?>