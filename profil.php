<?php
session_start();
include 'koneksi.php'; // Panggil koneksi database

// Cek apakah user sudah login
if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

// Pastikan user_id didapat dari session (atau cari dari DB jika cuma nyimpen username)
if (!isset($_SESSION['user_id'])) {
    $username_session = $_SESSION['username'];
    $get_id = $conn->query("SELECT id FROM users WHERE username = '$username_session'");
    $_SESSION['user_id'] = $get_id->fetch_assoc()['id'] ?? 0;
}
$user_id = $_SESSION['user_id'];
$role_user = $_SESSION['role'];

// Ambil data penugasan (Mapel yang diampu) untuk profil guru/staff
$penugasan_q = $conn->query("SELECT m.nama_mapel, k.nama_kelas FROM penugasan p JOIN mapel m ON p.id_mapel = m.id JOIN kelas k ON p.id_kelas = k.id WHERE p.id_guru = $user_id ORDER BY m.nama_mapel ASC");
$is_teaching = ($penugasan_q && $penugasan_q->num_rows > 0);

// ==========================================
// LOGIKA PROSES UPDATE PROFIL
// ==========================================
if (isset($_POST['update_profil'])) {
    $nama = trim($_POST['nama_lengkap']);
    $email = trim($_POST['email']);
    $pass_baru = $_POST['password_baru'];
    $konfirmasi = $_POST['konfirmasi_password'];
    
    $valid = true;
    $domain_guru = "@bandarlampung.bpkpenabur.or.id";
    $domain_siswa = "@bpkpenabur.sch.id";

    // 1. Validasi Domain Email sesuai Role
    if (in_array($role_user, ['guru', 'kurikulum', 'kepsek', 'superadmin']) && !str_ends_with($email, $domain_guru)) {
        $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-2xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Gagal! Staff/Guru harus menggunakan email $domain_guru</div>";
        $valid = false;
    } elseif ($role_user == 'siswa' && !str_ends_with($email, $domain_siswa)) {
        $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-2xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Gagal! Siswa harus menggunakan email $domain_siswa</div>";
        $valid = false;
    }

    // 2. Cek apakah Email sudah dipakai user lain
    if ($valid) {
        $cek_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $cek_email->bind_param("si", $email, $user_id);
        $cek_email->execute();
        $cek_email->store_result();
        if ($cek_email->num_rows > 0) {
            $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-2xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Email sudah digunakan akun lain!</div>";
            $valid = false;
        }
    }

    // 3. Validasi Password
    $pass_hash = "";
    $update_pass_query = "";
    if ($valid && !empty($pass_baru)) {
        if ($pass_baru !== $konfirmasi) {
            $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-2xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Konfirmasi password tidak cocok!</div>";
            $valid = false;
        } else {
            $pass_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
            $update_pass_query = ", password=?, password_plain=?";
        }
    }

    // 4. Proses Upload Foto (Limit Maksimal 1 MB)
    $foto_name = $_SESSION['foto_profil'] ?? 'default.png'; 
    if ($valid && isset($_FILES['foto_profil']['name']) && $_FILES['foto_profil']['name'] != '') {
        $file_name = $_FILES['foto_profil']['name'];
        $file_tmp = $_FILES['foto_profil']['tmp_name'];
        $file_size = $_FILES['foto_profil']['size'];
        
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        
        if (in_array($ext, $allowed)) {
            if ($file_size <= 1048576) { 
                $new_name = 'profil_' . $user_id . '_' . time() . '.' . $ext;
                $dest = 'assets/' . $new_name;
                
                if (move_uploaded_file($file_tmp, $dest)) {
                    $foto_name = $new_name;
                } else {
                    $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-2xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Gagal mengunggah foto.</div>";
                    $valid = false;
                }
            } else {
                $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-2xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Ukuran foto maksimal 1MB.</div>";
                $valid = false;
            }
        } else {
            $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-2xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Format foto harus JPG/PNG.</div>";
            $valid = false;
        }
    }

    // 5. Eksekusi Update ke Database
    if ($valid) {
        if (!empty($pass_baru)) {
            $stmt = $conn->prepare("UPDATE users SET nama_lengkap=?, email=?, foto_profil=? $update_pass_query WHERE id=?");
            $stmt->bind_param("ssssi", $nama, $email, $foto_name, $pass_hash, $pass_baru, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET nama_lengkap=?, email=?, foto_profil=? WHERE id=?");
            $stmt->bind_param("sssi", $nama, $email, $foto_name, $user_id);
        }

        if ($stmt->execute()) {
            $_SESSION['nama_lengkap'] = $nama;
            $_SESSION['email'] = $email;
            $_SESSION['foto_profil'] = $foto_name;
            $_SESSION['pesan'] = "<div class='bg-green-500/80 text-white p-3 rounded-2xl mb-6 text-sm text-center border border-green-400 backdrop-blur-sm shadow-lg'>Profil berhasil diperbarui! 🎉</div>";
        } else {
            $_SESSION['pesan'] = "<div class='bg-red-500/80 text-white p-3 rounded-2xl mb-6 text-sm text-center border border-red-400 backdrop-blur-sm shadow-md'>Terjadi kesalahan database.</div>";
        }
    }
    header("Location: profil.php");
    exit();
}

$nama_user = $_SESSION['nama_lengkap'];
$username_user = $_SESSION['username'];
$email_user = $_SESSION['email'] ?? '';
$foto_user = $_SESSION['foto_profil'] ?? 'default.png';
?>

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profil Saya - SIAKAD PENABUR</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <script>
        tailwind.config = {
            theme: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } }
            }
        }
    </script>
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
                    <h2 class="text-xl font-extrabold text-penaburDark hidden sm:block">Profil Pengguna</h2>
                </div>
                
                <div class="flex items-center gap-4">
                    <div class="hidden sm:flex flex-col items-end">
                        <span class="text-sm font-bold text-penaburDark"><?= htmlspecialchars($nama_user); ?></span>
                        <span class="text-[10px] uppercase tracking-wider text-gray-500 font-bold"><?= htmlspecialchars($role_user); ?></span>
                    </div>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-2 p-1 rounded-xl hover:bg-gray-100 transition focus:ring-2 focus:ring-penaburGold">
                            <img src="assets/<?= htmlspecialchars($foto_user); ?>" onerror="this.src='https://via.placeholder.com/40'" class="w-10 h-10 rounded-full object-cover border-2 border-penaburGold shadow-sm bg-gray-100" alt="User">
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400 mr-2"></i>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-3 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-50 bg-gray-50/50">
                                <p class="text-sm font-bold text-penaburDark truncate"><?= htmlspecialchars($nama_user); ?></p>
                                <p class="text-xs text-gray-500 truncate"><?= $_SESSION['username']; ?></p>
                            </div>
                            <a href="logout.php" class="block px-4 py-2.5 text-sm font-bold text-red-500 hover:bg-red-50 transition"><i class="fa-solid fa-arrow-right-from-bracket mr-2"></i> Keluar Sistem</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-8 max-w-7xl mx-auto w-full relative">
            <div class="w-full">
                
                <?php if (isset($_SESSION['pesan'])): ?>
                    <?= $_SESSION['pesan']; unset($_SESSION['pesan']); ?>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 w-full">
                    
                    <div class="lg:col-span-1 space-y-6">
                        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 text-center relative overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-24 bg-gradient-to-b from-penaburBlue to-penaburDark"></div>
                            
                            <div class="relative inline-block mt-8 mb-4">
                                <img src="assets/<?= htmlspecialchars($foto_user); ?>" onerror="this.src='https://via.placeholder.com/128'" class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-xl mx-auto bg-gray-100">
                            </div>
                            
                            <h3 class="font-extrabold text-lg text-penaburDark"><?= htmlspecialchars($nama_user); ?></h3>
                            <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($username_user); ?></p>
                            
                            <div class="flex justify-center gap-2 flex-wrap">
                                <?php if ($role_user == 'superadmin' && $is_teaching): ?>
                                    <span class="px-3 py-1.5 rounded-full bg-red-50 text-red-600 text-[10px] font-extrabold uppercase tracking-widest border border-red-100">Superadmin</span>
                                    <span class="px-3 py-1.5 rounded-full bg-green-50 text-green-600 text-[10px] font-extrabold uppercase tracking-widest border border-green-100">Guru</span>
                                <?php else: ?>
                                    <span class="px-4 py-1.5 rounded-full bg-blue-50 text-penaburBlue text-[10px] font-extrabold uppercase tracking-widest border border-blue-100"><?= htmlspecialchars($role_user); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (in_array($role_user, ['guru', 'kurikulum', 'kepsek', 'superadmin'])): ?>
                        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                            <h4 class="text-xs font-extrabold text-gray-400 uppercase tracking-widest mb-4"><i class="fa-solid fa-book-open mr-2 text-penaburGold"></i> Mata Pelajaran Diampu</h4>
                            <?php if ($is_teaching): ?>
                                <div class="space-y-3 max-h-64 overflow-y-auto pr-2">
                                    <?php while($tugas = $penugasan_q->fetch_assoc()): ?>
                                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 text-left hover:shadow-md transition">
                                        <p class="font-bold text-penaburDark text-sm"><?= htmlspecialchars($tugas['nama_mapel']); ?></p>
                                        <p class="text-[10px] text-gray-500 font-bold uppercase tracking-widest mt-1">Kelas <?= htmlspecialchars($tugas['nama_kelas']); ?></p>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="p-4 bg-gray-50 rounded-xl border border-gray-100 text-center">
                                    <p class="text-xs text-gray-400 font-bold italic">Belum ada mapel yang ditugaskan ke Anda.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="lg:col-span-2 w-full">
                        <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden w-full">
                            <div class="p-6 sm:p-8 w-full">
                                <form action="" method="POST" enctype="multipart/form-data" class="space-y-6 w-full">
                                    
                                    <h4 class="text-sm font-extrabold text-gray-400 uppercase tracking-widest mb-4">Informasi Personal</h4>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
                                        <div class="w-full">
                                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2 ml-1">Nama Lengkap</label>
                                            <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($nama_user); ?>" 
                                                   class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-penaburGold focus:border-transparent transition outline-none text-sm font-medium w-full" required>
                                        </div>
                                        <div class="w-full">
                                            <label class="block text-xs font-bold text-gray-400 uppercase mb-2 ml-1">Username (Tetap)</label>
                                            <input type="text" value="<?= htmlspecialchars($username_user); ?>" 
                                                   class="w-full px-4 py-3 rounded-2xl border border-gray-100 bg-gray-100 text-gray-400 cursor-not-allowed outline-none text-sm font-medium w-full" readonly>
                                        </div>
                                        <div class="md:col-span-2 w-full">
                                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2 ml-1">Alamat Email Sekolah</label>
                                            <input type="email" name="email" value="<?= htmlspecialchars($email_user); ?>" 
                                                   class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-penaburGold focus:border-transparent transition outline-none text-sm font-medium w-full" required>
                                            <p class="text-[10px] text-penaburBlue mt-2 font-bold italic">
                                                * Gunakan <?= ($role_user == 'siswa') ? '@bpkpenabur.sch.id' : '@bandarlampung.bpkpenabur.or.id'; ?>
                                            </p>
                                        </div>
                                        <div class="md:col-span-2 w-full">
                                            <label class="block text-xs font-bold text-gray-700 uppercase mb-2 ml-1">Ganti Foto Profil</label>
                                            <input type="file" name="foto_profil" accept=".jpg, .jpeg, .png"
                                                   class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-bold file:bg-penaburBlue file:text-white hover:file:bg-penaburDark transition w-full">
                                            <p class="text-[10px] text-gray-400 mt-2 font-medium">* Format JPG/PNG, maksimal 1MB.</p>
                                        </div>
                                    </div>

                                    <div class="pt-6 border-t border-gray-100 w-full">
                                        <h4 class="text-sm font-extrabold text-gray-400 uppercase tracking-widest mb-4">Keamanan Akun</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 w-full">
                                            <div class="w-full">
                                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2 ml-1">Password Baru</label>
                                                <input type="password" name="password_baru" placeholder="Kosongkan jika tidak diganti" 
                                                       class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-penaburGold focus:border-transparent transition outline-none text-sm font-medium w-full">
                                            </div>
                                            <div class="w-full">
                                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2 ml-1">Konfirmasi Password</label>
                                                <input type="password" name="konfirmasi_password" placeholder="Ulangi password baru" 
                                                       class="w-full px-4 py-3 rounded-2xl border border-gray-200 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-penaburGold focus:border-transparent transition outline-none text-sm font-medium w-full">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pt-6 flex justify-end gap-3 w-full">
                                        <a href="dashboard.php" class="px-6 py-3 rounded-2xl text-sm font-bold text-gray-500 hover:bg-gray-100 transition">Batal</a>
                                        <button type="submit" name="update_profil" class="px-8 py-3 rounded-2xl bg-penaburBlue text-white text-sm font-extrabold hover:bg-penaburDark shadow-lg shadow-blue-900/20 transform transition hover:-translate-y-1">
                                            Simpan Perubahan
                                        </button>
                                    </div>

                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>

        <footer class="px-6 py-4 text-center text-[10px] text-gray-400 font-bold uppercase tracking-widest border-t border-gray-100 bg-white mt-auto">
            &copy; <?= date('Y'); ?> E-Learning SMKK BPK PENABUR. Made with ❤️ for Education.
        </footer>
    </div>

</body>
</html>