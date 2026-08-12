<?php
session_start();
include 'koneksi.php';

// ... (Bagian atas tetep sama persis kayak file lu sebelumnya) ...
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'kepsek', 'kurikulum'])) {
    header("Location: dashboard");
    exit();
}

$user_id = $_SESSION['user_id'] ?? 0;
$role = $_SESSION['role'];
$nama_user = $_SESSION['nama_lengkap'];
$foto_user = $_SESSION['foto_profil'] ?? 'default.png';

if (isset($_POST['simpan_jadwal'])) {
    $id_mapel = $_POST['id_mapel'] != '' ? $_POST['id_mapel'] : null;
    $id_kelas = $_POST['id_kelas'] != '' ? $_POST['id_kelas'] : null;
    $hari = $_POST['hari'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    $is_istirahat = isset($_POST['is_istirahat']) ? 1 : 0;
    
    $insert = $conn->prepare("INSERT INTO jadwal_pelajaran (id_mapel, id_kelas, hari, jam_mulai, jam_selesai, is_istirahat) VALUES (?, ?, ?, ?, ?, ?)");
    $insert->bind_param("iisssi", $id_mapel, $id_kelas, $hari, $jam_mulai, $jam_selesai, $is_istirahat);
    if ($insert->execute()) {
        $pesan_sukses = "Jadwal berhasil ditambahkan!";
    } else {
        $pesan_error = "Gagal menyimpan jadwal.";
    }
}

if (isset($_GET['hapus'])) {
    $id_hapus = (int)$_GET['hapus'];
    $conn->query("DELETE FROM jadwal_pelajaran WHERE id = '$id_hapus'");
    header("Location: data_jadwal");
    exit();
}

$mapels_list = $conn->query("SELECT id, nama_mapel, kode_mapel FROM mapel ORDER BY nama_mapel ASC");
$kelases_list = $conn->query("SELECT id, nama_kelas FROM kelas ORDER BY id ASC");
$hari_list = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
$search = $_GET['q'] ?? '';
?>

<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <title>Jadwal Pelajaran - SIAKAD PENABUR</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } } } }
    </script>
