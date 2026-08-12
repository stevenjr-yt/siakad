<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once 'koneksi.php'; 

// Fungsi Pembuat Singkatan Mata Pelajaran
if(!function_exists('singkatMapel')){
    function singkatMapel($str){
        $str_clean = str_ireplace([' dan ', ' & ', ' atau '], ' ', trim($str));
        $words = explode(' ', $str_clean);
        $res = '';
        foreach($words as $w){
            $w = trim($w);
            if($w != '') $res .= strtoupper($w[0]);
        }
        // Jika hasilnya cuma 1 huruf tapi aslinya panjang (misal "Matematika" -> "MAT")
        if(strlen($res) == 1 && strlen(str_replace(' ', '', $str_clean)) >= 3){
            return strtoupper(substr(str_replace(' ', '', $str_clean), 0, 3));
        }
        return $res;
    }
}

$user_id_sidebar = $_SESSION['user_id'] ?? 0;
$role_sidebar = $_SESSION['role'] ?? '';
$current_page = basename($_SERVER['PHP_SELF']); 

// Ambil daftar kelas yang diampu user ini (untuk menu Input Nilai Akhir)
$kelas_ampu_sidebar = $conn->query("SELECT p.id as id_penugasan, m.nama_mapel, k.nama_kelas 
                                    FROM penugasan p 
                                    JOIN mapel m ON p.id_mapel = m.id 
                                    JOIN kelas k ON p.id_kelas = k.id 
                                    WHERE p.id_guru = '$user_id_sidebar' 
                                    ORDER BY m.nama_mapel ASC");

// Ambil daftar kelas E-Learning (Dinamis untuk Murid & Guru di Sidebar)
if ($role_sidebar == 'siswa') {
    $id_kelas_siswa_sidebar = $_SESSION['id_kelas'] ?? 0;
    $elearning_sidebar = $conn->query("SELECT p.id as id_penugasan, m.nama_mapel, k.nama_kelas 
                                       FROM penugasan p 
                                       JOIN mapel m ON p.id_mapel = m.id 
                                       JOIN kelas k ON p.id_kelas = k.id 
                                       WHERE p.id_kelas = '$id_kelas_siswa_sidebar' 
                                       ORDER BY m.nama_mapel ASC");
} else {
    $elearning_sidebar = $conn->query("SELECT p.id as id_penugasan, m.nama_mapel, k.nama_kelas 
                                       FROM penugasan p 
                                       JOIN mapel m ON p.id_mapel = m.id 
                                       JOIN kelas k ON p.id_kelas = k.id 
                                       WHERE p.id_guru = '$user_id_sidebar' 
                                       ORDER BY m.nama_mapel ASC");
}
?>

<aside class="fixed inset-y-0 left-0 z-50 w-64 bg-penaburDark text-white overflow-y-auto border-r border-white/10 transform transition-transform duration-300" :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'">
    <div class="flex items-center justify-between px-6 py-5 border-b border-white/10 sticky top-0 bg-penaburDark z-10">
        <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-full bg-white flex items-center justify-center p-1 ring-2 ring-penaburGold shadow-lg">
                <img src="assets/logo-penabur.png" class="h-8 w-8 object-contain rounded-full" onerror="this.src='https://via.placeholder.com/32'">
            </div>
            <div>
                <h1 class="text-[11px] font-extrabold text-penaburGold leading-tight">SIAKAD SMKK BPK PENABUR</h1>
                <p class="text-[9px] text-gray-400 font-bold uppercase">Bandar Lampung</p>
            </div>
        </div>
        <button @click="sidebarOpen = false" class="lg:hidden text-gray-400 hover:text-white"><i class="fa-solid fa-xmark text-xl"></i></button>
    </div>

    <nav class="mt-4 px-3 space-y-1 mb-8">
        <a href="dashboard.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= ($current_page == 'dashboard.php' || $current_page == 'dashboard') ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-white hover:bg-white/5'; ?>">
            <i class="fa-solid fa-house w-5 text-center"></i> Dashboard
        </a>

        <?php if (in_array($role_sidebar, ['superadmin', 'kepsek', 'kurikulum'])): ?>
            <div class="pt-5 pb-2 px-4"><p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-[0.2em]">Manajemen KBM</p></div>
            <a href="data_pengguna.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= ($current_page == 'data_pengguna.php') ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-white hover:bg-white/5'; ?>">
                <i class="fa-solid fa-users w-5 text-center"></i> Data Pengguna
            </a>
            <a href="data_mapel.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= ($current_page == 'data_mapel.php' || $current_page == 'data_mapel') ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-white hover:bg-white/5'; ?>">
                <i class="fa-solid fa-book-bookmark w-5 text-center"></i> Mata Pelajaran
            </a>
            <a href="data_penugasan.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= ($current_page == 'data_penugasan.php' || $current_page == 'data_penugasan') ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-white hover:bg-white/5'; ?>">
                <i class="fa-solid fa-user-tie w-5 text-center"></i> Penugasan Guru
            </a>
            <a href="data_jadwal.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= ($current_page == 'data_jadwal.php' || $current_page == 'data_jadwal') ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-white hover:bg-white/5'; ?>">
                <i class="fa-regular fa-calendar-days w-5 text-center"></i> Jadwal Pelajaran
            </a>

            <div class="pt-5 pb-2 px-4"><p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-[0.2em]">Ledger Nilai (STS)</p></div>
            <a href="ledger.php?level=X" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= (isset($_GET['level']) && $_GET['level'] == 'X') ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-white hover:bg-white/5'; ?>">
                <i class="fa-solid fa-table w-5 text-center"></i> Ledger Kelas X
            </a>
            <a href="ledger.php?level=XI" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= (isset($_GET['level']) && $_GET['level'] == 'XI') ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-white hover:bg-white/5'; ?>">
                <i class="fa-solid fa-table w-5 text-center"></i> Ledger Kelas XI
            </a>
            <a href="ledger.php?level=XII" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= (isset($_GET['level']) && $_GET['level'] == 'XII') ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-white hover:bg-white/5'; ?>">
                <i class="fa-solid fa-table w-5 text-center"></i> Ledger Kelas XII
            </a>

            <div class="pt-5 pb-2 px-4"><p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-[0.2em]">Pengaturan</p></div>
            <a href="setting_nilai.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= ($current_page == 'setting_nilai.php' || $current_page == 'setting_nilai') ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-white hover:bg-white/5'; ?>">
                <i class="fa-solid fa-calendar-check w-5 text-center"></i> Jadwal Input Nilai
            </a>
        <?php endif; ?>

        <div class="pt-5 pb-2 px-4"><p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-[0.2em]">Ruang Kelas E-Learning</p></div>
        <a href="semua_kelas.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= ($current_page == 'semua_kelas.php') ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-white hover:bg-white/5'; ?>">
            <i class="fa-solid fa-border-all w-5 text-center"></i> Semua Kelas
        </a>
        
        <?php if ($elearning_sidebar && $elearning_sidebar->num_rows > 0): ?>
            <?php while($el = $elearning_sidebar->fetch_assoc()): ?>
                <a href="view.php?id=<?= $el['id_penugasan']; ?>" title="<?= htmlspecialchars($el['nama_mapel']); ?> (<?= htmlspecialchars($el['nama_kelas']); ?>)" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= (isset($_GET['id']) && $_GET['id'] == $el['id_penugasan'] && strpos($current_page, 'view') !== false) ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-gray-300 hover:bg-white/5 hover:text-white'; ?>">
                    <div class="bg-penaburGold/20 p-1.5 rounded-lg flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-book-open-reader text-penaburGold w-4 text-center"></i>
                    </div>
                    <span class="truncate"><?= singkatMapel($el['nama_mapel']); ?>-<?= htmlspecialchars($el['nama_kelas']); ?></span>
                </a>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="px-4 py-2 text-xs text-gray-500 italic">Belum ada kelas</div>
        <?php endif; ?>

        <?php if (in_array($role_sidebar, ['guru', 'kurikulum', 'kepsek', 'superadmin']) && $kelas_ampu_sidebar && $kelas_ampu_sidebar->num_rows > 0): ?>
            <?php $kelas_ampu_sidebar->data_seek(0); ?>
            <div class="pt-5 pb-2 px-4"><p class="text-[10px] font-extrabold text-gray-500 uppercase tracking-[0.2em]">Input Nilai Akhir</p></div>
            <?php while($ka = $kelas_ampu_sidebar->fetch_assoc()): ?>
                <a href="input_nilai.php?kelas=<?= $ka['id_penugasan']; ?>" title="<?= htmlspecialchars($ka['nama_mapel']); ?> (<?= htmlspecialchars($ka['nama_kelas']); ?>)" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= (isset($_GET['kelas']) && $_GET['kelas'] == $ka['id_penugasan']) ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-gray-300 hover:bg-white/5 hover:text-white'; ?>">
                    <i class="fa-solid fa-pen-to-square w-5 text-center text-blue-300"></i> 
                    <span class="truncate"><?= singkatMapel($ka['nama_mapel']); ?>-<?= htmlspecialchars($ka['nama_kelas']); ?></span>
                </a>
            <?php endwhile; ?>
        <?php endif; ?>

        <div class="mt-8 pt-4 border-t border-white/10 px-4">
            <a href="profil.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm transition <?= ($current_page == 'profil.php' || $current_page == 'profil') ? 'bg-white/10 text-penaburGold font-bold shadow-inner' : 'text-gray-400 hover:bg-white/5 hover:text-white'; ?>">
                <i class="fa-solid fa-user-gear w-5 text-center"></i> Profil Saya
            </a>
            <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-sm text-red-400 hover:bg-red-500/10 transition mt-2">
                <i class="fa-solid fa-power-off w-5 text-center"></i> Keluar
            </a>
        </div>
    </nav>
</aside>