<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] != 'superadmin' && $_SESSION['role'] != 'kurikulum')) {
    header("Location: dashboard"); exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$nama_user = $_SESSION['nama_lengkap'];
$foto_user = $_SESSION['foto_profil'] ?? 'default.png';

// --- LOGIKA TAMBAH MAPEL ---
if (isset($_POST['tambah_mapel'])) {
    $kode = $_POST['kode_mapel'];
    $nama = $_POST['nama_mapel'];
    $kkm_x  = $_POST['kkm_x'];
    $kkm_xi = $_POST['kkm_xi'];
    $kkm_xii = $_POST['kkm_xii'];
    
    $insert = $conn->prepare("INSERT INTO mapel (kode_mapel, nama_mapel, kkm_x, kkm_xi, kkm_xii) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("ssiii", $kode, $nama, $kkm_x, $kkm_xi, $kkm_xii);
    if ($insert->execute()) { $pesan_sukses = "Mata Pelajaran berhasil ditambahkan!"; } 
    else { $pesan_error = "Gagal! Kode Mapel mungkin sudah ada."; }
}

// --- LOGIKA EDIT MAPEL ---
if (isset($_POST['edit_mapel'])) {
    $id_edit = $_POST['id_edit'];
    $kode = $_POST['kode_mapel_edit'];
    $nama = $_POST['nama_mapel_edit'];
    $kkm_x  = $_POST['kkm_x_edit'];
    $kkm_xi = $_POST['kkm_xi_edit'];
    $kkm_xii = $_POST['kkm_xii_edit'];
    
    $update = $conn->prepare("UPDATE mapel SET kode_mapel=?, nama_mapel=?, kkm_x=?, kkm_xi=?, kkm_xii=? WHERE id=?");
    $update->bind_param("ssiiii", $kode, $nama, $kkm_x, $kkm_xi, $kkm_xii, $id_edit);
    if ($update->execute()) { $pesan_sukses = "Mata Pelajaran berhasil diubah!"; } 
    else { $pesan_error = "Gagal mengubah data."; }
}

if (isset($_GET['hapus'])) {
    $id_hapus = $_GET['hapus'];
    $delete = $conn->prepare("DELETE FROM mapel WHERE id = ?");
    $delete->bind_param("i", $id_hapus);
    $delete->execute();
    header("Location: data_mapel"); exit();
}

$search = $_GET['q'] ?? '';
$query_mapel = $conn->query("SELECT * FROM mapel WHERE nama_mapel LIKE '%$search%' OR kode_mapel LIKE '%$search%' ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <title>Kelola Mapel - SIAKAD PENABUR</title>
    <base href="/">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>tailwind.config = { theme: { fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] }, extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } } } }</script>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-800" x-data="{ sidebarOpen: false, modalEdit: false, editId: '', editKode: '', editNama: '', editKkmX: '', editKkmXi: '', editKkmXii: '' }">

    <div class="fixed inset-0 z-40 bg-black/50" x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity style="display: none;"></div>

    <?php include 'sidebar.php'; ?>

    <div class="min-h-screen flex flex-col transition-all duration-300" :class="sidebarOpen ? 'lg:ml-64' : 'ml-0'">
        
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm">
            <div class="flex items-center justify-between px-4 sm:px-8 py-4">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue transition p-2 rounded-lg bg-gray-100"><i class="fa-solid fa-bars text-xl"></i></button>
                    <h2 class="text-xl font-extrabold text-penaburDark hidden sm:block">Kelola Mata Pelajaran & KKM</h2>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm font-extrabold text-penaburDark hidden sm:block"><?= htmlspecialchars($nama_user); ?></span>
                    <img src="assets/<?= htmlspecialchars($foto_user); ?>" class="w-10 h-10 rounded-full border-2 border-penaburGold shadow-md bg-gray-100" onerror="this.src='https://via.placeholder.com/40'">
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-8">
            <div class="max-w-7xl mx-auto grid lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sticky top-24">
                        <h3 class="font-extrabold text-penaburDark mb-4"><i class="fa-solid fa-plus-circle text-penaburGold mr-2"></i> Tambah Course Baru</h3>
                        <?php if(isset($pesan_sukses)): ?><div class="bg-green-100 text-green-700 p-3 rounded-xl mb-4 text-xs font-bold"><?= $pesan_sukses; ?></div><?php endif; ?>
                        <?php if(isset($pesan_error)): ?><div class="bg-red-100 text-red-700 p-3 rounded-xl mb-4 text-xs font-bold"><?= $pesan_error; ?></div><?php endif; ?>
                        <form action="" method="POST" class="space-y-4">
                            <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Kode Mapel</label><input type="text" name="kode_mapel" class="w-full px-4 py-2 rounded-xl border focus:ring-2 focus:ring-penaburGold outline-none" required></div>
                            <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nama Mata Pelajaran</label><input type="text" name="nama_mapel" class="w-full px-4 py-2 rounded-xl border focus:ring-2 focus:ring-penaburGold outline-none" required></div>
                            
                            <div class="grid grid-cols-3 gap-2">
                                <div><label class="block text-[10px] font-bold text-gray-600 uppercase mb-1 text-center">KKM KLS 10</label><input type="number" name="kkm_x" value="75" class="w-full px-2 py-2 rounded-xl border focus:ring-2 focus:ring-penaburGold outline-none text-center text-sm" required></div>
                                <div><label class="block text-[10px] font-bold text-gray-600 uppercase mb-1 text-center">KKM KLS 11</label><input type="number" name="kkm_xi" value="75" class="w-full px-2 py-2 rounded-xl border focus:ring-2 focus:ring-penaburGold outline-none text-center text-sm" required></div>
                                <div><label class="block text-[10px] font-bold text-gray-600 uppercase mb-1 text-center">KKM KLS 12</label><input type="number" name="kkm_xii" value="75" class="w-full px-2 py-2 rounded-xl border focus:ring-2 focus:ring-penaburGold outline-none text-center text-sm" required></div>
                            </div>

                            <button type="submit" name="tambah_mapel" class="w-full py-3 bg-penaburBlue text-white font-extrabold rounded-xl hover:bg-penaburDark transition shadow-md mt-2">Simpan Mata Pelajaran</button>
                        </form>
                    </div>
                </div>
                
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-6 border-b bg-gray-50/50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <h3 class="font-extrabold text-penaburDark"><i class="fa-solid fa-table-list text-penaburGold mr-2"></i> Daftar Course Tersedia</h3>
                            <form method="GET" class="flex w-full sm:w-auto gap-2">
                                <input type="text" name="q" value="<?= htmlspecialchars($search); ?>" placeholder="Cari Mapel..." class="px-4 py-2 rounded-xl border outline-none text-sm w-full focus:ring-2 focus:ring-penaburGold">
                                <button type="submit" class="bg-penaburDark text-white px-4 py-2 rounded-xl font-bold text-sm hover:bg-penaburBlue">Cari</button>
                            </form>
                        </div>
                        
                        <div class="p-6 overflow-x-auto">
                            <table class="w-full text-left text-sm border-collapse">
                                <thead class="text-gray-500 uppercase text-[10px] font-bold border-b bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3" rowspan="2">Kode</th>
                                        <th class="px-4 py-3 border-r" rowspan="2">Nama Mata Pelajaran</th>
                                        <th class="px-4 py-2 text-center border-b" colspan="3">Nilai KKM</th>
                                        <th class="px-4 py-3 text-right" rowspan="2">Aksi</th>
                                    </tr>
                                    <tr>
                                        <th class="px-2 py-1 text-center bg-gray-100 border-r border-gray-200">KLS 10</th>
                                        <th class="px-2 py-1 text-center bg-gray-100 border-r border-gray-200">KLS 11</th>
                                        <th class="px-2 py-1 text-center bg-gray-100">KLS 12</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if($query_mapel->num_rows > 0): while($row = $query_mapel->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-4 py-3 font-bold text-penaburBlue"><?= htmlspecialchars($row['kode_mapel']); ?></td>
                                            <td class="px-4 py-3 font-bold text-gray-700 border-r"><?= htmlspecialchars($row['nama_mapel']); ?></td>
                                            
                                            <td class="px-2 py-3 text-center border-r"><span class="px-2 py-1 bg-yellow-50 text-yellow-700 rounded text-xs font-bold"><?= htmlspecialchars($row['kkm_x']); ?></span></td>
                                            <td class="px-2 py-3 text-center border-r"><span class="px-2 py-1 bg-yellow-50 text-yellow-700 rounded text-xs font-bold"><?= htmlspecialchars($row['kkm_xi']); ?></span></td>
                                            <td class="px-2 py-3 text-center"><span class="px-2 py-1 bg-yellow-50 text-yellow-700 rounded text-xs font-bold"><?= htmlspecialchars($row['kkm_xii']); ?></span></td>
                                            
                                            <td class="px-4 py-3 text-right flex justify-end gap-2">
                                                <button @click="modalEdit = true; editId = '<?= $row['id']; ?>'; editKode = '<?= addslashes(htmlspecialchars($row['kode_mapel'])); ?>'; editNama = '<?= addslashes(htmlspecialchars($row['nama_mapel'])); ?>'; editKkmX = '<?= htmlspecialchars($row['kkm_x']); ?>'; editKkmXi = '<?= htmlspecialchars($row['kkm_xi']); ?>'; editKkmXii = '<?= htmlspecialchars($row['kkm_xii']); ?>'" class="bg-blue-50 text-penaburBlue hover:bg-blue-100 px-3 py-1.5 rounded-lg font-bold"><i class="fa-solid fa-edit"></i></button>
                                                <a href="data_mapel?hapus=<?= $row['id']; ?>" onclick="return confirm('Yakin hapus?')" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg font-bold"><i class="fa-solid fa-trash"></i></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; else: ?>
                                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 font-bold border-2 border-dashed rounded-xl">Mata pelajaran tidak ditemukan.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
        <footer class="px-6 py-6 text-center text-[10px] text-gray-400 font-extrabold uppercase tracking-[0.2em] border-t border-gray-200 bg-white mt-auto">
            &copy; <?= date('Y'); ?> E-Learning SMKK BPK PENABUR.
        </footer>
    </div>

    <div x-show="modalEdit" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="absolute inset-0 bg-penaburDark/60 backdrop-blur-sm" @click="modalEdit = false" x-transition.opacity></div>
        <div class="relative bg-white rounded-3xl p-6 w-full max-w-md shadow-2xl" x-transition>
            <div class="flex justify-between items-center mb-6 border-b pb-4">
                <h3 class="font-extrabold text-xl text-penaburDark"><i class="fa-solid fa-edit text-penaburGold mr-2"></i> Edit Mapel</h3>
                <button @click="modalEdit = false" class="text-gray-400 hover:text-red-500"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <form action="" method="POST" class="space-y-4">
                <input type="hidden" name="id_edit" x-model="editId">
                <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Kode Mapel</label><input type="text" name="kode_mapel_edit" x-model="editKode" class="w-full px-4 py-2.5 rounded-xl border focus:ring-2 focus:ring-penaburGold outline-none font-bold" required></div>
                <div><label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nama</label><input type="text" name="nama_mapel_edit" x-model="editNama" class="w-full px-4 py-2.5 rounded-xl border focus:ring-2 focus:ring-penaburGold outline-none font-bold" required></div>
                
                <div class="grid grid-cols-3 gap-2">
                    <div><label class="block text-[10px] font-bold text-gray-600 uppercase mb-1 text-center">KKM KLS 10</label><input type="number" name="kkm_x_edit" x-model="editKkmX" class="w-full px-2 py-2.5 rounded-xl border focus:ring-2 focus:ring-penaburGold outline-none text-center font-bold" required></div>
                    <div><label class="block text-[10px] font-bold text-gray-600 uppercase mb-1 text-center">KKM KLS 11</label><input type="number" name="kkm_xi_edit" x-model="editKkmXi" class="w-full px-2 py-2.5 rounded-xl border focus:ring-2 focus:ring-penaburGold outline-none text-center font-bold" required></div>
                    <div><label class="block text-[10px] font-bold text-gray-600 uppercase mb-1 text-center">KKM KLS 12</label><input type="number" name="kkm_xii_edit" x-model="editKkmXii" class="w-full px-2 py-2.5 rounded-xl border focus:ring-2 focus:ring-penaburGold outline-none text-center font-bold" required></div>
                </div>

                <div class="flex gap-3 justify-end mt-8 pt-4 border-t border-gray-100">
                    <button type="button" @click="modalEdit = false" class="px-5 py-2.5 bg-gray-100 text-gray-600 font-bold rounded-xl hover:bg-gray-200">Batal</button>
                    <button type="submit" name="edit_mapel" class="px-5 py-2.5 bg-penaburBlue text-white font-extrabold rounded-xl hover:bg-penaburDark">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>