</head>
<body class="bg-gray-50 font-sans" x-data="{ sidebarOpen: false }">

    <div class="fixed inset-0 z-40 bg-black/50" x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity style="display: none;"></div>

    <?php include 'sidebar.php'; ?>

    <div class="min-h-screen flex flex-col transition-all duration-300" :class="sidebarOpen ? 'lg:ml-64' : 'ml-0'">
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md shadow-sm p-4 sm:px-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue transition p-2 rounded-lg bg-gray-100">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <h2 class="text-xl font-extrabold text-penaburDark">Atur Jadwal Pelajaran</h2>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm font-extrabold hidden sm:block"><?= htmlspecialchars($nama_user); ?></span>
                <img src="assets/<?= htmlspecialchars($foto_user); ?>" class="w-10 h-10 rounded-full object-cover">
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-8 space-y-8 max-w-7xl mx-auto w-full">
            
            <div class="bg-white rounded-3xl shadow-sm border p-6" x-data="{ istirahat: false }">
                <h3 class="font-extrabold text-penaburDark mb-4"><i class="fa-solid fa-clock text-penaburGold mr-2"></i> Input Jadwal Baru</h3>
                <?php if(isset($pesan_sukses)): ?><div class="bg-green-100 text-green-700 p-3 rounded-xl mb-4 text-xs font-bold"><?= $pesan_sukses; ?></div><?php endif; ?>
                <?php if(isset($pesan_error)): ?><div class="bg-red-100 text-red-700 p-3 rounded-xl mb-4 text-xs font-bold"><?= $pesan_error; ?></div><?php endif; ?>
                <form action="" method="POST" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Hari</label>
                        <select name="hari" class="w-full px-4 py-2.5 rounded-xl border focus:ring-2 focus:ring-penaburGold outline-none text-sm">
                            <?php foreach($hari_list as $h): ?> <option value="<?= $h; ?>"><?= $h; ?></option> <?php endforeach; ?>
                        </select>
                    </div>
                    <div x-show="!istirahat">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Mapel</label>
                        <select name="id_mapel" class="w-full px-4 py-2.5 rounded-xl border outline-none text-sm" :required="!istirahat">
                            <option value="">-- Pilih --</option>
                            <?php while($m = $mapels_list->fetch_assoc()): ?> <option value="<?= $m['id']; ?>"><?= $m['kode_mapel']; ?></option> <?php endwhile; ?>
                        </select>
                    </div>
                    <div x-show="!istirahat">
                        <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Kelas</label>
                        <select name="id_kelas" class="w-full px-4 py-2.5 rounded-xl border outline-none text-sm" :required="!istirahat">
                            <option value="">-- Pilih --</option>
                            <?php while($k = $kelases_list->fetch_assoc()): ?> <option value="<?= $k['id']; ?>"><?= $k['nama_kelas']; ?></option> <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="md:col-span-1 flex gap-1">
                        <div class="w-1/2">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Mulai</label>
                            <input type="time" name="jam_mulai" class="w-full px-2 py-2.5 rounded-xl border text-sm" required>
                        </div>
                        <div class="w-1/2">
                            <label class="block text-[10px] font-bold text-gray-400 uppercase mb-1">Selesai</label>
                            <input type="time" name="jam_selesai" class="w-full px-2 py-2.5 rounded-xl border text-sm" required>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 pb-3">
                        <input type="checkbox" name="is_istirahat" x-model="istirahat" class="w-4 h-4 rounded text-penaburBlue">
                        <span class="text-xs font-bold text-gray-600 uppercase">Istirahat?</span>
                    </div>
                    <button type="submit" name="simpan_jadwal" class="w-full py-2.5 bg-penaburBlue text-white font-extrabold rounded-xl hover:bg-penaburDark transition">Simpan</button>
                </form>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border overflow-hidden">
                <div class="px-6 py-4 bg-penaburBlue border-b flex justify-between items-center gap-4">
                    <h3 class="font-extrabold text-white text-lg uppercase tracking-wider"><i class="fa-solid fa-calendar-days text-penaburGold mr-2"></i> Master Schedule Grid</h3>
                    <form method="GET" class="flex gap-2">
                        <input type="text" name="q" value="<?= htmlspecialchars($search); ?>" placeholder="Cari..." class="px-4 py-2 rounded-xl bg-white/10 text-white placeholder-blue-200 outline-none text-sm font-medium">
                        <button type="submit" class="bg-penaburGold text-penaburDark px-5 py-2 rounded-xl text-sm font-bold hover:bg-white"><i class="fa-solid fa-search"></i> Cari</button>
                    </form>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-center text-xs border-collapse">
                        <thead>
                            <tr class="bg-gray-100 text-penaburDark font-black uppercase tracking-widest border-b">
                                <th class="px-4 py-4 border-r w-24">Waktu</th>
                                <?php 
                                $kelases_list->data_seek(0);
                                while($k = $kelases_list->fetch_assoc()): 
                                ?>
                                    <th class="px-6 py-4 border-r min-w-[120px]"><?= $k['nama_kelas']; ?></th>
                                <?php endwhile; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($hari_list as $h): ?>
                            <tr class="bg-blue-50/50"><td colspan="10" class="px-4 py-2 text-left font-black text-penaburBlue uppercase tracking-[0.2em] border-y"><?= $h; ?></td></tr>
                            
                            <?php 
                            // Ambil slot waktu UNIK di hari ini (baik istirahat maupun mapel biasa)
                            $slots_q = $conn->query("SELECT DISTINCT jam_mulai, jam_selesai, is_istirahat FROM jadwal_pelajaran WHERE hari = '$h' ORDER BY jam_mulai ASC");
                            
                            while($slot = $slots_q->fetch_assoc()):
                                $mulai = $slot['jam_mulai'];
                                $selesai = $slot['jam_selesai'];
                            ?>
                            <tr class="border-b hover:bg-gray-50 transition">
                                <td class="px-2 py-3 border-r font-bold text-gray-500 bg-gray-50 whitespace-nowrap">
                                    <?= date('H:i', strtotime($mulai)); ?> - <?= date('H:i', strtotime($selesai)); ?>
                                </td>
                                
                                <?php if($slot['is_istirahat'] == 1): ?>
                                    <?php 
                                        $ist_id_q = $conn->query("SELECT id FROM jadwal_pelajaran WHERE hari = '$h' AND jam_mulai = '$mulai' AND is_istirahat = 1 LIMIT 1");
                                        $ist_id = $ist_id_q->fetch_assoc()['id'] ?? 0;
                                    ?>
                                    <td colspan="10" class="bg-yellow-50 text-penaburGold font-black uppercase tracking-[0.5em] py-2 relative">
                                        ISTIRAHAT
                                        <a href="data_jadwal?hapus=<?= $ist_id; ?>" onclick="return confirm('Hapus istirahat ini?')" class="absolute right-4 top-1/2 -translate-y-1/2 text-red-400 hover:text-red-600"><i class="fa-solid fa-trash-can"></i></a>
                                    </td>
                                <?php else: ?>
                                    <?php 
                                    $kelases_list->data_seek(0);
                                    while($k = $kelases_list->fetch_assoc()): 
                                        $id_k = $k['id'];
                                        // Cari mapel untuk KELAS INI di JAM INI
                                        $j_q = $conn->query("SELECT j.id, m.kode_mapel FROM jadwal_pelajaran j LEFT JOIN mapel m ON j.id_mapel = m.id WHERE j.hari = '$h' AND j.jam_mulai = '$mulai' AND j.id_kelas = $id_k LIMIT 1");
                                        
                                        if($j_q->num_rows > 0):
                                            $j_data = $j_q->fetch_assoc();
                                    ?>
                                        <td class="px-2 py-3 border-r relative group">
                                            <p class="font-black text-penaburBlue leading-none"><?= $j_data['kode_mapel'] ?? 'ERR'; ?></p>
                                            <a href="data_jadwal?hapus=<?= $j_data['id']; ?>" onclick="return confirm('Hapus jadwal ini?')" class="opacity-0 group-hover:opacity-100 absolute -top-1 -right-1 text-red-500 transition-all scale-75 hover:scale-100"><i class="fa-solid fa-circle-xmark"></i></a>
                                        </td>
                                    <?php else: ?>
                                        <td class="px-2 py-3 border-r"><span class="text-gray-200 italic">-</span></td>
                                    <?php endif; endwhile; ?>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</body>
</html>