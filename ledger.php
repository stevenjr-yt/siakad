<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['superadmin', 'kepsek', 'kurikulum'])) {
    header("Location: dashboard"); exit();
}

// Persiapan tabel dengan pengecekan
$cek_kolom = $conn->query("SHOW COLUMNS FROM nilai LIKE 'jenis_ujian'");
if($cek_kolom->num_rows == 0) {
    $conn->query("ALTER TABLE nilai ADD COLUMN jenis_ujian VARCHAR(10) DEFAULT 'STS'");
}

$level = $_GET['level'] ?? 'X';
$id_kelas_filter = $_GET['kelas'] ?? '';

// Ambil default periode dari settingan YANG AKTIF
$q_pengaturan = $conn->query("SELECT semester, tahun_ajaran FROM pengaturan_nilai WHERE status='aktif' LIMIT 1");
if($q_pengaturan->num_rows > 0) {
    $pengaturan = $q_pengaturan->fetch_assoc();
} else {
    $pengaturan = ['semester' => 'Ganjil', 'tahun_ajaran' => '2025/2026'];
}

$filter_semester = $_GET['semester'] ?? $pengaturan['semester'];
$filter_tahun = $_GET['tahun'] ?? $pengaturan['tahun_ajaran'];
$filter_jenis = $_GET['jenis_ujian'] ?? 'Semua';

// Ambil daftar Tahun Ajaran dan Semester yang ada di tabel nilai biar dinamis (historikal)
$list_tahun = [];
$q_thn = $conn->query("SELECT DISTINCT tahun_ajaran FROM nilai ORDER BY tahun_ajaran DESC");
if($q_thn) { while($t = $q_thn->fetch_assoc()){ $list_tahun[] = $t['tahun_ajaran']; } }
if(!in_array($pengaturan['tahun_ajaran'], $list_tahun)) { $list_tahun[] = $pengaturan['tahun_ajaran']; } 

// Ambil daftar kelas untuk filter
$kelases = [];
$q_kelas = $conn->query("SELECT id, nama_kelas FROM kelas WHERE nama_kelas LIKE '$level %' ORDER BY nama_kelas ASC");
while($k = $q_kelas->fetch_assoc()) { $kelases[] = $k; }

