<?php
session_start();
include 'koneksi.php';

// Proteksi halaman, hanya level admin yang bisa masuk
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'kepsek', 'kurikulum'])) {
    header("Location: dashboard.php");
    exit();
}

$user_id_login = $_SESSION['user_id'];
$role_aktif = $_SESSION['role'];
$nama_user_login = $_SESSION['nama_lengkap'];
$foto_user_login = $_SESSION['foto_profil'] ?? 'default.png';

// AKSI APPROVE USER
if (isset($_GET['id_approve'])) {
    $id_u = (int)$_GET['id_approve'];
    $conn->query("UPDATE users SET status_akun='aktif' WHERE id=$id_u");
    header("Location: approve_user.php?msg=approved");
    exit;
}

// QUERY AMAN (Pakai SELECT * biar gak error misal kolom kurang)
$q_pending = $conn->query("SELECT * FROM users WHERE status_akun='pending' OR status_akun='Pending' ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Akun Baru - SIAKAD PENABUR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>tailwind.config = { theme: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } } } }</script>
    <style>.glass-header { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }</style>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-800" x-data="{ sidebarOpen: false }">

    <div class="fixed inset-0 z-40 bg-black/50 lg:hidden" x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity style="display: none;"></div>

    <?php include 'sidebar.php'; ?>

    <div class="min-h-screen flex flex-col transition-all duration-300" :class="sidebarOpen ? 'lg:ml-64' : 'ml-0'">
        
        <header class="sticky top-0 z-30 glass-header border-b border-gray-200 shadow-sm">
            <div class="flex items-center justify-between px-4 sm:px-8 py-4">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue transition p-2 rounded-lg bg-gray-100">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-xl font-extrabold text-penaburDark hidden sm:block">Verifikasi Pendaftaran</h2>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="hidden sm:flex flex-col items-end">
                        <span class="text-sm font-bold text-penaburDark"><?= htmlspecialchars($nama_user_login); ?></span>
                        <span class="text-[10px] uppercase tracking-wider text-gray-500 font-bold"><?= htmlspecialchars($role_aktif); ?></span>
                    </div>
                    <img src="assets/<?= htmlspecialchars($foto_user_login); ?>" onerror="this.src='https://via.placeholder.com/40'" class="w-10 h-10 rounded-full object-cover border-2 border-penaburGold shadow-sm bg-gray-100" alt="User">
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-8 max-w-7xl mx-auto w-full relative">
            
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-8">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 border-b pb-4 gap-4">
                    <div>
                        <h2 class="text-2xl font-black text-red-600"><i class="fa-solid fa-user-clock text-red-500 mr-2"></i> Menunggu Verifikasi Akun</h2>
                        <p class="text-xs text-gray-500 font-bold mt-1">Daftar pengguna yang baru mendaftar dan menunggu persetujuan Admin/Kepsek.</p>
                    </div>
                    <a href="dashboard.php" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl text-sm font-bold transition shadow-sm"><i class="fa-solid fa-arrow-left mr-2"></i> Kembali</a>
                </div>

                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'approved'): ?>
                    <div class='bg-green-100 text-green-700 p-4 rounded-xl mb-6 font-bold shadow-sm border border-green-200'>
                        <i class="fa-solid fa-circle-check mr-2"></i> Akun pengguna berhasil disetujui dan diaktifkan!
                    </div>
                <?php endif; ?>

                <?php if($q_pending && $q_pending->num_rows > 0): ?>
                    <div class="overflow-x-auto bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase font-black text-[10px] tracking-wider border-b">
                                <tr>
                                    <th class="p-4 w-12 text-center">No</th>
                                    <th class="p-4">Info Pendaftar</th>
                                    <th class="p-4">Username</th>
                                    <th class="p-4">Tipe Akun (Role)</th>
                                    <th class="p-4 text-center">Aksi Verifikasi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php $no=1; while($u = $q_pending->fetch_assoc()): ?>
                                <tr class="hover:bg-red-50/50 transition">
                                    <td class="p-4 text-center font-bold text-gray-400"><?= $no++; ?></td>
                                    <td class="p-4">
                                        <div class="flex items-center gap-3">
                                            <img src="assets/<?= htmlspecialchars($u['foto_profil'] ?? 'default.png') ?>" onerror="this.src='https://via.placeholder.com/40'" class="w-10 h-10 rounded-full object-cover border border-gray-200 shadow-sm bg-gray-100">
                                            <div>
                                                <p class="font-bold text-penaburDark"><?= htmlspecialchars($u['nama_lengkap']) ?></p>
                                                <p class="text-[10px] text-gray-400 font-bold"><?= htmlspecialchars($u['email'] ?? 'Tidak ada email') ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-4 font-mono text-xs text-gray-600 font-bold"><?= htmlspecialchars($u['username']) ?></td>
                                    <td class="p-4">
                                        <span class="px-3 py-1 bg-red-100 text-red-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-red-200 shadow-sm"><?= $u['role'] ?></span>
                                    </td>
                                    <td class="p-4 text-center">
                                        <a href="?id_approve=<?= $u['id'] ?>" onclick="return confirm('Setujui akun <?= htmlspecialchars($u['nama_lengkap']); ?>?')" class="inline-block bg-green-500 text-white px-5 py-2.5 rounded-xl shadow-md hover:bg-green-600 transition text-xs font-black whitespace-nowrap">
                                            <i class="fa-solid fa-check mr-1"></i> Setujui Akun
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-10 text-center bg-gray-50 border-2 border-dashed border-gray-200 rounded-2xl">
                        <i class="fa-solid fa-clipboard-check text-4xl text-gray-300 mb-3"></i>
                        <h3 class="text-lg font-black text-gray-400">Tidak ada pendaftar baru</h3>
                        <p class="text-sm font-bold text-gray-400 mt-1">Semua akun di dalam sistem sudah diverifikasi.</p>
                    </div>
                <?php endif; ?>

            </div>
        </main>

        <footer class="px-6 py-6 text-center text-[10px] text-gray-400 font-extrabold uppercase tracking-[0.2em] border-t border-gray-200 bg-white mt-auto">
            &copy; <?= date('Y'); ?> E-Learning SMKK BPK PENABUR. Made with ❤️ for Education.
        </footer>
    </div>
</body>
</html>