<?php
// index.php
session_start();
require '../koneksi.php';

// AUTO-FIX: Membuat tabel secara diam-diam jika belum ada
$conn->query("CREATE TABLE IF NOT EXISTS setting_kelulusan (id INT PRIMARY KEY, waktu_pengumuman DATETIME NOT NULL)");
$conn->query("INSERT IGNORE INTO setting_kelulusan (id, waktu_pengumuman) VALUES (1, '2026-05-02 10:00:00')");
$conn->query("CREATE TABLE IF NOT EXISTS status_kelulusan (id_user INT(11) PRIMARY KEY, keterangan VARCHAR(50) NOT NULL DEFAULT 'BELUM ADA DATA')");

$pesan = '';
$pesan_foto = '';

// 1. AUTO-ROUTER: Jika sudah login sebagai Guru/Kurikulum/Superadmin, langsung lempar ke Dashboard Admin
if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['superadmin', 'kurikulum', 'guru'])) {
    header("Location: admin.php");
    exit;
}

// Ambil Waktu Pengumuman
$q_waktu = $conn->query("SELECT waktu_pengumuman FROM setting_kelulusan WHERE id = 1");
$d_waktu = $q_waktu->fetch_assoc();
$waktu_pengumuman = $d_waktu['waktu_pengumuman'];

$now = time();
$target = strtotime($waktu_pengumuman);
$is_open = $now >= $target;

// Proses Login Global SIAKAD
if (isset($_POST['login_siakad'])) {
    $username = $conn->real_escape_string(trim($_POST['username']));
    $password = $_POST['password'];

    $query = "SELECT u.id, u.username, u.password, u.nama_lengkap, u.role, u.foto_profil, k.nama_kelas 
              FROM users u 
              LEFT JOIN kelas k ON u.id_kelas = k.id 
              WHERE u.username = '$username'";
    $result = $conn->query($query);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            
            // Set Global Session SIAKAD
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['foto_profil'] = $user['foto_profil'] ?: 'default.png';

            // Pengecekan Role & Redirect
            if (in_array($user['role'], ['superadmin', 'kurikulum', 'guru'])) {
                header("Location: admin.php");
                exit;
            } elseif ($user['role'] == 'siswa') {
                if (strpos(strtoupper($user['nama_kelas']), 'XII') !== false) {
                    $_SESSION['lulus_kelas'] = $user['nama_kelas'];
                    header("Location: index.php");
                    exit;
                } else {
                    session_destroy();
                    $pesan = "Akses Ditolak! Halaman pengumuman ini eksklusif untuk siswa Kelas XII.";
                }
            } else {
                session_destroy();
                $pesan = "Akses Ditolak! Role tidak dikenali.";
            }
        } else {
            $pesan = "Password SIAKAD Anda salah!";
        }
    } else {
        $pesan = "Username SIAKAD tidak ditemukan!";
    }
}

// Proses Upload Foto Profil Sinkron dengan SIAKAD
if (isset($_POST['update_foto']) && isset($_SESSION['user_id'])) {
    $id_user_aktif = $_SESSION['user_id'];
    if ($_FILES['foto_profil']['name']) {
        $file_name = $_FILES['foto_profil']['name'];
        $file_tmp = $_FILES['foto_profil']['tmp_name'];
        $file_size = $_FILES['foto_profil']['size'];
        
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png'];
        
        if (in_array($ext, $allowed)) {
            if ($file_size <= 1048576) { 
                $new_name = 'profil_' . $id_user_aktif . '_' . time() . '.' . $ext;
                $dest = '../assets/' . $new_name; 
                
                if (move_uploaded_file($file_tmp, $dest)) {
                    $conn->query("UPDATE users SET foto_profil = '$new_name' WHERE id = '$id_user_aktif'");
                    $_SESSION['foto_profil'] = $new_name; // Update session langsung
                    $pesan_foto = "<div class='bg-green-50 text-green-700 p-2 rounded-lg text-[10px] font-bold mb-3 border border-green-200'>Foto berhasil diperbarui!</div>";
                } else {
                    $pesan_foto = "<div class='bg-red-50 text-red-700 p-2 rounded-lg text-[10px] font-bold mb-3 border border-red-200'>Gagal mengunggah foto ke server.</div>";
                }
            } else {
                $pesan_foto = "<div class='bg-red-50 text-red-700 p-2 rounded-lg text-[10px] font-bold mb-3 border border-red-200'>Ukuran foto maksimal 1MB.</div>";
            }
        } else {
            $pesan_foto = "<div class='bg-red-50 text-red-700 p-2 rounded-lg text-[10px] font-bold mb-3 border border-red-200'>Format foto harus JPG/PNG.</div>";
        }
    }
}

