<?php
session_start();
include 'koneksi.php';

// Tangkap slug dari URL (ini dikirim diam-diam oleh .htaccess)
if (isset($_GET['slug'])) {
    $slug = $_GET['slug'];
    
    // Siapkan query pakai prepared statement biar aman dari SQL Injection
    $stmt = $conn->prepare("SELECT * FROM pengumuman WHERE slug = ?");
    $stmt->bind_param("s", $slug);
    $stmt->execute();
    $result = $stmt->get_result();

    // Jika berita ada
    if ($result->num_rows > 0) {
        $berita = $result->fetch_assoc();
    } else {
        // Jika slug ngasal, tendang balik ke index
        header("Location: /index.php");
        exit();
    }
} else {
    // Jika akses tanpa slug
    header("Location: /index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $berita['judul']; ?> - SIAKAD PENABUR</title>
    
    <base href="/">
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } }
            }
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased flex flex-col min-h-screen">

    <nav class="bg-penaburDark shadow-md">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="index.php" class="flex items-center gap-2 text-white hover:text-penaburGold transition">
                    <i class="fa-solid fa-arrow-left"></i> <span class="font-bold text-sm">Kembali ke Beranda</span>
                </a>
                <div class="text-penaburGold font-bold tracking-widest text-xs uppercase">SIAKAD PENABUR</div>
            </div>
        </div>
    </nav>

    <main class="flex-grow py-12 px-4 sm:px-6">
        <div class="max-w-3xl mx-auto bg-white rounded-3xl shadow-sm border border-gray-200 overflow-hidden">
            
            <div class="p-8 sm:p-10 border-b border-gray-100 bg-gray-50/50">
                <div class="flex flex-wrap items-center gap-3 mb-4">
                    <span class="px-3 py-1 bg-blue-100 text-penaburBlue rounded-full text-xs font-bold uppercase tracking-wider">
                        Pengumuman
                    </span>
                    <span class="text-sm font-medium text-gray-500">
                        <i class="fa-regular fa-calendar mr-1"></i> <?= date('d F Y, H:i', strtotime($berita['tanggal'])); ?>
                    </span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-penaburDark leading-tight mb-4">
                    <?= $berita['judul']; ?>
                </h1>
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <div class="w-8 h-8 rounded-full bg-penaburDark flex items-center justify-center text-white"><i class="fa-solid fa-user-pen text-xs"></i></div>
                    Ditulis oleh <span class="font-bold text-penaburDark"><?= $berita['penulis']; ?></span>
                </div>
            </div>

            <div class="p-8 sm:p-10 prose prose-blue max-w-none text-gray-600 leading-relaxed">
                <?= nl2br(htmlspecialchars($berita['konten'])); ?>
            </div>

        </div>
    </main>

    <footer class="bg-white py-6 border-t border-gray-200">
        <div class="text-center text-xs text-gray-400 font-medium">
            &copy; <?= date('Y'); ?> E-Learning BPK PENABUR.
        </div>
    </footer>

</body>
</html>