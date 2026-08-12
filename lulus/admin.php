<?php
// admin.php
session_start();
require '../koneksi.php';

$pesan = '';

// Pengecekan Akses Global SIAKAD
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'kurikulum', 'guru'])) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

// 1. Update Waktu
if (isset($_POST['update_waktu'])) {
    $waktu_baru = $_POST['waktu'];
    $stmt = $conn->prepare("UPDATE setting_kelulusan SET waktu_pengumuman = ? WHERE id = 1");
    $stmt->bind_param("s", $waktu_baru);
    if ($stmt->execute()) {
        $pesan = '<div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 shadow border border-green-200">Waktu pengumuman berhasil diperbarui.</div>';
    }
}

// 2. Download Template
if (isset($_POST['download_template'])) {
    $query_export = "SELECT u.id, u.username, u.nama_lengkap, k.nama_kelas, IFNULL(sk.keterangan, 'BELUM ADA DATA') as keterangan 
                     FROM users u JOIN kelas k ON u.id_kelas = k.id LEFT JOIN status_kelulusan sk ON u.id = sk.id_user 
                     WHERE k.nama_kelas LIKE '%XII%' AND u.role = 'siswa' ORDER BY k.nama_kelas ASC, u.nama_lengkap ASC";
    $q_export = $conn->query($query_export);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Data_Kelulusan_Kelas_XII.csv');
    $output = fopen('php://output', 'w');
    fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    fputcsv($output, array('ID_USER_(JANGAN_DIUBAH)', 'USERNAME', 'NAMA_SISWA', 'KELAS', 'KETERANGAN_(LULUS/TIDAK LULUS)'), ';');
    
    while($row = $q_export->fetch_assoc()) {
        fputcsv($output, array($row['id'], $row['username'], $row['nama_lengkap'], $row['nama_kelas'], $row['keterangan']), ';');
    }
    fclose($output);
    exit();
}

// 3. Upload CSV
if (isset($_POST['upload_data'])) {
    if ($_FILES['file_csv']['name']) {
        $filename = explode(".", $_FILES['file_csv']['name']);
        if (strtolower(end($filename)) == "csv") {
            $handle = fopen($_FILES['file_csv']['tmp_name'], "r");
            $firstLine = fgets($handle);
            $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
            rewind($handle);
            fgetcsv($handle, 1000, $delimiter); 
            
            $berhasil = 0;
            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                if(!empty($data[0])){
                    $id_user = (int) $conn->real_escape_string(trim($data[0]));
                    $ket = strtoupper($conn->real_escape_string(trim($data[4])));

                    $query = "INSERT INTO status_kelulusan (id_user, keterangan) VALUES ('$id_user', '$ket') ON DUPLICATE KEY UPDATE keterangan='$ket'";
                    if ($conn->query($query)) $berhasil++;
                }
            }
            fclose($handle);
            $pesan = '<div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 shadow border border-green-200">Berhasil memperbarui status kelulusan untuk '.$berhasil.' siswa.</div>';
        } else {
            $pesan = '<div class="p-4 mb-4 text-sm text-red-800 rounded-lg bg-red-50 shadow border border-red-200">Format file harus .csv</div>';
        }
    }
}

// 4. Live Edit
if (isset($_POST['edit_data'])) {
    $id_user_edit = (int) $_POST['edit_id_user'];
    $ket_baru = strtoupper($conn->real_escape_string($_POST['edit_ket']));

    $query = "INSERT INTO status_kelulusan (id_user, keterangan) VALUES ('$id_user_edit', '$ket_baru') ON DUPLICATE KEY UPDATE keterangan='$ket_baru'";
    if ($conn->query($query)) {
        $pesan = '<div class="p-4 mb-4 text-sm text-green-800 rounded-lg bg-green-50 shadow border border-green-200">Status siswa berhasil diperbarui!</div>';
    }
}

// Mengambil Data Master Siswa
$query_siswa_xii = "
    SELECT u.id as id_user, u.username, u.nama_lengkap, k.nama_kelas, IFNULL(sk.keterangan, 'BELUM ADA DATA') as keterangan
    FROM users u
    JOIN kelas k ON u.id_kelas = k.id
    LEFT JOIN status_kelulusan sk ON u.id = sk.id_user
    WHERE k.nama_kelas LIKE '%XII%' AND u.role = 'siswa'
    ORDER BY k.nama_kelas ASC, u.nama_lengkap ASC
";
$q_data_siswa = $conn->query($query_siswa_xii);

$q_waktu = $conn->query("SELECT waktu_pengumuman FROM setting_kelulusan WHERE id = 1");
$d_waktu = $q_waktu->fetch_assoc();

