<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'kepsek', 'kurikulum'])) { header("Location: dashboard"); exit(); }
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$nama_user = $_SESSION['nama_lengkap'];

// Injeksi kolom aman untuk MariaDB/MySQL modern
@$conn->query("ALTER TABLE pengaturan_nilai ADD COLUMN IF NOT EXISTS status ENUM('aktif', 'nonaktif') DEFAULT 'nonaktif'");
@$conn->query("ALTER TABLE pengaturan_nilai ADD COLUMN IF NOT EXISTS jenis_ujian VARCHAR(10) DEFAULT 'STS'");

// Variabel untuk nangkep error/sukses
$pesan_error = null;
$pesan_sukses = null;

// Aksi Hapus
if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    $conn->query("DELETE FROM pengaturan_nilai WHERE id=$id_hapus");
    header("Location: setting_nilai.php?msg=hapus");
    exit;
}

// Aksi Set Aktif
if (isset($_GET['set_aktif'])) {
    $id_aktif = (int)$_GET['set_aktif'];
    $conn->query("UPDATE pengaturan_nilai SET status='nonaktif'"); 
    $conn->query("UPDATE pengaturan_nilai SET status='aktif' WHERE id=$id_aktif"); 
    header("Location: setting_nilai.php?msg=aktif");
    exit;
}

// Aksi Simpan / Update
if (isset($_POST['simpan_jadwal'])) {
    $mulai = $conn->real_escape_string($_POST['tanggal_mulai']);
    $selesai = $conn->real_escape_string($_POST['tanggal_selesai']);
    $semester = $conn->real_escape_string($_POST['semester']);
    $tahun = $conn->real_escape_string($_POST['tahun_ajaran']);
    $jenis = $conn->real_escape_string($_POST['jenis_ujian']);
    $id_edit = $_POST['id_edit'] ?? ''; 
    
    // Deteksi Edit
    if (trim($id_edit) !== '') {
        $id_edit = (int)$id_edit;
        $q_update = "UPDATE pengaturan_nilai SET tanggal_mulai='$mulai', tanggal_selesai='$selesai', semester='$semester', tahun_ajaran='$tahun', jenis_ujian='$jenis' WHERE id=$id_edit";
        if ($conn->query($q_update)) {
            header("Location: setting_nilai.php?msg=edit");
            exit;
        } else {
            $pesan_error = "Gagal memperbarui data! Error DB: " . $conn->error;
        }
    } else {
        // Mode Tambah Baru
        $cek_ada = $conn->query("SELECT id FROM pengaturan_nilai");
        $status_baru = ($cek_ada && $cek_ada->num_rows == 0) ? 'aktif' : 'nonaktif';
        
        // Skenario 1: Coba Insert normal (Mengandalkan Auto Increment)
        $q_insert = "INSERT INTO pengaturan_nilai (tanggal_mulai, tanggal_selesai, semester, tahun_ajaran, jenis_ujian, status) VALUES ('$mulai', '$selesai', '$semester', '$tahun', '$jenis', '$status_baru')";
        
        if ($conn->query($q_insert)) {
            header("Location: setting_nilai.php?msg=tambah");
            exit;
        } else {
            // Skenario 2: Kalau gagal (karena tabel gak diset Auto Increment), kita paksa bikin ID urut sendiri
            $cek_max = $conn->query("SELECT MAX(id) as max_id FROM pengaturan_nilai")->fetch_assoc();
            $next_id = ($cek_max['max_id'] ?? 0) + 1;
            
            $q_insert2 = "INSERT INTO pengaturan_nilai (id, tanggal_mulai, tanggal_selesai, semester, tahun_ajaran, jenis_ujian, status) VALUES ('$next_id', '$mulai', '$selesai', '$semester', '$tahun', '$jenis', '$status_baru')";
            
            if ($conn->query($q_insert2)) {
                header("Location: setting_nilai.php?msg=tambah");
                exit;
            } else {
                $pesan_error = "Gagal nyimpen data baru! Error DB: " . $conn->error;
            }
        }
    }
}

// Tangkap pesan sukses dari redirect URL
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'hapus') $pesan_sukses = "Periode penilaian berhasil dihapus!";
    if ($_GET['msg'] == 'aktif') $pesan_sukses = "Periode penilaian berhasil diaktifkan!";
    if ($_GET['msg'] == 'edit') $pesan_sukses = "Jadwal & Periode berhasil diperbarui!";
    if ($_GET['msg'] == 'tambah') $pesan_sukses = "Jadwal & Periode baru berhasil ditambahkan!";
}

