<?php
session_start();
include 'koneksi.php';

// Cek hak akses (Hanya Superadmin, Kepsek, & Kurikulum yang boleh masuk)
$allowed_roles = ['superadmin', 'kepsek', 'kurikulum'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: dashboard");
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

// --- LOGIKA TAMBAH PENUGASAN ---
if (isset($_POST['tambah_tugas'])) {
    $id_guru = $_POST['id_guru'];
    $id_mapel = $_POST['id_mapel'];
    $id_kelas = $_POST['id_kelas'];
    
    $cek = $conn->query("SELECT id FROM penugasan WHERE id_guru='$id_guru' AND id_mapel='$id_mapel' AND id_kelas='$id_kelas'");
    
    if ($cek->num_rows > 0) {
        $pesan_error = "Gagal! Penugasan tersebut sudah ada.";
    } else {
        $insert = $conn->prepare("INSERT INTO penugasan (id_guru, id_mapel, id_kelas) VALUES (?, ?, ?)");
        $insert->bind_param("iii", $id_guru, $id_mapel, $id_kelas);
        if ($insert->execute()) {
            $pesan_sukses = "Penugasan guru berhasil ditambahkan!";
        }
    }
}

// --- LOGIKA HAPUS PENUGASAN (Support Massal via Group ID) ---
if (isset($_GET['hapus'])) {
    $ids_hapus = preg_replace('/[^0-9,]/', '', $_GET['hapus']);
    if (!empty($ids_hapus)) {
        $conn->query("DELETE FROM penugasan WHERE id IN ($ids_hapus)");
    }
    header("Location: data_penugasan");
    exit();
}

// Ambil Data Master untuk Dropdown
$gurus = $conn->query("SELECT id, nama_lengkap, role FROM users WHERE role IN ('guru', 'kurikulum', 'kepsek', 'superadmin') ORDER BY nama_lengkap ASC");
$mapels = $conn->query("SELECT id, nama_mapel, kode_mapel FROM mapel ORDER BY nama_mapel ASC");
$kelases = $conn->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");

// Ambil Data Penugasan (Grouping Kelas)
$query_tugas = $conn->query("
    SELECT u.nama_lengkap as nama_guru, u.role as role_guru, m.nama_mapel, m.kode_mapel, 
           GROUP_CONCAT(k.nama_kelas ORDER BY k.nama_kelas ASC SEPARATOR ',') as daftar_kelas,
           GROUP_CONCAT(p.id SEPARATOR ',') as daftar_id
    FROM penugasan p 
    JOIN users u ON p.id_guru = u.id 
    JOIN mapel m ON p.id_mapel = m.id 
    JOIN kelas k ON p.id_kelas = k.id 
    GROUP BY p.id_guru, p.id_mapel
    ORDER BY u.nama_lengkap ASC, m.nama_mapel ASC
");
?>

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Penugasan Guru - SIAKAD PENABUR</title>
    <base href="/">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } }
            }
        }
    </script>
