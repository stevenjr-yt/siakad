<?php
session_start();
include 'koneksi.php'; 

// Proteksi jika sudah login
if (isset($_SESSION['role'])) {
    header("Location: dashboard");
    exit();
}

// Ambil Berita Terbaru untuk Section Pengumuman
$query_berita = $conn->query("SELECT judul, slug, ringkasan, tanggal FROM pengumuman ORDER BY tanggal DESC LIMIT 3");
?>
<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIAKAD | SMKK BPK PENABUR Bandar Lampung</title>
    
    <base href="/">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                extend: {
                    colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' },
                    backgroundImage: { 'school-bg': "url('assets/bg-sekolah.png')" },
                    animation: { 'float': 'float 6s ease-in-out infinite', 'float-delayed': 'float 6s ease-in-out 3s infinite' },
                    keyframes: { float: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-20px)' } } }
                }
            }
        }
    </script>
    <style>
        .glass { background: rgba(7, 34, 59, 0.65); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.15); }
        .form-container { transition: all 0.4s ease-in-out; }
        .hidden-form { opacity: 0; pointer-events: none; position: absolute; transform: scale(0.95); visibility: hidden;}
        .active-form { opacity: 1; pointer-events: auto; position: relative; transform: scale(1); visibility: visible;}
        .reveal { opacity: 0; transform: translateY(40px); transition: all 0.8s cubic-bezier(0.5, 0, 0, 1); }
        .reveal.active { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body class="bg-school-bg bg-cover bg-center bg-fixed min-h-screen relative font-sans antialiased text-gray-800 overflow-x-hidden flex flex-col">

    <div class="absolute inset-0 bg-penaburDark/80 backdrop-blur-sm z-0 pointer-events-none"></div>

    <nav class="fixed top-0 left-0 right-0 z-50 bg-penaburDark/90 backdrop-blur-lg shadow-xl transition-all duration-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-full bg-white flex items-center justify-center p-1 ring-2 ring-penaburGold animate-float">
                        <img src="assets/logo-penabur.png" alt="Logo Penabur" class="h-9 w-9 object-contain rounded-full" onerror="this.src='https://via.placeholder.com/36'; this.onerror='';">
                    </div>
                    <div>
                        <h1 class="text-white font-bold text-lg">SIAKAD BPK PENABUR</h1>
                        <p class="text-penaburGold text-xs uppercase tracking-widest font-bold">SMKK Bandar Lampung</p>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="flex-grow relative z-10">
        
        <section class="min-h-screen flex items-center pt-20 relative overflow-hidden">
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute rounded-full bg-penaburGold/30 animate-float" style="width: 15px; height: 15px; left: 10%; top: 20%;"></div>
                <div class="absolute rounded-full bg-penaburGold/20 animate-float-delayed" style="width: 25px; height: 25px; left: 80%; top: 15%;"></div>
                <div class="absolute rounded-full bg-white/20 animate-float" style="width: 10px; height: 10px; left: 50%; top: 80%;"></div>
            </div>

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
                <div class="grid lg:grid-cols-2 gap-12 items-center">
                    
                    <div class="text-center lg:text-left z-10 reveal">
                        <div class="inline-flex items-center gap-2 px-4 py-2 bg-white/10 border border-white/20 rounded-full text-penaburGold text-sm mb-6 shadow-lg backdrop-blur-md font-bold">
                            <span>✨</span> Platform Akademik Terintegrasi
                        </div>
                        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight mb-6 drop-shadow-lg">
                            Belajar & Mengajar<br>
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-penaburGold to-yellow-200">Lebih Terstruktur</span><br>
                            & Terintegrasi 🚀
                        </h1>
                        <p class="text-blue-100 text-lg sm:text-xl mb-8 max-w-xl mx-auto lg:mx-0 drop-shadow-md">
                            Kelola nilai, absensi, submit tugas, dan akses materi Moodle langsung dalam satu platform modern SMKK BPK PENABUR Bandar Lampung.
                        </p>
                    </div>

                    <div class="w-full max-w-md mx-auto lg:ml-auto z-10 reveal" style="transition-delay: 200ms;">
                        <div class="glass rounded-3xl p-8 shadow-2xl relative">
                            <?php if (isset($_SESSION['pesan'])): ?>
                                <?= $_SESSION['pesan']; ?>
                                <?php unset($_SESSION['pesan']); ?>
                            <?php endif; ?>

                            <div class="flex mb-8 bg-black/30 rounded-xl p-1 relative z-20">
                                <button id="btn-login" class="flex-1 text-center font-bold text-sm py-2 rounded-lg bg-penaburGold text-penaburDark shadow transition-all duration-300" onclick="showLogin()">Masuk</button>
                                <button id="btn-register" class="flex-1 text-center font-bold text-sm py-2 rounded-lg text-gray-300 hover:text-white transition-all duration-300" onclick="showRegister()">Daftar</button>
                            </div>

                            <div id="form-login" class="form-container active-form">
                                <h3 class="text-2xl font-bold text-white mb-6">Selamat Datang! 👋</h3>
                                <form action="proses_login" method="POST" class="space-y-5">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1 ml-1">Username / ID</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                <i class="fa-regular fa-user text-gray-400"></i>
                                            </div>
                                            <input type="text" name="username" class="block w-full pl-11 pr-4 py-3 border border-white/20 rounded-xl bg-black/30 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-penaburGold focus:bg-black/50 transition" placeholder="Masukkan Username Anda" required>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1 ml-1">Password</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                <i class="fa-solid fa-lock text-gray-400"></i>
                                            </div>
                                            <input type="password" name="password" class="block w-full pl-11 pr-4 py-3 border border-white/20 rounded-xl bg-black/30 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-penaburGold focus:bg-black/50 transition" placeholder="••••••••" required>
                                        </div>
                                    </div>
                                    <button type="submit" name="login" class="w-full bg-gradient-to-r from-penaburGold to-yellow-500 hover:from-yellow-400 hover:to-yellow-600 text-penaburDark font-extrabold py-3 px-4 rounded-xl shadow-lg transform transition hover:-translate-y-1 mt-4">
                                        Masuk Sistem <i class="fa-solid fa-arrow-right ml-2"></i>
                                    </button>
                                </form>
                            </div>

                            <div id="form-register" class="form-container hidden-form">
                                <h3 class="text-2xl font-bold text-white mb-6">Buat Akun Baru ✨</h3>
                                <form action="proses_register" method="POST" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1 ml-1">Nama Lengkap</label>
                                        <input type="text" name="nama_lengkap" class="block w-full px-4 py-2.5 border border-white/20 rounded-xl bg-black/30 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-penaburGold focus:bg-black/50 transition" placeholder="Sesuai Ijazah" required>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-300 mb-1 ml-1">Role</label>
                                            <select id="reg_role" name="role" class="block w-full px-4 py-2.5 border border-white/20 rounded-xl bg-black/30 text-white focus:outline-none focus:ring-2 focus:ring-penaburGold focus:bg-penaburDark transition text-sm" required onchange="toggleRegFields()">
                                                <option value="siswa" class="text-black">Siswa</option>
                                                <option value="guru" class="text-black">Guru / Admin</option>
                                            </select>
                                        </div>
                                        <div id="box_kelas">
                                            <label class="block text-sm font-medium text-gray-300 mb-1 ml-1">Kelas</label>
                                            <select name="id_kelas" class="block w-full px-4 py-2.5 border border-white/20 rounded-xl bg-black/30 text-white focus:outline-none focus:ring-2 focus:ring-penaburGold focus:bg-penaburDark transition text-sm">
                                                <option value="1" class="text-black">X AK 1</option>
                                                <option value="2" class="text-black">X AK 2</option>
                                                <option value="3" class="text-black">XI AK 1</option>
                                                <option value="4" class="text-black">XI AK 2</option>
                                                <option value="5" class="text-black">XII AK 1</option>
                                                <option value="6" class="text-black">XII AK 2</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label id="label_username" class="block text-sm font-medium text-gray-300 mb-1 ml-1">Username</label>
                                        <input type="text" id="reg_username" name="username" class="block w-full px-4 py-2.5 border border-white/20 rounded-xl bg-black/30 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-penaburGold focus:bg-black/50 transition" placeholder="Username Anda" required>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1 ml-1">Email Sekolah</label>
                                        <input type="email" id="reg_email" name="email" class="block w-full px-4 py-2.5 border border-white/20 rounded-xl bg-black/30 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-penaburGold focus:bg-black/50 transition" placeholder="...domain sekolah" required>
                                        <p id="helper_email" class="text-[10px] text-penaburGold mt-1.5 font-bold italic tracking-wide"></p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-300 mb-1 ml-1">Buat Password</label>
                                        <input type="password" name="password" class="block w-full px-4 py-2.5 border border-white/20 rounded-xl bg-black/30 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-penaburGold focus:bg-black/50 transition" placeholder="Minimal 6 Karakter" required>
                                    </div>
                                    
                                    <button type="submit" name="register" class="w-full bg-white/10 border-2 border-penaburGold hover:bg-penaburGold text-penaburGold hover:text-penaburDark font-bold py-3 px-4 rounded-xl shadow-lg transform transition hover:-translate-y-1 mt-2">
                                        Daftar Sekarang <i class="fa-solid fa-user-plus ml-2"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="absolute bottom-0 left-0 right-0 z-20 translate-y-1">
                <svg viewBox="0 0 1440 120" fill="none"><path d="M0,96L48,85.3C96,75,192,53,288,48C384,43,480,53,576,69.3C672,85,768,107,864,101.3C960,96,1056,64,1152,53.3C1248,43,1344,53,1392,58.7L1440,64L1440,120L1392,120C1344,120,1248,120,1152,120C1056,120,960,120,864,120C768,120,672,120,576,120C480,120,384,120,288,120C192,120,96,120,48,120L0,120Z" fill="#f9fafb"/></svg>
            </div>
        </section>

        <section class="py-24 bg-gray-50 relative z-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16 reveal">
                    <span class="inline-block px-4 py-1.5 bg-blue-100 text-penaburBlue rounded-full text-sm font-bold mb-4 tracking-widest uppercase">📢 Pengumuman</span>
                    <h2 class="text-3xl sm:text-4xl font-extrabold text-penaburDark mb-4">Update Terbaru</h2>
                    <p class="text-gray-600 max-w-2xl mx-auto">Pantau terus informasi akademik dan kegiatan sekolah SMKK BPK PENABUR di sini.</p>
                </div>

                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php 
                    $delay = 0;
                    while($row = $query_berita->fetch_assoc()): 
                        $tgl = date('d M Y', strtotime($row['tanggal']));
                    ?>
                    <div class="bg-white rounded-3xl p-7 shadow-sm hover:shadow-xl transition border border-gray-100 transform hover:-translate-y-2 reveal" style="transition-delay: <?= $delay; ?>ms;">
                        <span class="inline-block px-3 py-1 bg-gray-100 text-gray-500 rounded-lg text-[10px] font-bold mb-4 uppercase tracking-wider"><i class="fa-regular fa-calendar mr-2"></i> <?= $tgl; ?></span>
                        <h3 class="text-lg font-bold text-penaburDark mb-3 leading-tight"><?= $row['judul']; ?></h3>
                        <p class="text-gray-500 text-sm leading-relaxed mb-6 line-clamp-3"><?= $row['ringkasan']; ?></p>
                        <a href="<?= $row['slug']; ?>" class="inline-flex items-center text-sm font-extrabold text-penaburBlue hover:text-penaburGold transition">
                            Baca Selengkapnya <i class="fa-solid fa-arrow-right-long ml-3"></i>
                        </a>
                    </div>
                    <?php $delay += 150; endwhile; ?>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-gray-50 text-penaburDark py-10 relative z-20 border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-4">
                <div class="h-12 w-12 rounded-full bg-penaburDark flex items-center justify-center p-1 ring-2 ring-penaburGold shadow-lg">
                    <img src="assets/logo-penabur.png" alt="Logo Penabur" class="h-8 w-8 object-contain rounded-full" onerror="this.src='https://via.placeholder.com/32'; this.onerror='';">
                </div>
                <div>
                    <h3 class="font-extrabold text-sm md:text-base">SMKK BPK PENABUR Bandar Lampung</h3>
                    <p class="text-gray-500 text-[10px] font-bold uppercase tracking-widest mt-0.5">Sistem Informasi Akademik & E-Learning</p>
                </div>
            </div>
            <div class="text-gray-400 text-[10px] text-center md:text-right font-bold uppercase tracking-[0.2em]">
                &copy; <?= date('Y'); ?> BPK PENABUR. All rights reserved.<br>
                Built with ❤️ for Education
            </div>
        </div>
    </footer>

    <script>
        function showLogin() {
            document.getElementById('form-login').classList.replace('hidden-form', 'active-form');
            document.getElementById('form-register').classList.replace('active-form', 'hidden-form');
            document.getElementById('btn-login').className = "flex-1 text-center font-bold text-sm py-2 rounded-lg bg-penaburGold text-penaburDark shadow transition-all duration-300";
            document.getElementById('btn-register').className = "flex-1 text-center font-bold text-sm py-2 rounded-lg text-gray-300 hover:text-white transition-all duration-300";
        }

        function showRegister() {
            document.getElementById('form-register').classList.replace('hidden-form', 'active-form');
            document.getElementById('form-login').classList.replace('active-form', 'hidden-form');
            document.getElementById('btn-register').className = "flex-1 text-center font-bold text-sm py-2 rounded-lg bg-penaburGold text-penaburDark shadow transition-all duration-300";
            document.getElementById('btn-login').className = "flex-1 text-center font-bold text-sm py-2 rounded-lg text-gray-300 hover:text-white transition-all duration-300";
            toggleRegFields();
        }

        function toggleRegFields() {
            var role = document.getElementById('reg_role').value;
            var boxKelas = document.getElementById('box_kelas');
            var labelUser = document.getElementById('label_username');
            var inputUser = document.getElementById('reg_username');
            var inputEmail = document.getElementById('reg_email');
            var helperEmail = document.getElementById('helper_email');

            if (role === 'siswa') {
                boxKelas.style.display = 'block';
                labelUser.innerHTML = "Username";
                inputUser.placeholder = "Masukkan Username";
                inputEmail.placeholder = "contoh@bpkpenabur.sch.id";
                helperEmail.innerHTML = "* Gunakan email @bpkpenabur.sch.id";
            } else {
                boxKelas.style.display = 'none';
                labelUser.innerHTML = "Username";
                inputUser.placeholder = "Masukkan Username";
                inputEmail.placeholder = "contoh@bandarlampung.bpkpenabur.or.id";
                helperEmail.innerHTML = "* Gunakan email @bandarlampung.bpkpenabur.or.id";
            }
        }

        function reveal() {
            var reveals = document.querySelectorAll(".reveal");
            for (var i = 0; i < reveals.length; i++) {
                var windowHeight = window.innerHeight;
                var elementTop = reveals[i].getBoundingClientRect().top;
                var elementVisible = 50;
                if (elementTop < windowHeight - elementVisible) {
                    reveals[i].classList.add("active");
                }
            }
        }
        window.addEventListener("scroll", reveal);
        document.addEventListener('DOMContentLoaded', function() {
            toggleRegFields();
            reveal();
        });
    </script>
</body>
</html>