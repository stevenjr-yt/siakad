<?php
session_start();
include 'koneksi.php';

// Injeksi kolom password_plain buat jaga-jaga kalau belum ada di DB
@$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS password_plain VARCHAR(255) DEFAULT NULL");

// Hanya level admin yang boleh melihat halaman ini
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'kepsek', 'kurikulum'])) {
    header("Location: dashboard.php");
    exit();
}

// Data user yang sedang login (Buat ditampilin di Header Navbar)
if (!isset($_SESSION['user_id'])) {
    $username_session = $_SESSION['username'];
    $get_id = $conn->query("SELECT id FROM users WHERE username = '$username_session'");
    $_SESSION['user_id'] = $get_id->fetch_assoc()['id'] ?? 0;
}
$user_id_login = $_SESSION['user_id'];
$role_aktif = $_SESSION['role'];
$nama_user_login = $_SESSION['nama_lengkap'];
$foto_user_login = $_SESSION['foto_profil'] ?? 'default.png';

$id_user = (int)$_GET['id'];

// =========================================================================
// PROSES UPDATE FULL PROFIL OLEH SUPERADMIN (Semua Kolom Bisa Diedit!)
// =========================================================================
if (isset($_POST['update_full_profil']) && $role_aktif == 'superadmin') {
    $e_nama = $conn->real_escape_string($_POST['e_nama']);
    $e_email = $conn->real_escape_string($_POST['e_email']);
    $e_role = $conn->real_escape_string($_POST['e_role']);
    $e_kelas = (int)$_POST['e_kelas'];
    $pass_baru = $conn->real_escape_string($_POST['password_baru']);
    
    $kelas_sql = ($e_kelas > 0) ? $e_kelas : "NULL";

    // Jika password diisi, update semua + password. Jika kosong, update tanpa password.
    if (!empty($pass_baru)) {
        $pass_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET nama_lengkap='$e_nama', email='$e_email', role='$e_role', id_kelas=$kelas_sql, password='$pass_hash', password_plain='$pass_baru' WHERE id=$id_user");
    } else {
        $conn->query("UPDATE users SET nama_lengkap='$e_nama', email='$e_email', role='$e_role', id_kelas=$kelas_sql WHERE id=$id_user");
    }
    
    $_SESSION['pesan'] = "<div class='bg-green-100 text-green-700 p-4 rounded-xl mb-6 font-bold text-sm border border-green-200 shadow-sm'><i class='fa-solid fa-check-circle mr-2'></i> Profil dan kredensial pengguna berhasil diperbarui!</div>";
    header("Location: detail_profil.php?id=$id_user");
    exit;
}

// Tarik Data User yang Sedang Dilihat
$q = $conn->query("SELECT u.*, k.nama_kelas FROM users u LEFT JOIN kelas k ON u.id_kelas=k.id WHERE u.id=$id_user");
if($q->num_rows == 0) die("User tidak ditemukan di sistem.");
$user_target = $q->fetch_assoc();

