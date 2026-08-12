<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role'])) {
    header("Location: /");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$nama_user = $_SESSION['nama_lengkap'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Semua Kelas - SIAKAD PENABUR</title>
    <base href="/">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>tailwind.config = { theme: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } } } }</script>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-800" x-data="{ sidebarOpen: false }">

    <div class="fixed inset-0 z-40 bg-black/50 lg:hidden" x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity style="display: none;"></div>
    <?php include 'sidebar.php'; ?>

    <div class="min-h-screen flex flex-col transition-all duration-300" :class="sidebarOpen ? 'lg:ml-64' : 'ml-0'">
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm">
            <div class="flex items-center justify-between px-4 sm:px-8 py-4">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue transition p-2 rounded-lg bg-gray-100"><i class="fa-solid fa-bars text-xl"></i></button>
                    <h2 class="text-xl font-extrabold text-penaburDark hidden sm:block">Daftar Semua Kelas</h2>
                </div>
                <a href="dashboard.php" class="px-4 py-2 bg-gray-100 rounded-lg text-sm font-bold text-gray-600 hover:bg-gray-200 transition">Kembali</a>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-8">
            <div class="max-w-7xl mx-auto w-full">
                <h3 class="text-2xl font-extrabold text-penaburDark mb-6 border-b pb-3"><i class="fa-solid fa-chalkboard text-penaburGold mr-2"></i> Seluruh Kelas Anda</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 w-full">
                    <?php
                    if ($role == 'siswa') {
                        $id_kelas_siswa = $_SESSION['id_kelas'] ?? 0;
                        $q_card = $conn->query("SELECT p.id, m.nama_mapel, k.nama_kelas, m.kode_mapel, u.nama_lengkap as pengajar FROM penugasan p JOIN mapel m ON p.id_mapel = m.id JOIN kelas k ON p.id_kelas = k.id JOIN users u ON p.id_guru = u.id WHERE p.id_kelas = '$id_kelas_siswa'");
                    } else {
                        $q_card = $conn->query("SELECT p.id, m.nama_mapel, k.nama_kelas, m.kode_mapel, u.nama_lengkap as pengajar FROM penugasan p JOIN mapel m ON p.id_mapel = m.id JOIN kelas k ON p.id_kelas = k.id JOIN users u ON p.id_guru = u.id WHERE p.id_guru = '$user_id'");
                    }

                    if ($q_card && $q_card->num_rows > 0) {
                        while ($card = $q_card->fetch_assoc()) {
                            ?>
                            <div class="bg-white rounded-3xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-lg transition w-full flex flex-col">
                                <div class="h-28 bg-gradient-to-r from-penaburDark to-penaburBlue p-5 relative">
                                    <h4 class="text-white font-extrabold text-lg line-clamp-2 z-10 relative leading-tight"><?= htmlspecialchars($card['nama_mapel']); ?></h4>
                                    <span class="bg-white/20 text-white px-2 py-1 rounded text-[10px] font-bold absolute bottom-4 right-4"><?= htmlspecialchars($card['kode_mapel']); ?></span>
                                </div>
                                <div class="p-5 flex flex-col flex-1 justify-between">
                                    <div class="mb-5">
                                        <p class="text-xs text-gray-500 uppercase font-bold mb-1">Kelas: <?= htmlspecialchars($card['nama_kelas']); ?></p>
                                        <p class="text-sm font-bold text-penaburDark"><i class="fa-solid fa-user-tie text-penaburGold mr-1"></i> <?= htmlspecialchars($card['pengajar']); ?></p>
                                    </div>
                                    <a href="view.php?id=<?= $card['id']; ?>" class="block w-full text-center py-3 bg-penaburBlue text-white font-bold rounded-xl shadow-md hover:bg-penaburDark transition mt-auto">Buka Modul</a>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo "<div class='col-span-full p-6 text-center bg-yellow-50 text-yellow-700 rounded-2xl border border-yellow-200 font-bold'>Tidak ada data kelas yang dapat ditampilkan.</div>";
                    }
                    ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>