</head>
<body class="font-sans antialiased bg-gray-50 text-gray-800" x-data="{ sidebarOpen: false }">

    <div class="fixed inset-0 z-40 bg-black/50" x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity style="display: none;"></div>

    <?php include 'sidebar.php'; ?>

    <div class="min-h-screen flex flex-col transition-all duration-300" :class="sidebarOpen ? 'lg:ml-64' : 'ml-0'">
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b border-gray-200 shadow-sm">
            <div class="flex items-center justify-between px-4 sm:px-8 py-4">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue transition p-2 rounded-lg bg-gray-100">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                    <h2 class="text-xl font-extrabold text-penaburDark hidden sm:block">Kelola Penugasan Guru</h2>
                </div>
                <div class="flex items-center gap-5">
                    <span class="text-sm font-extrabold text-penaburDark hidden sm:block"><?= htmlspecialchars($nama_user); ?></span>
                    <img src="assets/<?= htmlspecialchars($foto_user); ?>" class="w-10 h-10 rounded-full border-2 border-penaburGold object-cover" onerror="this.src='https://via.placeholder.com/40'">
                </div>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-8">
            <div class="max-w-7xl mx-auto grid lg:grid-cols-3 gap-8">
                
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 sticky top-24">
                        <h3 class="font-extrabold text-penaburDark mb-4"><i class="fa-solid fa-plus-circle text-penaburGold mr-2"></i> Tambah Penugasan</h3>
                        <?php if(isset($pesan_sukses)): ?><div class="bg-green-100 text-green-700 p-3 rounded-xl mb-4 text-xs font-bold"><?= $pesan_sukses; ?></div><?php endif; ?>
                        <?php if(isset($pesan_error)): ?><div class="bg-red-100 text-red-700 p-3 rounded-xl mb-4 text-xs font-bold"><?= $pesan_error; ?></div><?php endif; ?>

                        <form action="" method="POST" class="space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Guru Pengajar</label>
                                <select name="id_guru" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-penaburGold outline-none text-sm transition" required>
                                    <option value="">-- Pilih Guru --</option>
                                    <?php while($g = $gurus->fetch_assoc()): ?>
                                        <option value="<?= $g['id']; ?>"><?= htmlspecialchars($g['nama_lengkap']); ?> (<?= strtoupper($g['role']); ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Mata Pelajaran</label>
                                <select name="id_mapel" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-penaburGold outline-none text-sm transition" required>
                                    <option value="">-- Pilih Mapel --</option>
                                    <?php while($m = $mapels->fetch_assoc()): ?>
                                        <option value="<?= $m['id']; ?>"><?= htmlspecialchars($m['kode_mapel']); ?> - <?= htmlspecialchars($m['nama_mapel']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Kelas</label>
                                <select name="id_kelas" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:ring-2 focus:ring-penaburGold outline-none text-sm transition" required>
                                    <option value="">-- Pilih Kelas --</option>
                                    <?php while($k = $kelases->fetch_assoc()): ?>
                                        <option value="<?= $k['id']; ?>"><?= htmlspecialchars($k['nama_kelas']); ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" name="tambah_tugas" class="w-full py-3 bg-penaburBlue text-white font-extrabold rounded-xl hover:bg-penaburDark transition shadow-md">Tetapkan Penugasan</button>
                        </form>
                    </div>
                </div>

                <div class="lg:col-span-2">
                    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                        <div class="p-6 border-b bg-gray-50/50"><h3 class="font-extrabold text-penaburDark"><i class="fa-solid fa-table-list text-penaburGold mr-2"></i> Daftar Penugasan Aktif</h3></div>
                        <div class="p-6 overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="text-gray-500 uppercase text-[10px] font-bold border-b">
                                    <tr><th class="px-4 py-3">Guru</th><th class="px-4 py-3">Mata Pelajaran</th><th class="px-4 py-3">Kelas</th><th class="px-4 py-3 text-center">Aksi</th></tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php if($query_tugas->num_rows > 0): ?>
                                        <?php while($row = $query_tugas->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-4 py-3 font-bold text-penaburDark"><?= htmlspecialchars($row['nama_guru']); ?></td>
                                            <td class="px-4 py-3 text-penaburBlue font-bold"><?= htmlspecialchars($row['nama_mapel']); ?></td>
                                            <td class="px-4 py-3">
                                                <div class="flex flex-wrap gap-1">
                                                    <?php foreach(explode(',', $row['daftar_kelas']) as $kls): ?>
                                                        <span class="px-2 py-1 bg-white border border-gray-200 text-gray-600 font-bold text-[10px] rounded-lg shadow-sm"><?= htmlspecialchars($kls); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <a href="data_penugasan?hapus=<?= $row['daftar_id']; ?>" onclick="return confirm('Batalkan semua kelas untuk mapel ini?')" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-lg font-bold text-xs transition"><i class="fa-solid fa-trash"></i> Batal</a>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-gray-400 font-bold border-2 border-dashed border-gray-100 rounded-xl">Belum ada penugasan guru.</td>
                                        </tr>
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
</body>
</html>