<?php
session_start();
// Paksa PHP menampilkan error jika terjadi blank/mati di tengah jalan
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../koneksi.php';

// Deteksi otomatis nama variabel koneksi dari file ../koneksi.php Anda
$koneksi_db = isset($conn) ? $conn : (isset($koneksi) ? $koneksi : null);

if (!$koneksi_db) {
    die("FATAL ERROR: Variabel koneksi ke database tidak ditemukan! Pastikan file ../koneksi.php menggunakan variabel \$conn atau \$koneksi.");
}

$error = '';

// Kalau sudah login, langsung lempar ke nilai.php
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (!headers_sent()) {
        header("Location: nilai.php");
    }
    echo "<script>window.location.href = 'nilai.php';</script>";
    exit();
}

// Proses Login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $stmt = $koneksi_db->prepare("SELECT id, password, password_plain, nama_lengkap, role FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                
                // Cek password menggunakan password_verify ATAU mencocokkan langsung dengan password_plain agar pasti tembus
                if (password_verify($password, $user['password']) || $password === $user['password_plain']) {
                    $_SESSION['id_user'] = $user['id'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['logged_in'] = true;
                    
                    // Jika sukses, lempar ke halaman nilai (menggunakan proteksi ganda)
                    if (!headers_sent()) {
                        header("Location: nilai.php");
                    }
                    echo "<script>window.location.href='nilai.php';</script>";
                    exit();
                } else {
                    $error = "Password yang Anda masukkan salah!";
                }
            } else {
                $error = "Username tidak terdaftar di sistem!";
            }
        } else {
            $error = "Query Database Error: " . $koneksi_db->error;
        }
    } catch (Throwable $e) {
        // Tangkap jika file koneksi menggunakan format PDO atau ada error fungsi
        $error = "Terjadi Kesalahan Sistem Server: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Portal Pengumuman KKA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100 text-blue-600 mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">Portal Pengumuman</h1>
            <p class="text-sm text-gray-500 mt-1">Koding dan Kecerdasan Artifisial (KKA)</p>
        </div>

        <?php if ($error != ''): ?>
            <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-md mb-6" role="alert">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <span class="font-medium text-sm"><?php echo $error; ?></span>
                </div>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2" for="username">
                    Username / NISN
                </label>
                <input class="w-full px-4 py-3 rounded-lg bg-gray-50 border border-gray-200 text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" id="username" name="username" type="text" placeholder="Masukkan Username Anda" required>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2" for="password">
                    Password
                </label>
                <input class="w-full px-4 py-3 rounded-lg bg-gray-50 border border-gray-200 text-gray-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" id="password" name="password" type="password" placeholder="••••••••" required>
            </div>
            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg focus:outline-none focus:ring-4 focus:ring-blue-300 transition-colors shadow-lg" type="submit">
                Masuk ke Dashboard
            </button>
        </form>
    </div>

</body>
</html>