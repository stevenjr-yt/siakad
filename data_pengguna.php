<?php
session_start();
include 'koneksi.php';

// Proteksi halaman, hanya level admin yang bisa masuk
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'kepsek', 'kurikulum'])) {
    header("Location: dashboard.php");
    exit();
}

// Pastikan user_id didapat dari session
if (!isset($_SESSION['user_id'])) {
    $username_session = $_SESSION['username'];
    $get_id = $conn->query("SELECT id FROM users WHERE username = '$username_session'");
    $_SESSION['user_id'] = $get_id->fetch_assoc()['id'] ?? 0;
}

$user_id_login = $_SESSION['user_id'];
$role_aktif = $_SESSION['role'];
$nama_user_login = $_SESSION['nama_lengkap'];
$foto_user_login = $_SESSION['foto_profil'] ?? 'default.png';

$filter_kelas = isset($_GET['kelas']) ? (int)$_GET['kelas'] : 0;

// Fitur Naik Kelas (Promote) - Berlaku untuk Siswa
if (isset($_POST['naik_kelas']) && isset($_POST['user_ids']) && isset($_POST['tujuan_kelas'])) {
    $tujuan = (int)$_POST['tujuan_kelas'];
    foreach($_POST['user_ids'] as $uid) {
        $uid = (int)$uid;
        // Amankan agar yang dipindah kelasnya HANYA role siswa
        $conn->query("UPDATE users SET id_kelas = $tujuan WHERE id = $uid AND role = 'siswa'");
    }
    header("Location: data_pengguna.php?msg=naik_kelas&kelas=$filter_kelas");
    exit;
}

// Fitur Lulus - Berlaku untuk Siswa
if (isset($_POST['lulus_siswa']) && isset($_POST['user_ids'])) {
    foreach($_POST['user_ids'] as $uid) {
        $uid = (int)$uid;
        // Mengubah role menjadi alumni dan menghapus dari kelas (id_kelas = NULL) agar tidak masuk kelas manapun
        $conn->query("UPDATE users SET role = 'alumni', id_kelas = NULL WHERE id = $uid AND role = 'siswa'");
    }
    header("Location: data_pengguna.php?msg=lulus&kelas=$filter_kelas");
    exit;
}

// Ambil daftar kelas untuk dropdown filter & naik kelas
$sql_kelas = "SELECT * FROM kelas ORDER BY nama_kelas ASC";
$list_kelas = $conn->query($sql_kelas);

