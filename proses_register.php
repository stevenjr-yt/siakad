<?php
session_start();
include 'koneksi.php';

if (isset($_POST['register'])) {
    $nama = trim($_POST['nama_lengkap']);
    $user = trim($_POST['username']); 
    $email = trim($_POST['email']); 
    $role = $_POST['role'];
    // id_kelas hanya diambil jika pendaftar adalah siswa
    $id_kelas = ($role == 'siswa') ? $_POST['id_kelas'] : null;
    
    $pass_plain = $_POST['password']; 
    $pass_hash = password_hash($pass_plain, PASSWORD_DEFAULT); 

    // --- VALIDASI DOMAIN EMAIL ---
    $domain_guru = "@bandarlampung.bpkpenabur.or.id";
    $domain_siswa = "@bpkpenabur.sch.id";

    if ($role == 'guru' && !str_ends_with($email, $domain_guru)) {
        $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Gagal! Guru harus menggunakan email $domain_guru</div>";
        header("Location: index"); 
        exit();
    }

    if ($role == 'siswa' && !str_ends_with($email, $domain_siswa)) {
        $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Gagal! Siswa harus menggunakan email $domain_siswa</div>";
        header("Location: index"); 
        exit();
    }

    // Cek duplikasi username atau email
    $cek = $conn->prepare("SELECT username FROM users WHERE username = ? OR email = ?");
    $cek->bind_param("ss", $user, $email);
    $cek->execute();
    $cek->store_result();

    if ($cek->num_rows > 0) {
        $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Username atau Email sudah terdaftar!</div>";
    } else {
        // Query insert dengan id_kelas dan penambahan status pending
        $insert = $conn->prepare("INSERT INTO users (username, email, password, password_plain, nama_lengkap, role, id_kelas, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        $insert->bind_param("ssssssi", $user, $email, $pass_hash, $pass_plain, $nama, $role, $id_kelas);
        
        if ($insert->execute()) {
            $_SESSION['pesan'] = "<div class='bg-yellow-500/80 text-white p-3 rounded-xl mb-6 text-sm text-center border border-yellow-400 backdrop-blur-sm shadow-lg'>Registrasi berhasil! Akun Anda sedang menunggu persetujuan dari Admin.</div>";
        } else {
            $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Terjadi kesalahan sistem. Silakan coba lagi.</div>";
        }
    }
    header("Location: index"); 
    exit();
} else {
    header("Location: index");
    exit();
}
?>