// List kelas untuk dropdown
$list_kelas_all = $conn->query("SELECT * FROM kelas ORDER BY nama_kelas ASC");
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detail Profil - SIAKAD PENABUR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>tailwind.config = { theme: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } } } }</script>
    <style>.glass-header { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }</style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased" x-data="{ sidebarOpen: false }">

    <div class="fixed inset-0 z-40 bg-black/50 lg:hidden" x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity style="display: none;"></div>

    <?php include 'sidebar.php'; ?>

    <div class="min-h-screen flex flex-col transition-all duration-300" :class="sidebarOpen ? 'lg:ml-64' : 'ml-0'">
        
        <header class="sticky top-0 z-30 glass-header border-b border-gray-200 shadow-sm">
            <div class="flex items-center justify-between px-4 sm:px-8 py-4">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue transition p-2 rounded-lg bg-gray-100">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-xl font-extrabold text-penaburDark hidden sm:block">Detail Pengguna</h2>
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

        <main class="flex-1 p-4 sm:p-8 max-w-7xl mx-auto w-full">
            
            <div class="flex items-center justify-between mb-6 border-b pb-4">
                <div>
                    <h2 class="text-2xl font-black text-penaburDark"><i class="fa-solid fa-id-badge text-penaburBlue mr-2"></i> Profil Informasi</h2>
                    <p class="text-sm text-gray-500 font-bold mt-1">Melihat & mengubah data spesifik milik pengguna.</p>
                </div>
                <a href="data_pengguna.php" class="px-5 py-2.5 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-xl text-sm font-bold transition shadow-sm"><i class="fa-solid fa-arrow-left mr-2"></i> Kembali</a>
            </div>

            <?php if (isset($_SESSION['pesan'])): ?>
                <?= $_SESSION['pesan']; unset($_SESSION['pesan']); ?>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 w-full">
                
                <div class="lg:col-span-1 space-y-6">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 text-center relative overflow-hidden">
                        <div class="absolute top-0 left-0 right-0 h-24 bg-gradient-to-b from-penaburBlue to-penaburDark"></div>
                        
                        <div class="relative inline-block mt-8 mb-4">
                            <img src="assets/<?= htmlspecialchars($user_target['foto_profil'] ?? 'default.png'); ?>" onerror="this.src='https://via.placeholder.com/128'" class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-xl mx-auto bg-gray-100">
                        </div>
                        
                        <h3 class="font-extrabold text-lg text-penaburDark"><?= htmlspecialchars($user_target['nama_lengkap']); ?></h3>
                        <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($user_target['username']); ?></p>
                        
                        <div class="flex justify-center gap-2 flex-wrap mb-2">
                            <span class="px-4 py-1.5 rounded-full bg-blue-50 text-penaburBlue text-[10px] font-extrabold uppercase tracking-widest border border-blue-100"><?= htmlspecialchars($user_target['role']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-2 w-full">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden w-full">
                        <div class="p-6 sm:p-8 w-full space-y-6">
                            
                            <h4 class="text-sm font-extrabold text-gray-400 uppercase tracking-widest mb-4 border-b pb-2">Informasi Lanjutan</h4>
                            
                            <?php if($role_aktif == 'superadmin'): ?>
                            <form method="POST" action="">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full bg-gray-50 p-6 rounded-2xl border border-gray-100 shadow-inner mb-6">
                                    <div class="w-full">
                                        <label class="block text-[10px] uppercase font-black text-gray-400 mb-1">Nama Lengkap</label>
                                        <input type="text" name="e_nama" value="<?= htmlspecialchars($user_target['nama_lengkap']) ?>" class="w-full p-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-penaburBlue text-sm font-bold bg-white outline-none" required>
                                    </div>
                                    <div class="w-full">
                                        <label class="block text-[10px] uppercase font-black text-gray-400 mb-1">Email Akun</label>
                                        <input type="email" name="e_email" value="<?= htmlspecialchars($user_target['email'] ?? '') ?>" class="w-full p-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-penaburBlue text-sm font-bold bg-white outline-none" required>
                                    </div>
                                    <div class="w-full">
                                        <label class="block text-[10px] uppercase font-black text-gray-400 mb-1">Role / Jabatan</label>
                                        <select name="e_role" class="w-full p-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-penaburBlue text-sm font-bold bg-white outline-none" required>
                                            <option value="siswa" <?= $user_target['role']=='siswa'?'selected':'' ?>>Siswa</option>
                                            <option value="guru" <?= $user_target['role']=='guru'?'selected':'' ?>>Guru</option>
                                            <option value="kurikulum" <?= $user_target['role']=='kurikulum'?'selected':'' ?>>Kurikulum</option>
                                            <option value="kepsek" <?= $user_target['role']=='kepsek'?'selected':'' ?>>Kepsek</option>
                                            <option value="superadmin" <?= $user_target['role']=='superadmin'?'selected':'' ?>>Superadmin</option>
                                        </select>
                                    </div>
                                    <div class="w-full">
                                        <label class="block text-[10px] uppercase font-black text-gray-400 mb-1">Kelas / Rombel (Khusus Siswa)</label>
                                        <select name="e_kelas" class="w-full p-3 rounded-xl border border-gray-200 focus:ring-2 focus:ring-penaburBlue text-sm font-bold bg-white outline-none">
                                            <option value="0">-- Tanpa Kelas --</option>
                                            <?php while($k = $list_kelas_all->fetch_assoc()): ?>
                                                <option value="<?= $k['id'] ?>" <?= $user_target['id_kelas']==$k['id']?'selected':'' ?>><?= htmlspecialchars($k['nama_kelas']) ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="pt-4">
                                    <h4 class="text-sm font-extrabold text-gray-400 uppercase tracking-widest mb-4 border-b pb-2">Keamanan Akun</h4>
                                    
                                    <div class="mb-6">
                                        <p class="text-[10px] uppercase font-black text-gray-400 mb-2">Password Saat Ini (Plain Text)</p>
                                        <div class="flex items-center gap-3 bg-yellow-50 border border-yellow-200 p-4 rounded-xl shadow-sm">
                                            <i class="fa-solid fa-unlock-keyhole text-yellow-600"></i>
                                            <span class="font-mono font-bold text-yellow-800 text-sm"><?= htmlspecialchars($user_target['password_plain'] ?? 'Belum diatur / Terenkripsi Lama') ?></span>
                                        </div>
                                    </div>

                                    <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100">
                                        <h4 class="text-[11px] font-black text-penaburBlue uppercase mb-4 tracking-widest"><i class="fa-solid fa-shield-halved mr-2"></i> Update Password (Opsional)</h4>
                                        <input type="text" name="password_baru" placeholder="Ketik password baru (Kosongkan jika tidak ingin mengubah)..." class="w-full px-4 py-3 rounded-xl border border-blue-200 outline-none focus:ring-2 focus:ring-penaburBlue text-sm font-bold bg-white mb-4">
                                        
                                        <div class="text-right border-t border-blue-200 pt-4 mt-2">
                                            <button type="submit" name="update_full_profil" onclick="return confirm('Anda yakin ingin menyimpan perubahan data pengguna ini?')" class="w-full sm:w-auto bg-penaburDark text-penaburGold px-8 py-3.5 rounded-xl font-black shadow-lg hover:bg-black transition uppercase text-xs tracking-widest whitespace-nowrap"><i class="fa-solid fa-save mr-2"></i> Simpan Perubahan Detail</button>
                                        </div>
                                    </div>
                                </div>
                            </form>

                            <?php else: ?>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full bg-gray-50 p-6 rounded-2xl border border-gray-100 shadow-inner">
                                    <div class="w-full">
                                        <p class="text-[10px] uppercase font-black text-gray-400 mb-1">Email Akun</p>
                                        <p class="font-bold text-gray-800 break-words"><?= htmlspecialchars($user_target['email'] ?? 'Belum diatur'); ?></p>
                                    </div>
                                    <div class="w-full">
                                        <p class="text-[10px] uppercase font-black text-gray-400 mb-1">Kelas / Rombel Aktif</p>
                                        <p class="font-bold text-gray-800"><?= htmlspecialchars($user_target['nama_kelas'] ?? 'Tidak terikat kelas manapun'); ?></p>
                                    </div>
                                </div>

                                <div class="pt-6">
                                    <h4 class="text-sm font-extrabold text-gray-400 uppercase tracking-widest mb-4 border-b pb-2">Keamanan Akun</h4>
                                    <div class="flex items-center gap-3 bg-red-50 text-red-500 p-4 rounded-xl border border-red-100 text-sm font-bold shadow-sm">
                                        <i class="fa-solid fa-lock text-lg"></i> 
                                        <span>Password disembunyikan untuk alasan privasi & keamanan tingkat tinggi. Hanya Superadmin yang memiliki akses.</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                    </div>
                </div>
            </div>

        </main>

        <footer class="px-6 py-6 text-center text-[10px] text-gray-400 font-extrabold uppercase tracking-[0.2em] border-t border-gray-200 bg-white mt-auto">
            &copy; <?= date('Y'); ?> E-Learning SMKK BPK PENABUR. Made with ❤️ for Education.
        </footer>
    </div>
</body>
</html>