// LOGIKA FILTER MAPEL DINAMIS (CUMA MUNCULIN MAPEL YANG DIAJARKAN DI KELAS INI)
$where_mapel = ($id_kelas_filter != '') ? "AND p.id_kelas = '$id_kelas_filter'" : "AND k.nama_kelas LIKE '$level %'";
// GW TAMBAHIN KKM DISINI BIAR BISA NGITUNG GRADE
$q_mapel = $conn->query("SELECT DISTINCT m.id, m.nama_mapel, m.kode_mapel, m.kkm_x, m.kkm_xi, m.kkm_xii 
                         FROM mapel m 
                         JOIN penugasan p ON m.id = p.id_mapel 
                         JOIN kelas k ON p.id_kelas = k.id 
                         WHERE 1=1 $where_mapel 
                         ORDER BY m.nama_mapel ASC");

$mapels = [];
while($m = $q_mapel->fetch_assoc()) { $mapels[] = $m; }

// =========================================================================================
// QUERY SAKTI: NGELACAK SISWA BERDASARKAN "SEJARAH" KELAS DI TAHUN TERSEBUT
// =========================================================================================
$where_condition = ($id_kelas_filter != '') 
    ? "(k_curr.id = '$id_kelas_filter' OR k_hist.id = '$id_kelas_filter')" 
    : "(k_curr.nama_kelas LIKE '$level %' OR k_hist.nama_kelas LIKE '$level %')";

$query_siswa_sql = "
    SELECT u.id, u.nama_lengkap, u.username as nis,
           COALESCE(k_hist.nama_kelas, k_curr.nama_kelas) as nama_kelas
    FROM users u
    LEFT JOIN kelas k_curr ON u.id_kelas = k_curr.id
    LEFT JOIN (
        SELECT n.id_siswa, p.id_kelas 
        FROM nilai n 
        JOIN penugasan p ON n.id_penugasan = p.id 
        WHERE n.semester = '$filter_semester' AND n.tahun_ajaran = '$filter_tahun'
        GROUP BY n.id_siswa, p.id_kelas
    ) as hist ON u.id = hist.id_siswa
    LEFT JOIN kelas k_hist ON hist.id_kelas = k_hist.id
    WHERE u.role = 'siswa' AND ($where_condition)
    GROUP BY u.id
    ORDER BY nama_kelas ASC, u.nama_lengkap ASC
";
$siswa_q = $conn->query($query_siswa_sql);


// Ambil nilai HANYA berdasarkan FILTER SEMESTER, TAHUN AJARAN, DAN JENIS UJIAN (STS/SAS/Semua)
$nilai_map = [];
if ($filter_jenis == 'Semua') {
    // Akumulasi rata-rata jika dipilih "Semua"
    $q_nilai = $conn->query("SELECT n.id_siswa, p.id_mapel, 
        AVG(n.tugas_1) as tugas_1, AVG(n.tugas_2) as tugas_2, AVG(n.tugas_3) as tugas_3, 
        AVG(n.sumatif) as sumatif, AVG(n.sas) as sas, AVG(n.nilai_akhir) as nilai_akhir, 
        GROUP_CONCAT(n.catatan SEPARATOR ' | ') as catatan
        FROM nilai n 
        JOIN penugasan p ON n.id_penugasan = p.id 
        WHERE n.semester = '$filter_semester' AND n.tahun_ajaran = '$filter_tahun'
        GROUP BY n.id_siswa, p.id_mapel");
} else {
    $q_nilai = $conn->query("SELECT n.*, p.id_mapel FROM nilai n JOIN penugasan p ON n.id_penugasan = p.id WHERE n.semester = '$filter_semester' AND n.tahun_ajaran = '$filter_tahun' AND n.jenis_ujian = '$filter_jenis'");
}

while($n = $q_nilai->fetch_assoc()){
    $nilai_map[$n['id_siswa']][$n['id_mapel']] = $n;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Ledger Nilai & Catatan - SIAKAD PENABUR</title>
    <base href="/">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>tailwind.config = { theme: { extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } } } }</script>
    <style>
        .table-ledger th, .table-ledger td { border: 1px solid #e5e7eb; padding: 8px; font-size: 11px; white-space: nowrap; }
        .bg-header { background-color: #f9fafb; font-weight: 800; text-transform: uppercase; color: #4b5563; }
        .sticky-col { position: sticky; left: 0; background: white; z-index: 10; border-right: 2px solid #ddd !important; }
        .catatan-wrap { white-space: normal !important; min-width: 350px; line-height: 1.6; }
    </style>
</head>
<body class="bg-gray-50 font-sans text-gray-800" x-data="{ sidebarOpen: false }">

    <div class="fixed inset-0 z-40 bg-black/50" x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity style="display: none;"></div>
    <?php include 'sidebar.php'; ?>

    <div class="min-h-screen flex flex-col transition-all duration-300" :class="sidebarOpen ? 'lg:ml-64' : 'ml-0'">
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md border-b shadow-sm p-4 sm:px-8 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue p-2 rounded-lg bg-gray-100"><i class="fa-solid fa-bars text-xl"></i></button>
                <h2 class="text-xl font-extrabold text-penaburDark">Ledger Nilai - Kelas <?= $level; ?></h2>
            </div>
            <div class="flex gap-2">
                <form class="flex gap-2 items-center" id="filterForm">
                    <input type="hidden" name="level" value="<?= $level; ?>">
                    
                    <select name="tahun" onchange="document.getElementById('filterForm').submit()" class="px-3 py-2 rounded-xl border text-xs font-bold focus:ring-2 focus:ring-penaburGold outline-none bg-blue-50 text-penaburBlue">
                        <?php foreach(array_unique($list_tahun) as $thn): ?>
                            <option value="<?= $thn; ?>" <?= $filter_tahun == $thn ? 'selected' : ''; ?>><?= $thn; ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select name="semester" onchange="document.getElementById('filterForm').submit()" class="px-3 py-2 rounded-xl border text-xs font-bold focus:ring-2 focus:ring-penaburGold outline-none bg-blue-50 text-penaburBlue">
                        <option value="Ganjil" <?= $filter_semester == 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                        <option value="Genap" <?= $filter_semester == 'Genap' ? 'selected' : ''; ?>>Genap</option>
                    </select>

                    <select name="jenis_ujian" onchange="document.getElementById('filterForm').submit()" class="px-3 py-2 rounded-xl border text-xs font-bold focus:ring-2 focus:ring-penaburGold outline-none bg-purple-50 text-purple-700">
                        <option value="Semua" <?= $filter_jenis == 'Semua' ? 'selected' : ''; ?>>Semua (Akumulasi)</option>
                        <option value="STS" <?= $filter_jenis == 'STS' ? 'selected' : ''; ?>>Periode STS</option>
                        <option value="SAS" <?= $filter_jenis == 'SAS' ? 'selected' : ''; ?>>Periode SAS</option>
                    </select>

                    <select name="kelas" onchange="document.getElementById('filterForm').submit()" class="px-4 py-2 rounded-xl border text-xs font-bold focus:ring-2 focus:ring-penaburGold outline-none">
                        <option value="">Semua Rombel (Filter)</option>
                        <?php foreach($kelases as $k): ?>
                            <option value="<?= $k['id']; ?>" <?= $id_kelas_filter == $k['id'] ? 'selected' : ''; ?>><?= $k['nama_kelas']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <button onclick="window.print()" class="bg-penaburDark text-white px-4 py-2 rounded-xl text-xs font-bold hover:bg-black transition"><i class="fa-solid fa-print mr-2"></i> Cetak</button>
            </div>
        </header>

        <main class="flex-1 p-4 overflow-hidden flex flex-col">
            <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-auto flex-1">
                <table class="w-full table-ledger text-center border-collapse">
                    <thead>
                        <tr class="bg-header">
                            <th rowspan="2" class="sticky-col">NO</th>
                            <th rowspan="2" class="sticky-col" style="left: 45px;">NAMA PESERTA DIDIK</th>
                            <th rowspan="2">NIS</th>
                            <th rowspan="2">KELAS</th>
                            
                            <?php if(empty($mapels)): ?>
                                <th rowspan="2" class="text-gray-400 italic font-medium p-4">Belum ada penugasan guru di kelas ini.</th>
                            <?php else: ?>
                                <?php foreach($mapels as $m): ?>
                                    <th colspan="7" class="bg-blue-50 text-penaburBlue border-b-2 border-blue-200"><?= htmlspecialchars($m['nama_mapel']); ?></th>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <th colspan="3" class="bg-red-50 text-red-700 border-b-2 border-red-200">KETIDAKHADIRAN</th>
                            <th rowspan="2" class="bg-green-50 text-green-700 font-black tracking-wider px-6">AKUMULASI CATATAN WALI KELAS / MAPEL</th>
                        </tr>
                        <tr class="bg-gray-50 text-[9px]">
                            <?php foreach($mapels as $m): ?>
                                <th>S1</th><th>S2</th><th>S3</th><th>S4</th><th class="bg-yellow-50">STS</th>
                                <th class="bg-purple-50 text-purple-700">GRADE</th><th class="bg-gray-100 text-gray-700">STATUS</th>
                            <?php endforeach; ?>
                            <th class="bg-red-50/50">S</th>
                            <th class="bg-red-50/50">I</th>
                            <th class="bg-red-50/50">A</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if($siswa_q && $siswa_q->num_rows > 0):
                            while($s = $siswa_q->fetch_assoc()):
                                $id_s = $s['id'];
                                $akumulasi_catatan = [];
                                $nama_kelas_upper = strtoupper($s['nama_kelas']); // Buat ngecek KKM
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="sticky-col bg-white"><?= $no++; ?></td>
                            <td class="sticky-col bg-white text-left font-bold" style="left: 45px;"><?= htmlspecialchars($s['nama_lengkap']); ?></td>
                            <td class="text-gray-500"><?= $s['nis']; ?></td>
                            <td class="font-bold text-penaburBlue"><?= htmlspecialchars($s['nama_kelas']); ?></td>
                            
                            <?php foreach($mapels as $m): 
                                $id_m = $m['id'];
                                $n = $nilai_map[$id_s][$id_m] ?? null;
                                
                                $teks_catatan = $n['catatan'] ?? '';
                                if (!empty(trim($teks_catatan))) {
                                    $akumulasi_catatan[] = "<span class='text-[10px] text-gray-500 uppercase font-bold'>[" . htmlspecialchars($m['nama_mapel']) . "]</span><br>" . htmlspecialchars($teks_catatan);
                                }

                                // HITUNG GRADE & STATUS
                                $grade = '-';
                                $status_text = '-';
                                if ($n && isset($n['nilai_akhir']) && $n['nilai_akhir'] > 0) {
                                    $na = $n['nilai_akhir'];
                                    
                                    // Tentukan KKM Mapel Ini
                                    $kkm_mapel = 75;
                                    if (strpos($nama_kelas_upper, 'XII ') === 0) { $kkm_mapel = $m['kkm_xii'] ?? 75; }
                                    elseif (strpos($nama_kelas_upper, 'XI ') === 0) { $kkm_mapel = $m['kkm_xi'] ?? 75; }
                                    elseif (strpos($nama_kelas_upper, 'X ') === 0) { $kkm_mapel = $m['kkm_x'] ?? 75; }

                                    // Hitung
                                    if ($na >= 90) { $grade = 'A'; }
                                    elseif ($na >= 80) { $grade = 'B'; }
                                    elseif ($na >= $kkm_mapel) { $grade = 'C'; }
                                    elseif ($na >= ($kkm_mapel - 10)) { $grade = 'D'; }
                                    else { $grade = 'E'; }

                                    $status_text = ($na >= $kkm_mapel) ? '<span class="text-green-600 font-bold">Lulus</span>' : '<span class="text-red-500 font-bold">Tidak Lulus</span>';
                                }
                            ?>
                                <td><?= $n ? round($n['tugas_1'], 1) : '-'; ?></td>
                                <td><?= $n ? round($n['tugas_2'], 1) : '-'; ?></td>
                                <td><?= $n ? round($n['tugas_3'], 1) : '-'; ?></td>
                                <td><?= $n ? round($n['sumatif'], 1) : '-'; ?></td>
                                <td class="bg-yellow-50/50 font-bold"><?= $n ? round($n['sas'], 1) : '-'; ?></td>
                                
                                <td class="font-extrabold text-purple-700 bg-purple-50/30"><?= $grade; ?></td>
                                <td class="text-[9px] uppercase bg-gray-50/50"><?= $status_text; ?></td>

                            <?php endforeach; ?>
                            
                            <td class="bg-red-50/30 font-bold text-gray-500">-</td>
                            <td class="bg-red-50/30 font-bold text-gray-500">-</td>
                            <td class="bg-red-50/30 font-bold text-gray-500">-</td>
                            
                            <td class="bg-green-50/30 text-left catatan-wrap align-top px-4 py-2 border-l border-green-200">
                                <?php 
                                if (!empty($akumulasi_catatan)) {
                                    echo implode("<div class='my-2 border-b border-green-200/50'></div>", $akumulasi_catatan);
                                } else {
                                    echo "<span class='text-gray-400 italic'>Belum ada catatan dari guru mapel manapun untuk periode ini.</span>";
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                            <tr><td colspan="<?= 8 + (count($mapels)*7); ?>" class="p-10 text-gray-400 font-bold italic">Belum ada data siswa di periode ini.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 p-4 bg-blue-50 rounded-2xl border border-blue-100 flex justify-between items-center">
                <p class="text-[10px] text-blue-700 font-bold uppercase tracking-wider"><i class="fa-solid fa-info-circle mr-2"></i> Menampilkan Data Ledger Periode: <?= $filter_semester; ?> - <?= $filter_tahun; ?> (Filter: <?= $filter_jenis; ?>).</p>
            </div>
        </main>
    </div>
</body>
</html>