// Tampilkan SEMUA pengguna (Superadmin bisa lihat semua data)
$sql_user = "SELECT u.*, k.nama_kelas FROM users u LEFT JOIN kelas k ON u.id_kelas = k.id WHERE 1=1";
if ($filter_kelas > 0) {
    $sql_user .= " AND u.id_kelas = $filter_kelas";
}
// Urutkan berdasarkan jabatan lalu nama (menambahkan alumni di akhir agar tetap berurutan)
$sql_user .= " ORDER BY FIELD(u.role, 'superadmin', 'kepsek', 'kurikulum', 'guru', 'siswa', 'alumni'), u.nama_lengkap ASC";
$users = $conn->query($sql_user);
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Data Pengguna - SIAKAD PENABUR</title>
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
                    <h2 class="text-xl font-extrabold text-penaburDark hidden sm:block">Manajemen Pengguna</h2>
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
                        <h2 class="text-2xl font-black text-penaburDark"><i class="fa-solid fa-users text-penaburBlue mr-2"></i> Data Pengguna Sistem</h2>
                        <p class="text-xs text-gray-500 font-bold mt-1">Kelola data siswa, guru, dan staff sekolah BPK PENABUR.</p>
                    </div>
                    <a href="dashboard.php" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl text-sm font-bold transition shadow-sm"><i class="fa-solid fa-arrow-left mr-2"></i> Kembali</a>
                </div>

                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'naik_kelas'): ?>
                    <div class='bg-green-100 text-green-700 p-4 rounded-xl mb-6 font-bold shadow-sm border border-green-200'>
                        <i class="fa-solid fa-circle-check mr-2"></i> Siswa terpilih berhasil dipindahkan/dinaikkan kelasnya!
                    </div>
                <?php endif; ?>

                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'lulus'): ?>
                    <div class='bg-blue-100 text-blue-800 p-4 rounded-xl mb-6 font-bold shadow-sm border border-blue-200'>
                        <i class="fa-solid fa-graduation-cap mr-2"></i> Siswa terpilih berhasil diluluskan, dikeluarkan dari kelas, dan statusnya menjadi Alumni!
                    </div>
                <?php endif; ?>

                <form method="GET" class="flex flex-col sm:flex-row items-center gap-4 mb-6 bg-gray-50 p-4 rounded-2xl border border-gray-100">
                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center shadow-sm border text-gray-400"><i class="fa-solid fa-filter"></i></div>
                        <span class="font-bold text-sm text-gray-600 hidden sm:block">Filter Kelas:</span>
                    </div>
                    <select name="kelas" class="p-3 border border-gray-200 rounded-xl w-full sm:w-64 font-bold text-sm outline-none focus:ring-2 focus:ring-penaburBlue shadow-inner bg-white" onchange="this.form.submit()">
                        <option value="0">Tampilkan Semua Kelas</option>
                        <?php while($k = $list_kelas->fetch_assoc()): ?>
                            <option value="<?= $k['id'] ?>" <?= $filter_kelas==$k['id']?'selected':'' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </form>

                <form method="POST">
                    <div class="flex flex-col xl:flex-row items-center gap-4 bg-gradient-to-r from-penaburDark to-penaburBlue p-6 rounded-2xl border border-blue-900 shadow-md mb-6">
                        <div class="flex-1 text-center xl:text-left w-full">
                            <h4 class="font-black text-white text-sm"><i class="fa-solid fa-arrow-up-right-dots text-penaburGold mr-2"></i> Pindah / Naik Kelas & Kelulusan</h4>
                            <p class="text-[10px] text-blue-200 font-bold mt-1">Centang siswa di tabel bawah ini, lalu pilih kelas tujuan atau proses kelulusan.</p>
                        </div>
                        <div class="flex w-full xl:w-auto gap-3 flex-col sm:flex-row">
                            <?php $list_kelas->data_seek(0); ?>
                            <select name="tujuan_kelas" class="p-3 border-0 rounded-xl font-bold text-sm outline-none w-full sm:w-56 shadow-inner bg-white text-penaburDark" required>
                                <option value="">-- Pilih Kelas Tujuan --</option>
                                <?php while($k = $list_kelas->fetch_assoc()): ?>
                                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" name="naik_kelas" onclick="return confirm('Yakin ingin memindahkan siswa yang dicentang ke kelas baru?')" class="bg-penaburGold text-penaburDark px-6 py-3 rounded-xl font-black shadow-lg hover:bg-yellow-400 transition shrink-0"><i class="fa-solid fa-check-double mr-2"></i> Pindahkan</button>
                            <button type="submit" name="lulus_siswa" formnovalidate onclick="return confirm('Yakin ingin meluluskan siswa yang dicentang? Mereka tidak akan masuk di kelas mana pun lagi.')" class="bg-emerald-500 text-white px-6 py-3 rounded-xl font-black shadow-lg hover:bg-emerald-600 transition shrink-0"><i class="fa-solid fa-graduation-cap mr-2"></i> Luluskan</button>
                        </div>
                    </div>

                    <div class="overflow-x-auto bg-white border border-gray-200 rounded-2xl shadow-sm">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-gray-500 uppercase font-black text-[10px] tracking-wider border-b">
                                <tr>
                                    <th class="p-4 w-12 text-center"><input type="checkbox" onchange="document.querySelectorAll('.chk-user').forEach(e=>e.checked=this.checked)" class="w-4 h-4 text-penaburBlue rounded focus:ring-penaburBlue"></th>
                                    <th class="p-4">Info Pengguna</th>
                                    <th class="p-4">Username</th>
                                    <th class="p-4">Role & Kelas</th>
                                    <th class="p-4 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php while($u = $users->fetch_assoc()): ?>
                                <tr class="hover:bg-blue-50/50 transition">
                                    <td class="p-4 text-center">
                                        <?php if($u['role'] == 'siswa'): ?>
                                            <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" class="chk-user w-4 h-4 text-penaburBlue rounded focus:ring-penaburBlue">
                                        <?php endif; ?>
                                    </td>
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
                                        <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-[10px] font-black uppercase tracking-widest mr-2 inline-block mb-1 sm:mb-0 shadow-sm border border-gray-200"><?= $u['role'] ?></span>
                                        <?php if($u['role'] == 'siswa' || !empty($u['nama_kelas'])): ?>
                                            <span class="px-3 py-1 bg-blue-50 text-penaburBlue border border-blue-100 rounded-full text-[10px] font-black uppercase tracking-widest inline-block shadow-sm"><?= htmlspecialchars($u['nama_kelas'] ?? 'Tanpa Kelas') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-center">
                                        <?php if($role_aktif == 'superadmin'): ?>
                                            <a href="detail_profil.php?id=<?= $u['id'] ?>" class="inline-block bg-yellow-400 text-penaburDark px-4 py-2 rounded-xl shadow hover:bg-yellow-500 transition text-xs font-black whitespace-nowrap"><i class="fa-solid fa-user-gear mr-1"></i> Edit & Detail</a>
                                        <?php else: ?>
                                            <a href="detail_profil.php?id=<?= $u['id'] ?>" class="inline-block bg-penaburBlue text-white px-4 py-2 rounded-xl shadow hover:bg-penaburDark transition text-xs font-black whitespace-nowrap"><i class="fa-solid fa-eye mr-1"></i> Lihat Detail</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </form>

            </div>
        </main>

        <footer class="px-6 py-6 text-center text-[10px] text-gray-400 font-extrabold uppercase tracking-[0.2em] border-t border-gray-200 bg-white mt-auto">
            &copy; <?= date('Y'); ?> E-Learning SMKK BPK PENABUR. Made with ❤️ for Education.
        </footer>
    </div>
</body>
</html>