// Proses Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$is_logged_in = isset($_SESSION['user_id']) && $_SESSION['role'] == 'siswa';
$keterangan_lulus = 'BELUM ADA DATA';

if ($is_logged_in) {
    $id_user = $_SESSION['user_id'];
    $q_status = $conn->query("SELECT keterangan FROM status_kelulusan WHERE id_user = '$id_user'");
    if ($q_status->num_rows > 0) {
        $keterangan_lulus = $q_status->fetch_assoc()['keterangan'];
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengumuman Kelulusan - SMK BPK Penabur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800;900&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f3f4f6; }
        .bg-penabur { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); }
        .text-penabur { color: #1e3a8a; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } } 
        .animate-fade-in { animation: fadeIn 0.5s ease-out forwards; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4 relative">

    <div class="max-w-md w-full mt-10 md:mt-0">
        <div class="text-center mb-5">
            <img src="../assets/logo-penabur.png" alt="Logo" class="h-24 mx-auto mb-4 drop-shadow-md" onerror="this.src='https://upload.wikimedia.org/wikipedia/id/3/30/Logo_BPK_Penabur.png'">
            <h1 class="text-3xl font-black text-penabur tracking-tight leading-tight">PENGUMUMAN KELULUSAN<br>SMKK BPK PENABUR</h1>
            <div class="h-1 w-16 bg-blue-600 mx-auto mt-3 rounded-full"></div>
        </div>

        <div class="flex justify-center mb-6">
            <div class="bg-white px-5 py-2 rounded-full shadow-sm border border-gray-200 text-xs font-bold text-gray-500 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Waktu Server: <span id="waktu-server" class="text-blue-800 tracking-wide">Memuat...</span>
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden border border-gray-100">
            <div class="bg-penabur p-5 text-white text-center">
                <p class="text-blue-100 text-xs uppercase font-bold tracking-widest">KELAS XII - T.A 2025/2026</p>
            </div>

            <div class="p-8">
                <?php if (!$is_logged_in): ?>
                    <div class="text-center mb-6">
                        <h2 class="text-lg font-bold text-gray-800">Akses Masuk Terpusat</h2>
                        <p class="text-xs text-gray-500 mt-1">Gunakan akun SIAKAD Anda</p>
                    </div>

                    <?php if ($pesan): ?>
                        <div class="bg-red-50 text-red-600 p-4 rounded-xl text-center text-xs font-bold border border-red-100 mb-5"><?= $pesan ?></div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="text-xs font-bold text-gray-500 ml-1">Username SIAKAD</label>
                            <input type="text" name="username" required class="w-full p-4 mt-1 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:bg-white focus:border-blue-500 outline-none transition-all font-semibold">
                        </div>
                        <div>
                            <label class="text-xs font-bold text-gray-500 ml-1">Password</label>
                            <input type="password" name="password" required class="w-full p-4 mt-1 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:bg-white focus:border-blue-500 outline-none transition-all font-semibold">
                        </div>
                        <button type="submit" name="login_siakad" class="w-full bg-penabur text-white py-4 mt-2 rounded-2xl font-extrabold text-lg shadow-xl shadow-blue-200 hover:opacity-90 transform hover:scale-[1.02] transition-all">LOG IN SEKARANG</button>
                    </form>

                    <div class="mt-6 flex flex-col gap-3">
                        <a href="../register.php" class="block w-full text-center py-3 bg-blue-50 text-blue-700 rounded-2xl font-bold text-sm border border-blue-100 hover:bg-blue-100 transition-all">Daftar Akun SIAKAD Baru</a>
                        <button type="button" onclick="bukaPanduan()" class="block w-full text-center text-xs font-bold text-gray-500 hover:text-gray-800 underline decoration-dashed transition-all">Cara & Panduan Penggunaan</button>
                    </div>

                <?php else: ?>
                    <div class="text-center mb-6 animate-fade-in">
                        <p class="text-xs text-gray-400 font-bold uppercase tracking-widest border-b pb-2 mb-4">Data Peserta Didik</p>
                        
                        <div class="relative inline-block mb-3">
                            <img src="../assets/<?= htmlspecialchars($_SESSION['foto_profil']) ?>" onerror="this.src='https://via.placeholder.com/100'" class="w-28 h-28 rounded-full object-cover border-4 border-gray-100 shadow-md mx-auto bg-gray-50">
                        </div>
                        
                        <form method="POST" enctype="multipart/form-data" class="mb-4">
                            <div class="flex items-center justify-center gap-2">
                                <input type="file" name="foto_profil" accept=".jpg, .jpeg, .png" class="block w-48 text-[10px] text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-full file:border-0 file:text-[10px] file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition" required>
                                <button type="submit" name="update_foto" class="bg-blue-600 text-white text-[10px] px-3 py-1.5 rounded-full font-bold shadow-sm hover:bg-blue-700 transition">Update</button>
                            </div>
                        </form>
                        <?= $pesan_foto ?>

                        <h3 class="text-xl font-extrabold text-gray-800"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></h3>
                        <p class="text-sm font-semibold text-blue-600 mt-1"><?= htmlspecialchars($_SESSION['lulus_kelas']) ?></p>
                    </div>

                    <?php if (!$is_open): ?>
                        <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5 mb-5 text-center shadow-inner">
                            <p class="text-xs font-bold text-blue-800 mb-3">SISTEM MASIH TERKUNCI. DIBUKA DALAM:</p>
                            <div class="flex justify-center gap-3">
                                <div><span id="hari" class="block text-2xl font-black text-blue-600">00</span><span class="text-[10px] text-gray-500 font-bold">HARI</span></div><span class="text-xl font-black text-blue-300 mt-1">:</span>
                                <div><span id="jam" class="block text-2xl font-black text-blue-600">00</span><span class="text-[10px] text-gray-500 font-bold">JAM</span></div><span class="text-xl font-black text-blue-300 mt-1">:</span>
                                <div><span id="menit" class="block text-2xl font-black text-blue-600">00</span><span class="text-[10px] text-gray-500 font-bold">MENIT</span></div><span class="text-xl font-black text-blue-300 mt-1">:</span>
                                <div><span id="detik" class="block text-2xl font-black text-blue-600">00</span><span class="text-[10px] text-gray-500 font-bold">DETIK</span></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="animate-fade-in mt-2">
                            <?php if ($keterangan_lulus == 'LULUS'): ?>
                                <div class="bg-green-500 text-white p-6 rounded-3xl shadow-xl shadow-green-200 border-b-4 border-green-700 text-center">
                                    <p class="text-[10px] font-bold tracking-[0.3em] opacity-80 mb-1">HASIL KEPUTUSAN</p>
                                    <h2 class="text-4xl font-black italic tracking-tighter">LULUS</h2>
                                    <p class="text-xs mt-2 font-medium opacity-90">Soli Deo Gloria!</p>
                                </div>
                            <?php elseif ($keterangan_lulus == 'TIDAK LULUS'): ?>
                                <div class="bg-red-500 text-white p-6 rounded-3xl shadow-xl shadow-red-200 border-b-4 border-red-700 text-center">
                                    <p class="text-[10px] font-bold tracking-[0.3em] opacity-80 mb-1">HASIL KEPUTUSAN</p>
                                    <h2 class="text-4xl font-black italic tracking-tighter">TIDAK LULUS</h2>
                                    <p class="text-xs mt-2 font-medium opacity-90">Tetap semangat dan pantang menyerah.</p>
                                </div>
                            <?php else: ?>
                                <div class="bg-yellow-50 text-yellow-700 p-5 rounded-2xl border border-yellow-200 text-center font-bold text-sm">
                                    ⏳ Status Anda sedang diproses oleh pihak sekolah. Mohon cek kembali nanti.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <a href="?logout=1" class="block mt-8 text-center text-xs font-bold text-red-500 hover:text-red-700 transition uppercase tracking-widest">Logout (Keluar)</a>
                <?php endif; ?>
            </div>
        </div>
        <p class="text-center text-xs text-gray-400 font-medium mt-8">&copy; 2026 SMK BPK Penabur.<br>Developed by SteVenJr.</p>
    </div>

    <div id="modal-panduan" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center backdrop-blur-sm p-4">
        <div class="bg-white rounded-3xl shadow-2xl max-w-md w-full overflow-hidden transform transition-all animate-fade-in">
            <div class="bg-blue-600 p-5 flex justify-between items-center border-b border-blue-700">
                <h3 class="text-lg font-black text-white tracking-wide">PANDUAN SISTEM</h3>
                <button type="button" onclick="tutupPanduan()" class="text-blue-200 hover:text-white font-bold text-2xl outline-none">&times;</button>
            </div>
            
            <div class="p-6 space-y-5">
                <div class="flex items-start gap-4">
                    <div class="bg-blue-100 text-blue-700 font-black rounded-full w-8 h-8 flex items-center justify-center shrink-0">1</div>
                    <div>
                        <h4 class="font-bold text-gray-800 text-sm">Registrasi Akun SIAKAD</h4>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Siswa diwajibkan mendaftar akun baru terlebih dahulu melalui tombol <b>Daftar Akun SIAKAD Baru</b>.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="bg-blue-100 text-blue-700 font-black rounded-full w-8 h-8 flex items-center justify-center shrink-0">2</div>
                    <div>
                        <h4 class="font-bold text-gray-800 text-sm">Persetujuan (ACC) Sekolah</h4>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Akun yang telah didaftarkan akan divalidasi dan disetujui (ACC) oleh pihak sekolah/kurikulum.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="bg-blue-100 text-blue-700 font-black rounded-full w-8 h-8 flex items-center justify-center shrink-0">3</div>
                    <div>
                        <h4 class="font-bold text-gray-800 text-sm">Pengolahan Data Kelulusan</h4>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Pihak sekolah akan mengolah, memasukkan, dan mengatur status kelulusan Anda di dalam database.</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="bg-green-100 text-green-700 font-black rounded-full w-8 h-8 flex items-center justify-center shrink-0">4</div>
                    <div>
                        <h4 class="font-bold text-gray-800 text-sm">Lihat Pengumuman</h4>
                        <p class="text-xs text-gray-500 mt-1 leading-relaxed">Silakan Login kembali di halaman ini. Jika waktu pengumuman sudah tiba, hasil kelulusan akan langsung ditampilkan.</p>
                    </div>
                </div>
            </div>
            
            <div class="p-4 bg-gray-50 border-t border-gray-100">
                <button type="button" onclick="tutupPanduan()" class="w-full bg-gray-200 text-gray-800 font-bold py-3 rounded-xl hover:bg-gray-300 transition text-sm">Saya Mengerti</button>
            </div>
        </div>
    </div>

    <script>
        function bukaPanduan() { document.getElementById('modal-panduan').classList.remove('hidden'); }
        function tutupPanduan() { document.getElementById('modal-panduan').classList.add('hidden'); }

        function updateWaktuServer() {
            const now = new Date();
            const hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const bulan = ['Jan.', 'Feb.', 'Mar.', 'Apr.', 'Mei', 'Jun.', 'Jul.', 'Ags.', 'Sep.', 'Okt.', 'Nov.', 'Des.'];
            
            const serverEl = document.getElementById('waktu-server');
            if(serverEl) serverEl.innerText = `${hari[now.getDay()]}, ${String(now.getDate()).padStart(2, '0')} ${bulan[now.getMonth()]} ${now.getFullYear()} | ${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')} WIB`;
        }
        setInterval(updateWaktuServer, 1000);
        updateWaktuServer();

        const targetDate = new Date("<?= str_replace('-', '/', $waktu_pengumuman) ?>").getTime();
        if (document.getElementById('hari')) {
            function updateTimer() {
                const distance = targetDate - new Date().getTime();
                if (distance <= 0) { location.reload(); return; }

                document.getElementById('hari').innerText = String(Math.floor(distance / (1000 * 60 * 60 * 24))).padStart(2, '0');
                document.getElementById('jam').innerText = String(Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))).padStart(2, '0');
                document.getElementById('menit').innerText = String(Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60))).padStart(2, '0');
                document.getElementById('detik').innerText = String(Math.floor((distance % (1000 * 60)) / 1000)).padStart(2, '0');
            }
            setInterval(updateTimer, 1000);
            updateTimer();
        }
    </script>
</body>
</html>