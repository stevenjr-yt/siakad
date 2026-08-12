<?php
session_start();
include 'koneksi.php';

// Tendang kalau belum login
if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

// Pastikan session user_id ada
if (!isset($_SESSION['user_id'])) {
    $username_session = $_SESSION['username'];
    $get_id = $conn->query("SELECT id FROM users WHERE username = '$username_session'");
    $_SESSION['user_id'] = $get_id->fetch_assoc()['id'] ?? 0;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$nama_user = $_SESSION['nama_lengkap'];
$foto_user = $_SESSION['foto_profil'] ?? 'default.png';

// INJEKSI AMAN STATUS AKUN & UBAH DEFAULT KE PENDING UNTUK REGISTER BARU
$cek_status = $conn->query("SHOW COLUMNS FROM users LIKE 'status_akun'");
if ($cek_status && $cek_status->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN status_akun VARCHAR(20) DEFAULT 'aktif'");
    $conn->query("ALTER TABLE users ALTER COLUMN status_akun SET DEFAULT 'pending'");
} else {
    $conn->query("ALTER TABLE users ALTER COLUMN status_akun SET DEFAULT 'pending'");
}

// LOGIKA APPROVE USER
if (isset($_GET['approve_user']) && in_array($role, ['superadmin', 'kepsek', 'kurikulum'])) {
    $id_u = (int)$_GET['approve_user'];
    $conn->query("UPDATE users SET status_akun='aktif' WHERE id=$id_u");
    header("Location: dashboard.php?msg=approved");
    exit;
}

// =========================================================================
// FITUR DEWA: AUTO-SWEEP KOREKSI NILAI 0 (DIJALANKAN DI BACKGROUND)
// =========================================================================
// Sistem otomatis nyari anak yg nilainya 0, narik jawaban dari DB, dan ngoreksi ulang!
if (in_array($role, ['superadmin', 'guru', 'kurikulum', 'kepsek'])) {
    $q_zero = $conn->query("SELECT id, id_kuis, id_siswa FROM kuis_nilai WHERE nilai = 0");
    if ($q_zero && $q_zero->num_rows > 0) {
        while ($zn = $q_zero->fetch_assoc()) {
            $id_k_zero = $zn['id_kuis'];
            $id_s_zero = $zn['id_siswa'];

            // 1. Ambil kunci jawaban kuis
            $kunci = [];
            $q_kunci = $conn->query("SELECT id, kunci_jawaban FROM kuis_soal WHERE id_kuis = $id_k_zero AND tipe='pg'");
            $total_soal = $q_kunci->num_rows;

            if ($total_soal > 0) {
                while($k = $q_kunci->fetch_assoc()) {
                    $kunci[$k['id']] = strtoupper(trim($k['kunci_jawaban']));
                }

                // 2. Tarik mutlak jawaban siswa dari DB (kuis_jawaban_siswa) yg udah ke-save
                $q_ans = $conn->query("SELECT id_soal, jawaban FROM kuis_jawaban_siswa WHERE id_kuis = $id_k_zero AND id_siswa = $id_s_zero");
                if ($q_ans && $q_ans->num_rows > 0) {
                    $benar = 0;
                    while($ans = $q_ans->fetch_assoc()) {
                        $jwb_siswa = strtoupper(trim($ans['jawaban']));
                        $id_soal = $ans['id_soal'];
                        if (isset($kunci[$id_soal]) && $jwb_siswa == $kunci[$id_soal]) {
                            $benar++;
                        }
                    }
                    
                    // 3. Kalkulasi & Update Nilai Baru
                    $nilai_baru = ($benar / $total_soal) * 100;
                    if ($nilai_baru > 0) {
                        $conn->query("UPDATE kuis_nilai SET nilai = $nilai_baru WHERE id_kuis = $id_k_zero AND id_siswa = $id_s_zero");
                    }
                }
            }
        }
    }
}
// =========================================================================

// ==========================================
// 1. DATA STATISTIK UTAMA (Global)
// ==========================================
$q_pending_count = $conn->query("SELECT COUNT(*) as total FROM users WHERE status_akun = 'pending' OR status_akun = 'Pending'");
$total_pending = ($q_pending_count) ? $q_pending_count->fetch_assoc()['total'] : 0;

$total_siswa = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'siswa'")->fetch_assoc()['total'] ?? 0;
$total_guru  = $conn->query("SELECT COUNT(*) as total FROM users WHERE role IN ('guru', 'kurikulum', 'kepsek', 'superadmin')")->fetch_assoc()['total'] ?? 0;
$total_mapel = $conn->query("SELECT COUNT(*) as total FROM mapel")->fetch_assoc()['total'] ?? 0;
$total_kelas = $conn->query("SELECT COUNT(*) as total FROM kelas")->fetch_assoc()['total'] ?? 0;

// ==========================================
// 2. DATA GRAFIK
// ==========================================
$grafik_kelas_labels = []; $grafik_kelas_data = [];
$q_gk = $conn->query("SELECT k.nama_kelas, COUNT(u.id) as total FROM kelas k LEFT JOIN users u ON k.id = u.id_kelas AND u.role='siswa' GROUP BY k.id");
if($q_gk){ while($r = $q_gk->fetch_assoc()){ $grafik_kelas_labels[] = $r['nama_kelas']; $grafik_kelas_data[] = $r['total']; } }

$cek_tabel_nilai = $conn->query("SHOW TABLES LIKE 'nilai'");
$tabel_nilai_ada = ($cek_tabel_nilai && $cek_tabel_nilai->num_rows > 0);

$grafik_nilai_labels = []; $grafik_nilai_data = [];
if ($tabel_nilai_ada && in_array($role, ['superadmin', 'kepsek', 'kurikulum'])) {
    $q_gn = $conn->query("SELECT m.nama_mapel, AVG(n.nilai_akhir) as rata FROM nilai n JOIN penugasan p ON n.id_penugasan = p.id JOIN mapel m ON p.id_mapel = m.id GROUP BY m.id");
    if($q_gn && $q_gn->num_rows > 0) {
        while($r = $q_gn->fetch_assoc()) {
            if($r['nama_mapel'] != null) {
                $grafik_nilai_labels[] = $r['nama_mapel']; $grafik_nilai_data[] = round($r['rata'], 2);
            }
        }
    }
}

$grafik_nilai_guru_labels = []; $grafik_nilai_guru_data = [];
if ($tabel_nilai_ada && $role != 'siswa') {
    $q_gn_guru = $conn->query("SELECT m.nama_mapel, AVG(n.nilai_akhir) as rata FROM nilai n JOIN penugasan p ON n.id_penugasan = p.id JOIN mapel m ON p.id_mapel = m.id WHERE p.id_guru = '$user_id' GROUP BY m.id");
    if($q_gn_guru && $q_gn_guru->num_rows > 0) {
        while($r = $q_gn_guru->fetch_assoc()) {
            if($r['nama_mapel'] != null) {
                $grafik_nilai_guru_labels[] = $r['nama_mapel']; $grafik_nilai_guru_data[] = round($r['rata'], 2);
            }
        }
    }
}

$dashboard_title = "";
switch ($role) {
    case 'superadmin': $dashboard_title = "Super Administrator"; break;
    case 'kepsek': $dashboard_title = "Kepala Sekolah"; break;
    case 'kurikulum': $dashboard_title = "Waka Kurikulum"; break;
    case 'guru': $dashboard_title = "Guru Mata Pelajaran"; break;
    case 'siswa': $dashboard_title = "Siswa"; break;
}
?>

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - SIAKAD PENABUR</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        tailwind.config = {
            theme: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } } }
        }
    </script>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-800" x-data="{ sidebarOpen: false }">

    <div class="fixed inset-0 z-40 bg-black/50 lg:hidden" x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity style="display: none;"></div>

    <?php include 'sidebar.php'; ?>

    <div class="min-h-screen flex flex-col transition-all duration-300" :class="sidebarOpen ? 'lg:ml-64' : 'ml-0'">
        
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm">
            <div class="flex items-center justify-between px-4 sm:px-8 py-4">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue transition p-2 rounded-lg bg-gray-100">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-xl font-extrabold text-penaburDark hidden sm:block"><?= $dashboard_title; ?></h2>
                </div>
                
                <div class="flex items-center gap-5">
                    <div class="hidden md:flex flex-col items-end">
                        <span class="text-sm font-extrabold text-penaburDark"><?= htmlspecialchars($nama_user); ?></span>
                        <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest"><?= htmlspecialchars($role); ?></span>
                    </div>
                    
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center gap-2 p-1 rounded-full hover:bg-gray-100 transition focus:ring-2 focus:ring-penaburGold">
                            <img src="assets/<?= htmlspecialchars($foto_user); ?>" onerror="this.src='https://via.placeholder.com/40'" class="w-10 h-10 rounded-full object-cover border-2 border-penaburGold shadow-md bg-gray-100" alt="User">
                            <i class="fa-solid fa-chevron-down text-xs text-gray-400 mr-2"></i>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-3 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-50 bg-gray-50/50">
                                <p class="text-sm font-bold text-penaburDark truncate"><?= htmlspecialchars($nama_user); ?></p>
                                <p class="text-xs text-gray-500 truncate"><?= $_SESSION['username']; ?></p>
                            </div>
                            <a href="profil.php" class="block px-4 py-2.5 text-sm font-medium text-gray-600 hover:bg-blue-50 hover:text-penaburBlue transition"><i class="fa-solid fa-user-gear mr-2"></i> Pengaturan Profil</a>
                            <div class="border-t border-gray-50 my-1"></div>
                            <a href="logout.php" class="block px-4 py-2.5 text-sm font-bold text-red-500 hover:bg-red-50 transition"><i class="fa-solid fa-arrow-right-from-bracket mr-2"></i> Keluar Sistem</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-8">
            <div class="max-w-7xl mx-auto space-y-8">
                
                <?php if (isset($_GET['msg']) && $_GET['msg'] == 'approved'): ?>
                    <div class="bg-green-100 text-green-700 p-4 rounded-xl font-bold border border-green-200 shadow-sm"><i class="fa-solid fa-check-circle mr-2"></i> Akun pengguna berhasil disetujui!</div>
                <?php endif; ?>
                <?php if (isset($_SESSION['pesan'])): ?>
                    <?= $_SESSION['pesan']; unset($_SESSION['pesan']); ?>
                <?php endif; ?>

                <div class="bg-gradient-to-r from-penaburDark via-penaburBlue to-[#1a5b99] rounded-3xl p-8 text-white relative overflow-hidden shadow-lg border border-blue-900/50">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-penaburGold/10 rounded-full -translate-y-1/2 translate-x-1/4 blur-3xl"></div>
                    <div class="relative z-10 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h2 class="text-3xl font-extrabold mb-2 drop-shadow-md">Selamat datang, <?= htmlspecialchars($nama_user); ?>! 👋</h2>
                            <p class="text-blue-200 text-sm font-medium">Sistem Informasi Akademik & E-Learning BPK PENABUR</p>
                        </div>
                        <?php if ($role == 'superadmin'): ?>
                            <span class="px-4 py-2 bg-red-500 text-white text-xs font-bold rounded-xl shadow-lg animate-pulse"><i class="fa-solid fa-shield-halved mr-1"></i> FULL ACCESS</span>
                        <?php endif; ?>
                    </div>
                </div>

                <?php 
                if ($role == 'siswa'): 
                    $id_kls = $_SESSION['id_kelas'] ?? 0;
                    $q_dl = $conn->query("SELECT k.id, k.judul, k.waktu_selesai, m.nama_mapel, p.id as id_penugasan FROM kuis k JOIN penugasan p ON k.id_penugasan=p.id JOIN mapel m ON p.id_mapel=m.id WHERE p.id_kelas=$id_kls AND k.status='aktif' AND k.waktu_selesai >= NOW() ORDER BY k.waktu_selesai ASC LIMIT 5");
                    if($q_dl && $q_dl->num_rows > 0):
                ?>
                <div class="bg-white p-6 rounded-3xl shadow-sm border border-orange-100">
                    <h3 class="text-lg font-extrabold text-orange-600 mb-4"><i class="fa-solid fa-clock mr-2"></i> Tenggat Waktu Tugas (Dateline)</h3>
                    <div class="space-y-3">
                        <?php while($dl = $q_dl->fetch_assoc()): ?>
                        <a href="view.php?id=<?= $dl['id_penugasan']; ?>&kerjakan=<?= $dl['id']; ?>" class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-4 bg-orange-50 rounded-2xl border border-orange-100 hover:shadow-md transition gap-3">
                            <div>
                                <p class="font-bold text-penaburDark"><?= htmlspecialchars($dl['judul']); ?></p>
                                <p class="text-[10px] uppercase font-bold text-gray-500"><?= htmlspecialchars($dl['nama_mapel']); ?></p>
                            </div>
                            <div class="text-right sm:text-center shrink-0 w-full sm:w-auto">
                                <span class="px-4 py-2 bg-white text-xs font-black text-red-500 rounded-xl border shadow-sm block w-full"><i class="fa-solid fa-bell mr-1 animate-pulse"></i> Batas: <?= date('d M Y H:i', strtotime($dl['waktu_selesai'])); ?></span>
                            </div>
                        </a>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; endif; ?>

                <?php if ($role == 'guru'): 
                    @$conn->query("CREATE TABLE IF NOT EXISTS pengaturan (id INT AUTO_INCREMENT PRIMARY KEY, nama_setting VARCHAR(50), nilai_setting VARCHAR(100))");
                    $cek_set = $conn->query("SELECT id FROM pengaturan WHERE nama_setting='portal_nilai'");
                    if($cek_set && $cek_set->num_rows == 0) { @$conn->query("INSERT INTO pengaturan (nama_setting, nilai_setting) VALUES ('portal_nilai', 'tutup')"); }

                    $set_p = $conn->query("SELECT nilai_setting FROM pengaturan WHERE nama_setting='portal_nilai'");
                    $val_portal = ($set_p && $set_p->num_rows > 0) ? $set_p->fetch_assoc()['nilai_setting'] : 'tutup';
                    if($val_portal == 'buka'):
                ?>
                <div class="bg-red-50 p-6 rounded-3xl shadow-sm border border-red-200 animate-pulse">
                    <h3 class="text-lg font-extrabold text-red-600 mb-2"><i class="fa-solid fa-triangle-exclamation mr-2"></i> PORTAL NILAI DIBUKA!</h3>
                    <p class="text-sm font-bold text-red-500">Waka Kurikulum telah membuka portal pengisian nilai akhir. Segera selesaikan rekapitulasi nilai Anda di menu Input Nilai!</p>
                </div>
                <?php endif; ?>

                <div class="bg-white p-6 rounded-3xl shadow-sm border border-blue-100">
                    <h3 class="text-lg font-extrabold text-penaburBlue mb-4"><i class="fa-solid fa-clipboard-list mr-2"></i> Kuis & Tugas Berjalan</h3>
                    <?php
                        $q_tugas_guru = $conn->query("SELECT k.id, k.judul, m.nama_mapel, kls.nama_kelas, p.id as id_penugasan FROM kuis k JOIN penugasan p ON k.id_penugasan=p.id JOIN mapel m ON p.id_mapel=m.id JOIN kelas kls ON p.id_kelas=kls.id WHERE p.id_guru=$user_id AND k.status='aktif' ORDER BY k.id DESC LIMIT 5");
                        if($q_tugas_guru && $q_tugas_guru->num_rows > 0):
                    ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <?php while($tg = $q_tugas_guru->fetch_assoc()): ?>
                        <a href="view.php?id=<?= $tg['id_penugasan']; ?>&scoreboard=<?= $tg['id']; ?>" class="flex justify-between items-center p-4 bg-blue-50 rounded-2xl border border-blue-100 hover:shadow-md transition">
                            <div class="min-w-0 pr-4">
                                <p class="font-bold text-penaburDark truncate"><?= htmlspecialchars($tg['judul']); ?></p>
                                <p class="text-[10px] uppercase font-bold text-gray-500 truncate"><?= htmlspecialchars($tg['nama_mapel']); ?> - Kelas <?= htmlspecialchars($tg['nama_kelas']); ?></p>
                            </div>
                            <span class="text-xs font-black text-penaburBlue bg-white px-3 py-2 rounded-xl border shadow-sm shrink-0"><i class="fa-solid fa-chart-bar"></i></span>
                        </a>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-gray-400 font-bold italic">Belum ada tugas atau kuis aktif.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (in_array($role, ['superadmin', 'kepsek', 'kurikulum'])): ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6">
                        
                        <a href="approve_user.php" class="bg-white rounded-3xl p-6 shadow-sm border border-red-200 flex items-center gap-5 hover:shadow-lg transition transform hover:-translate-y-1 cursor-pointer group relative overflow-hidden">
                            <?php if($total_pending > 0): ?>
                                <span class="absolute top-4 right-4 flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span></span>
                            <?php endif; ?>
                            <div class="w-14 h-14 rounded-2xl bg-red-50 text-red-600 flex items-center justify-center text-2xl shadow-inner group-hover:scale-110 transition duration-300"><i class="fa-solid fa-user-clock"></i></div>
                            <div>
                                <p class="text-3xl font-extrabold text-red-700"><?= $total_pending; ?></p>
                                <p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-wide mt-1">Verifikasi Akun</p>
                            </div>
                        </a>

                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition">
                            <div class="w-14 h-14 rounded-2xl bg-blue-50 text-penaburBlue flex items-center justify-center text-2xl shadow-inner"><i class="fa-solid fa-user-graduate"></i></div>
                            <div><p class="text-3xl font-extrabold text-penaburDark"><?= $total_siswa; ?></p><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mt-1">Total Siswa</p></div>
                        </div>
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition">
                            <div class="w-14 h-14 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center text-2xl shadow-inner"><i class="fa-solid fa-chalkboard-user"></i></div>
                            <div><p class="text-3xl font-extrabold text-green-700"><?= $total_guru; ?></p><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mt-1">Total Guru</p></div>
                        </div>
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition">
                            <div class="w-14 h-14 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-2xl shadow-inner"><i class="fa-solid fa-book"></i></div>
                            <div><p class="text-3xl font-extrabold text-purple-700"><?= $total_mapel; ?></p><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mt-1">Mata Pelajaran</p></div>
                        </div>
                        <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex items-center gap-5 hover:shadow-md transition">
                            <div class="w-14 h-14 rounded-2xl bg-orange-50 text-orange-500 flex items-center justify-center text-2xl shadow-inner"><i class="fa-solid fa-door-open"></i></div>
                            <div><p class="text-3xl font-extrabold text-orange-600"><?= $total_kelas; ?></p><p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mt-1">Rombel / Kelas</p></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                        <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                            <h3 class="text-lg font-extrabold text-penaburDark mb-4"><i class="fa-solid fa-chart-bar text-penaburGold mr-2"></i> Persebaran Siswa per Kelas</h3>
                            <div style="position: relative; height: 220px; width: 100%;">
                                <canvas id="chartKelas"></canvas>
                            </div>
                        </div>
                        <div class="bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                            <h3 class="text-lg font-extrabold text-penaburDark mb-4"><i class="fa-solid fa-chart-line text-penaburBlue mr-2"></i> Rata-rata Nilai per Mapel (Global)</h3>
                            <div style="position: relative; height: 220px; width: 100%;">
                                <canvas id="chartNilaiGlobal"></canvas>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (in_array($role, ['guru', 'superadmin', 'kepsek', 'kurikulum'])): ?>
                    <?php 
                    $total_jp = 0;
                    $cek_jadwal = $conn->query("SHOW TABLES LIKE 'jadwal_pelajaran'");
                    if($cek_jadwal && $cek_jadwal->num_rows > 0) {
                        $jam_q = $conn->query("SELECT COUNT(j.id) as total_slot FROM jadwal_pelajaran j JOIN penugasan p ON j.id_mapel = p.id_mapel AND j.id_kelas = p.id_kelas WHERE p.id_guru = '$user_id'");
                        if($jam_q) $total_jp = $jam_q->fetch_assoc()['total_slot'] ?? 0;
                    }
                    
                    $cek_ngajar = $conn->query("SELECT id FROM penugasan WHERE id_guru = '$user_id'");
                    if ($cek_ngajar && $cek_ngajar->num_rows > 0):
                    ?>
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 w-full mb-6 mt-8">
                            <div class="lg:col-span-1 bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex flex-col justify-center items-center text-center">
                                <div class="p-5 bg-penaburGold/20 text-penaburDark rounded-full mb-4"><i class="fa-solid fa-stopwatch text-4xl"></i></div>
                                <h3 class="text-lg font-extrabold text-gray-700">Akumulasi Jam Mengajar</h3>
                                <p class="text-sm text-gray-500 mb-2">Total Jadwal Anda</p>
                                <div class="text-5xl font-black text-penaburBlue mt-2"><?= $total_jp; ?> <span class="text-sm text-gray-400 font-bold uppercase">Jam</span></div>
                            </div>
                            <div class="lg:col-span-2 bg-white p-6 rounded-3xl shadow-sm border border-gray-100">
                                <h3 class="text-lg font-extrabold text-penaburDark mb-4"><i class="fa-solid fa-chart-line text-penaburBlue mr-2"></i> Rata-rata Nilai Kelas Ampuan Anda</h3>
                                <div style="position: relative; height: 200px; width: 100%;">
                                    <canvas id="chartNilaiGuru"></canvas>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="mt-8">
                    <div class="flex justify-between items-end mb-4 border-b pb-2">
                        <h3 class="text-xl font-extrabold text-penaburDark"><i class="fa-solid fa-chalkboard text-penaburGold mr-2"></i> Ruang Kelas (E-Learning)</h3>
                        <a href="semua_kelas.php" class="text-sm font-bold text-penaburBlue hover:underline">Lihat Semua</a>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php
                        if ($role == 'siswa') {
                            $id_kelas_siswa = $_SESSION['id_kelas'] ?? 0;
                            $q_card = $conn->query("SELECT p.id, m.nama_mapel, k.nama_kelas, m.kode_mapel, u.nama_lengkap as pengajar FROM penugasan p JOIN mapel m ON p.id_mapel = m.id JOIN kelas k ON p.id_kelas = k.id JOIN users u ON p.id_guru = u.id WHERE p.id_kelas = '$id_kelas_siswa' LIMIT 6");
                        } else {
                            $q_card = $conn->query("SELECT p.id, m.nama_mapel, k.nama_kelas, m.kode_mapel, u.nama_lengkap as pengajar FROM penugasan p JOIN mapel m ON p.id_mapel = m.id JOIN kelas k ON p.id_kelas = k.id JOIN users u ON p.id_guru = u.id WHERE p.id_guru = '$user_id' LIMIT 6");
                        }

                        if ($q_card && $q_card->num_rows > 0) {
                            while ($card = $q_card->fetch_assoc()) {
                                ?>
                                <div class="bg-white rounded-3xl overflow-hidden shadow-sm border border-gray-100 hover:shadow-lg transition">
                                    <div class="h-24 bg-gradient-to-r from-penaburDark to-penaburBlue p-4 relative">
                                        <h4 class="text-white font-extrabold text-lg truncate z-10 relative"><?= htmlspecialchars($card['nama_mapel']); ?></h4>
                                        <span class="bg-white/20 text-white px-2 py-1 rounded text-xs font-bold"><?= htmlspecialchars($card['kode_mapel']); ?></span>
                                    </div>
                                    <div class="p-5 flex flex-col justify-between">
                                        <div class="mb-4">
                                            <p class="text-xs text-gray-500 uppercase font-bold mb-1">Kelas: <?= htmlspecialchars($card['nama_kelas']); ?></p>
                                            <p class="text-sm font-bold text-penaburDark"><i class="fa-solid fa-user-tie text-penaburGold mr-1"></i> <?= htmlspecialchars($card['pengajar']); ?></p>
                                        </div>
                                        <a href="view.php?id=<?= $card['id']; ?>" class="block w-full text-center py-2 bg-penaburBlue text-white font-bold rounded-xl shadow-md hover:bg-penaburDark transition">Buka Modul Kelas</a>
                                    </div>
                                </div>
                                <?php
                            }
                        } else {
                            echo "<div class='col-span-full p-4 bg-yellow-50 text-yellow-700 rounded-xl border border-yellow-200 font-bold'>Belum ada kelas / penugasan yang ditambahkan kurikulum untuk Anda.</div>";
                        }
                        ?>
                    </div>
                </div>

            </div>
        </main>

        <footer class="px-6 py-6 text-center text-[10px] text-gray-400 font-extrabold uppercase tracking-[0.2em] border-t border-gray-200 bg-white mt-auto">
            &copy; <?= date('Y'); ?> E-Learning SMKK BPK PENABUR.
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const lblKelas = <?= json_encode($grafik_kelas_labels ?? []); ?>;
            const dataKelas = <?= json_encode($grafik_kelas_data ?? []); ?>;
            const lblNilaiGlobal = <?= json_encode($grafik_nilai_labels ?? []); ?>;
            const dataNilaiGlobal = <?= json_encode($grafik_nilai_data ?? []); ?>;
            const lblNilaiGuru = <?= json_encode($grafik_nilai_guru_labels ?? []); ?>;
            const dataNilaiGuru = <?= json_encode($grafik_nilai_guru_data ?? []); ?>;

            if(document.getElementById('chartKelas')) {
                new Chart(document.getElementById('chartKelas'), {
                    type: 'bar',
                    data: { labels: lblKelas, datasets: [{ label: 'Siswa', data: dataKelas, backgroundColor: '#f4d35e', borderRadius: 6 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
                });
            }

            if(document.getElementById('chartNilaiGlobal')) {
                new Chart(document.getElementById('chartNilaiGlobal'), {
                    type: 'line',
                    data: { labels: lblNilaiGlobal, datasets: [{ label: 'Rata-rata Nilai', data: dataNilaiGlobal, borderColor: '#0d3b66', backgroundColor: 'rgba(13, 59, 102, 0.1)', fill: true, tension: 0.4 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
                });
            }

            if(document.getElementById('chartNilaiGuru')) {
                new Chart(document.getElementById('chartNilaiGuru'), {
                    type: 'bar',
                    data: { labels: lblNilaiGuru, datasets: [{ label: 'Rata-rata Nilai', data: dataNilaiGuru, backgroundColor: '#0d3b66', borderRadius: 6 }] },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
                });
            }
        });
    </script>
</body>
</html>