// MENGAMBIL DATA MAPEL AMPUAN GURU
$id_guru_aktif = $_SESSION['user_id'];
$q_mapel = $conn->query("
    SELECT m.kode_mapel, k.nama_kelas 
    FROM penugasan p 
    JOIN mapel m ON p.id_mapel = m.id 
    JOIN kelas k ON p.id_kelas = k.id 
    WHERE p.id_guru = '$id_guru_aktif'
");

$arr_mapel = [];
if ($q_mapel && $q_mapel->num_rows > 0) {
    while ($m = $q_mapel->fetch_assoc()) {
        $arr_mapel[] = $m['kode_mapel'] . '-' . str_replace(' ', '', $m['nama_kelas']);
    }
    $string_mapel = implode(', ', $arr_mapel);
} else {
    $string_mapel = 'Tidak ada penugasan Mapel';
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Kelulusan Penabur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap'); body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-gray-100 min-h-screen p-4 pb-20">

    <div class="max-w-6xl mx-auto mt-6">
        <div class="flex justify-between items-center mb-6 bg-white p-5 rounded-2xl shadow-sm border border-gray-200">
            <div class="flex items-center gap-4">
                <img src="../assets/<?= htmlspecialchars($_SESSION['foto_profil']) ?>" onerror="this.src='https://via.placeholder.com/50'" class="w-14 h-14 rounded-full object-cover border-2 border-gray-200">
                <div>
                    <h1 class="text-2xl md:text-3xl font-extrabold text-gray-800">Manajemen Kelulusan</h1>
                    <p class="text-sm text-blue-600 font-bold mt-1 uppercase tracking-wide">
                        <?= htmlspecialchars($_SESSION['role']) ?> &bull; <?= htmlspecialchars($_SESSION['nama_lengkap']) ?>
                    </p>
                    <p class="text-xs text-gray-500 font-medium mt-1">MAPEL AMPUAN: <span class="font-bold text-gray-700"><?= htmlspecialchars($string_mapel) ?></span></p>
                </div>
            </div>
            <div class="space-x-2 md:space-x-4">
                <a href="index.php" target="_blank" class="text-blue-600 text-sm font-semibold hover:underline hidden md:inline-block">Lihat Web Siswa</a>
                <a href="?logout=1" class="bg-red-500 text-white px-5 py-2 rounded-lg font-bold hover:bg-red-600 shadow text-sm transition">Logout</a>
            </div>
        </div>

        <?= $pesan ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-2xl shadow-sm p-6 border-t-4 border-blue-600 border-x border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800 mb-2">1. Jadwal Pengumuman</h2>
                <p class="text-sm text-gray-500 mb-4">Tentukan kapan hasil kelulusan dapat dilihat oleh siswa.</p>
                <form method="POST">
                    <input type="datetime-local" name="waktu" value="<?= date('Y-m-d\TH:i', strtotime($d_waktu['waktu_pengumuman'])) ?>" class="w-full border p-3 rounded-lg bg-gray-50 mb-4 font-mono text-sm border-gray-300 focus:ring-blue-500" required>
                    <button type="submit" name="update_waktu" class="w-full bg-blue-600 text-white font-bold py-3 rounded-lg shadow hover:bg-blue-700 transition">Simpan Jadwal</button>
                </form>
            </div>

            <div class="bg-white rounded-2xl shadow-sm p-6 border-t-4 border-green-500 border-x border-b border-gray-200">
                <h2 class="text-xl font-bold text-gray-800 mb-2">2. Unggah Status Kelulusan</h2>
                <p class="text-sm text-gray-500 mb-4">Siswa Kelas XII otomatis terdaftar di file unduhan. Ubah status, lalu Unggah kembali.</p>
                
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-5 rounded-r-lg shadow-sm">
                    <div class="flex items-start">
                        <div class="ml-2">
                            <h3 class="text-sm font-bold text-yellow-800">PERHATIAN PENTING</h3>
                            <p class="text-xs text-yellow-700 mt-1 leading-relaxed">
                                Jangan mengubah ID_USER atau nama kolom. Anda cukup mengisi kolom <b>KETERANGAN</b> dengan tulisan <b>LULUS</b> atau <b>TIDAK LULUS</b>.
                            </p>
                        </div>
                    </div>
                </div>

                <form method="POST" class="mb-4">
                    <button type="submit" name="download_template" class="w-full border-2 border-green-500 text-green-600 font-bold py-2 rounded-lg hover:bg-green-50 transition text-sm">Unduh File Siswa Kelas XII (.csv)</button>
                </form>

                <form method="POST" enctype="multipart/form-data" class="space-y-3">
                    <input type="file" name="file_csv" accept=".csv" required class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none p-2">
                    <button type="submit" name="upload_data" class="w-full bg-green-500 text-white font-bold py-3 rounded-lg shadow hover:bg-green-600 transition">Proses Sinkronisasi Data</button>
                </form>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 p-5 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-bold text-gray-800">Status Kelulusan Siswa Kelas XII</h2>
                <span class="bg-blue-100 text-blue-800 text-xs font-semibold px-3 py-1 rounded-full"><?= $q_data_siswa->num_rows ?> Siswa</span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-500">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-100">
                        <tr>
                            <th scope="col" class="px-6 py-4 rounded-tl-lg">No</th>
                            <th scope="col" class="px-6 py-4">Username</th>
                            <th scope="col" class="px-6 py-4">Nama Lengkap</th>
                            <th scope="col" class="px-6 py-4">Kelas</th>
                            <th scope="col" class="px-6 py-4">Status</th>
                            <th scope="col" class="px-6 py-4 rounded-tr-lg text-center">Ubah Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($q_data_siswa->num_rows == 0): ?>
                            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-400 font-medium">Belum ada siswa kelas XII di database SIAKAD.</td></tr>
                        <?php else: ?>
                            <?php $no = 1; while($row = $q_data_siswa->fetch_assoc()): ?>
                                <tr class="bg-white border-b hover:bg-gray-50 transition">
                                    <td class="px-6 py-4 font-medium text-gray-900"><?= $no++ ?></td>
                                    <td class="px-6 py-4 font-bold text-gray-700"><?= htmlspecialchars($row['username']) ?></td>
                                    <td class="px-6 py-4 font-semibold text-gray-900"><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($row['nama_kelas']) ?></td>
                                    <td class="px-6 py-4 font-bold">
                                        <?php if ($row['keterangan'] == 'LULUS'): ?>
                                            <span class="text-green-600 bg-green-100 px-2 py-1 rounded">LULUS</span>
                                        <?php elseif ($row['keterangan'] == 'TIDAK LULUS'): ?>
                                            <span class="text-red-600 bg-red-100 px-2 py-1 rounded">TIDAK LULUS</span>
                                        <?php else: ?>
                                            <span class="text-gray-400 font-normal italic">Belum Diatur</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <button type="button" onclick="bukaModalEdit('<?= $row['id_user'] ?>', '<?= addslashes($row['nama_lengkap']) ?>', '<?= $row['keterangan'] ?>')" class="text-white bg-blue-500 hover:bg-blue-600 rounded-lg px-4 py-2 text-xs font-bold shadow-sm transition">
                                            Edit Status
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="modal-edit" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center backdrop-blur-sm p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full overflow-hidden transform transition-all">
                <div class="bg-blue-600 p-4 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-white">Edit Status Kelulusan</h3>
                    <button type="button" onclick="tutupModalEdit()" class="text-blue-100 hover:text-white font-bold text-xl">&times;</button>
                </div>
                
                <form method="POST" class="p-6 space-y-4">
                    <input type="hidden" name="edit_id_user" id="modal_id_user">
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Nama Siswa</label>
                        <input type="text" id="modal_nama" disabled class="w-full p-2.5 border rounded-lg bg-gray-100 text-gray-500 outline-none text-sm font-semibold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Keterangan Lulus</label>
                        <select name="edit_ket" id="modal_ket" required class="w-full p-2.5 border rounded-lg bg-gray-50 focus:ring-2 focus:ring-blue-500 outline-none text-sm font-bold">
                            <option value="LULUS" class="text-green-600 font-bold">LULUS</option>
                            <option value="TIDAK LULUS" class="text-red-600 font-bold">TIDAK LULUS</option>
                        </select>
                    </div>
                    
                    <div class="pt-4 flex gap-3">
                        <button type="button" onclick="tutupModalEdit()" class="flex-1 bg-gray-200 text-gray-800 font-bold py-2.5 rounded-lg hover:bg-gray-300 transition text-sm">Batal</button>
                        <button type="submit" name="edit_data" class="flex-1 bg-blue-600 text-white font-bold py-2.5 rounded-lg shadow hover:bg-blue-700 transition text-sm">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            function bukaModalEdit(id, nama, ket) {
                document.getElementById('modal_id_user').value = id;
                document.getElementById('modal_nama').value = nama;
                document.getElementById('modal_ket').value = ket === 'BELUM ADA DATA' ? 'LULUS' : ket;
                document.getElementById('modal-edit').classList.remove('hidden');
            }
            function tutupModalEdit() { document.getElementById('modal-edit').classList.add('hidden'); }
        </script>

    </div>
</body>
</html>