// Ambil data untuk form edit
$data_form = ['tanggal_mulai' => date('Y-m-d'), 'tanggal_selesai' => date('Y-m-d'), 'semester' => 'Ganjil', 'tahun_ajaran' => '2025/2026', 'jenis_ujian' => 'STS'];
$id_edit_form = '';
if (isset($_GET['edit'])) {
    $id_edit_form = (int)$_GET['edit'];
    $q_edit = $conn->query("SELECT * FROM pengaturan_nilai WHERE id=$id_edit_form");
    if($q_edit && $q_edit->num_rows > 0) {
        $data_form = $q_edit->fetch_assoc();
    }
}

// Ambil semua daftar periode untuk tabel di bawah
$list_periode = $conn->query("SELECT * FROM pengaturan_nilai ORDER BY status ASC, tahun_ajaran DESC, semester DESC, id DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Jadwal Penilaian - SIAKAD PENABUR</title>
    <base href="/">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>tailwind.config = { theme: { extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } } } }</script>
</head>
<body class="bg-gray-50 font-sans text-gray-800" x-data="{ sidebarOpen: false }">

    <div class="fixed inset-0 z-40 bg-black/50" x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity style="display: none;"></div>
    <?php include 'sidebar.php'; ?>

    <div class="min-h-screen flex flex-col transition-all duration-300" :class="sidebarOpen ? 'lg:ml-64' : 'ml-0'">
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm p-4 sm:px-8 flex items-center">
            <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue transition p-2 rounded-lg bg-gray-100 mr-4"><i class="fa-solid fa-bars text-xl"></i></button>
            <h2 class="text-xl font-extrabold text-penaburDark">Set Jadwal & Periode Input Nilai</h2>
        </header>

        <main class="flex-1 p-4 sm:p-8 max-w-6xl mx-auto w-full space-y-6">
            <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100">
                <div class="flex items-center gap-4 mb-6 border-b pb-4">
                    <div class="p-4 bg-penaburGold/20 rounded-full text-penaburDark"><i class="fa-solid fa-calendar-check text-2xl"></i></div>
                    <div class="flex-1">
                        <h3 class="text-xl font-black text-penaburDark"><?= !empty($id_edit_form) ? 'Edit Periode Penilaian' : 'Manajemen Portal E-Rapor'; ?></h3>
                        <p class="text-sm text-gray-500">Buka akses input nilai untuk STS (Tengah Semester) atau SAS (Akhir Semester).</p>
                    </div>
                    <?php if(!empty($id_edit_form)): ?>
                        <a href="setting_nilai.php" class="bg-gray-100 text-gray-600 px-4 py-2 rounded-xl text-sm font-bold hover:bg-gray-200"><i class="fa-solid fa-xmark mr-1"></i> Batal Edit</a>
                    <?php endif; ?>
                </div>

                <?php if($pesan_sukses): ?>
                    <div class="bg-green-100 text-green-700 p-4 rounded-xl mb-6 font-bold border border-green-200"><i class="fa-solid fa-check-circle mr-2"></i> <?= $pesan_sukses; ?></div>
                <?php endif; ?>
                
                <?php if($pesan_error): ?>
                    <div class="bg-red-100 text-red-700 p-4 rounded-xl mb-6 font-bold border border-red-200 shadow-sm"><i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= htmlspecialchars($pesan_error); ?></div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="id_edit" value="<?= htmlspecialchars($id_edit_form); ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Event Penilaian (Dibuka Untuk)</label>
                            <select name="jenis_ujian" class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-penaburBlue outline-none bg-yellow-50 text-penaburDark font-bold border-yellow-200">
                                <option value="STS" <?= ($data_form['jenis_ujian'] ?? 'STS') == 'STS' ? 'selected' : ''; ?>>Sumatif Tengah Semester (STS) - 3 Bulan Pertama</option>
                                <option value="SAS" <?= ($data_form['jenis_ujian'] ?? '') == 'SAS' ? 'selected' : ''; ?>>Sumatif Akhir Semester (SAS) - 3 Bulan Terakhir</option>
                            </select>
                        </div>
                        <div><label class="block text-xs font-bold text-gray-500 uppercase mb-2">Tanggal Buka Akses</label><input type="date" name="tanggal_mulai" value="<?= htmlspecialchars($data_form['tanggal_mulai']); ?>" class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-penaburBlue outline-none" required></div>
                        <div><label class="block text-xs font-bold text-gray-500 uppercase mb-2">Tanggal Tutup Akses</label><input type="date" name="tanggal_selesai" value="<?= htmlspecialchars($data_form['tanggal_selesai']); ?>" class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-penaburBlue outline-none" required></div>
                        <div><label class="block text-xs font-bold text-gray-500 uppercase mb-2">Semester</label>
                            <select name="semester" class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-penaburBlue outline-none font-bold">
                                <option value="Ganjil" <?= ($data_form['semester'] ?? 'Ganjil') == 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                                <option value="Genap" <?= ($data_form['semester'] ?? '') == 'Genap' ? 'selected' : ''; ?>>Genap</option>
                            </select>
                        </div>
                        <div><label class="block text-xs font-bold text-gray-500 uppercase mb-2">Tahun Ajaran</label><input type="text" name="tahun_ajaran" value="<?= htmlspecialchars($data_form['tahun_ajaran']); ?>" class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-penaburBlue outline-none font-bold" required></div>
                    </div>
                    <button type="submit" name="simpan_jadwal" class="w-full py-4 bg-penaburBlue text-white font-extrabold rounded-xl shadow-lg hover:bg-penaburDark transition mt-4"><i class="fa-solid fa-save mr-2"></i> <?= !empty($id_edit_form) ? 'Perbarui Pengaturan Portal' : 'Simpan Pengaturan Portal Baru'; ?></button>
                </form>
            </div>

            <div class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100">
                <h3 class="text-lg font-black text-penaburDark mb-4"><i class="fa-solid fa-list-check text-penaburGold mr-2"></i> Daftar Periode & Status Portal</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left border-collapse">
                        <thead class="bg-gray-50 text-[10px] font-black text-gray-500 uppercase tracking-widest border-b border-t">
                            <tr>
                                <th class="px-4 py-3 border-r">Periode (Tahun / Smt)</th>
                                <th class="px-4 py-3 border-r text-center">Jenis (3 Bulan)</th>
                                <th class="px-4 py-3 border-r text-center">Tgl Mulai</th>
                                <th class="px-4 py-3 border-r text-center">Tgl Tutup</th>
                                <th class="px-4 py-3 border-r text-center">Status</th>
                                <th class="px-4 py-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if($list_periode && $list_periode->num_rows > 0): ?>
                                <?php while($lp = $list_periode->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition">
                                        <td class="px-4 py-3 font-bold text-penaburDark border-r whitespace-nowrap"><?= htmlspecialchars($lp['tahun_ajaran']); ?> - <?= htmlspecialchars($lp['semester']); ?></td>
                                        <td class="px-4 py-3 text-center font-extrabold border-r text-penaburBlue"><?= htmlspecialchars($lp['jenis_ujian'] ?? 'STS'); ?></td>
                                        <td class="px-4 py-3 text-center border-r"><?= date('d M Y', strtotime($lp['tanggal_mulai'])); ?></td>
                                        <td class="px-4 py-3 text-center border-r"><?= date('d M Y', strtotime($lp['tanggal_selesai'])); ?></td>
                                        <td class="px-4 py-3 text-center border-r">
                                            <?php if($lp['status'] == 'aktif'): ?>
                                                <span class="bg-green-100 text-green-700 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider"><i class="fa-solid fa-circle-check mr-1"></i> Aktif</span>
                                            <?php else: ?>
                                                <span class="bg-gray-100 text-gray-500 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider"><i class="fa-solid fa-ban mr-1"></i> Nonaktif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 flex gap-2 justify-center">
                                            <?php if($lp['status'] != 'aktif'): ?>
                                                <a href="setting_nilai.php?set_aktif=<?= $lp['id']; ?>" class="bg-green-500 hover:bg-green-600 text-white p-2 rounded-lg text-xs transition tooltip" title="Aktifkan Periode Ini"><i class="fa-solid fa-power-off"></i></a>
                                            <?php endif; ?>
                                            <a href="setting_nilai.php?edit=<?= $lp['id']; ?>" class="bg-yellow-400 hover:bg-yellow-500 text-penaburDark p-2 rounded-lg text-xs transition"><i class="fa-solid fa-pen"></i></a>
                                            <a href="setting_nilai.php?hapus=<?= $lp['id']; ?>" onclick="return confirm('Yakin ingin menghapus periode ini?')" class="bg-red-500 hover:bg-red-600 text-white p-2 rounded-lg text-xs transition"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 font-bold italic">Belum ada data periode yang dibuat.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>