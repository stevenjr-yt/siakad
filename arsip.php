<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role'])) { header("Location: dashboard"); exit(); }

@$conn->query("ALTER TABLE nilai ADD COLUMN jenis_ujian VARCHAR(10) DEFAULT 'STS'");

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$nama_user = $_SESSION['nama_lengkap'];

$data_arsip = [];

// ==========================================
// LOGIKA JIKA YANG LOGIN ADALAH SISWA
// ==========================================
if ($role == 'siswa') {
    $q_arsip = $conn->query("
        SELECT n.semester, n.tahun_ajaran, n.jenis_ujian, k.nama_kelas, m.nama_mapel, 
               n.tugas_1, n.tugas_2, n.tugas_3, n.sumatif, n.sas, n.nilai_akhir, n.catatan
        FROM nilai n
        JOIN penugasan p ON n.id_penugasan = p.id
        JOIN mapel m ON p.id_mapel = m.id
        JOIN kelas k ON p.id_kelas = k.id
        WHERE n.id_siswa = '$user_id'
        ORDER BY n.tahun_ajaran DESC, n.semester DESC, n.jenis_ujian DESC, m.nama_mapel ASC
    ");
    
    while($r = $q_arsip->fetch_assoc()) {
        $jenis = isset($r['jenis_ujian']) ? $r['jenis_ujian'] : 'STS';
        $periode = $r['semester'] . " - " . $r['tahun_ajaran'] . " (" . $jenis . ") (Kelas " . $r['nama_kelas'] . ")";
        $data_arsip[$periode][] = $r;
    }
} 
// ==========================================
// LOGIKA JIKA YANG LOGIN ADALAH GURU/KURIKULUM
// ==========================================
else {
    $q_arsip = $conn->query("
        SELECT DISTINCT n.semester, n.tahun_ajaran, n.jenis_ujian, k.id as id_kelas, k.nama_kelas, m.nama_mapel, 
               p.topik_1, p.topik_2, p.topik_3, p.topik_sumatif
        FROM nilai n
        JOIN penugasan p ON n.id_penugasan = p.id
        JOIN mapel m ON p.id_mapel = m.id
        JOIN kelas k ON p.id_kelas = k.id
        WHERE p.id_guru = '$user_id'
        ORDER BY n.tahun_ajaran DESC, n.semester DESC, n.jenis_ujian DESC, k.nama_kelas ASC, m.nama_mapel ASC
    ");
    
    while($r = $q_arsip->fetch_assoc()) {
        $jenis = isset($r['jenis_ujian']) ? $r['jenis_ujian'] : 'STS';
        $periode = $r['semester'] . " - " . $r['tahun_ajaran'] . " (" . $jenis . ")";
        $data_arsip[$periode][] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Arsip Akademik - SIAKAD PENABUR</title>
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
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md shadow-sm p-4 sm:px-8 border-b flex items-center justify-between">
            <div class="flex items-center">
                <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue p-2 rounded-lg bg-gray-100 mr-4"><i class="fa-solid fa-bars text-xl"></i></button>
                <h2 class="text-xl font-extrabold text-penaburDark">Arsip E-Rapor & KBM</h2>
            </div>
            <span class="bg-blue-50 text-penaburBlue px-4 py-2 rounded-xl text-xs font-bold uppercase tracking-widest"><i class="fa-solid fa-box-archive mr-2"></i> Mode Histori</span>
        </header>

        <main class="flex-1 p-4 sm:p-8 max-w-7xl mx-auto w-full space-y-6">
            
            <?php if(empty($data_arsip)): ?>
                <div class="bg-white p-12 text-center rounded-3xl border border-gray-100 shadow-sm">
                    <i class="fa-solid fa-folder-open text-6xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-extrabold text-gray-500">Belum Ada Riwayat Arsip</h3>
                    <p class="text-gray-400 mt-2">Data nilai atau riwayat mengajar Anda di periode sebelumnya akan muncul di sini.</p>
                </div>
            <?php else: ?>
                
                <?php foreach($data_arsip as $periode => $items): ?>
                <div x-data="{ open: false }" class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                    <button @click="open = !open" class="w-full p-6 flex justify-between items-center bg-gradient-to-r from-penaburDark to-penaburBlue text-white text-left transition hover:opacity-90">
                        <div>
                            <h3 class="text-xl font-black"><i class="fa-solid fa-calendar-check text-penaburGold mr-2"></i> Periode: <?= $periode; ?></h3>
                            <p class="text-xs text-blue-200 mt-1 font-bold">Klik untuk melihat detail arsip pada periode ini.</p>
                        </div>
                        <i class="fa-solid fa-chevron-down transform transition-transform text-xl" :class="open ? 'rotate-180' : ''"></i>
                    </button>

                    <div x-show="open" x-transition class="p-6 border-t" style="display: none;">
                        
                        <?php if($role == 'siswa'): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm text-left border-collapse">
                                    <thead class="bg-gray-50 text-[10px] font-black text-gray-500 uppercase tracking-widest border-b border-t">
                                        <tr>
                                            <th class="px-4 py-3 border-r">Mata Pelajaran</th>
                                            <th class="px-2 py-3 text-center">T1</th>
                                            <th class="px-2 py-3 text-center">T2</th>
                                            <th class="px-2 py-3 text-center">T3</th>
                                            <th class="px-2 py-3 text-center">SUM</th>
                                            <th class="px-2 py-3 text-center text-yellow-700 bg-yellow-50">STS/SAS</th>
                                            <th class="px-4 py-3 text-center font-extrabold text-penaburBlue bg-blue-50 border-r">Nilai Akhir</th>
                                            <th class="px-4 py-3 min-w-[300px]">Deskripsi (Catatan Guru)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach($items as $i): ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="px-4 py-3 font-bold text-penaburDark border-r whitespace-nowrap"><?= htmlspecialchars($i['nama_mapel']); ?></td>
                                            <td class="px-2 py-3 text-center"><?= $i['tugas_1']; ?></td>
                                            <td class="px-2 py-3 text-center"><?= $i['tugas_2']; ?></td>
                                            <td class="px-2 py-3 text-center"><?= $i['tugas_3']; ?></td>
                                            <td class="px-2 py-3 text-center"><?= $i['sumatif']; ?></td>
                                            <td class="px-2 py-3 text-center font-bold text-yellow-700 bg-yellow-50/50"><?= $i['sas']; ?></td>
                                            <td class="px-4 py-3 text-center font-black text-lg text-penaburBlue bg-blue-50/30 border-r"><?= round($i['nilai_akhir'], 1); ?></td>
                                            <td class="px-4 py-3 text-xs italic text-gray-600"><?= htmlspecialchars($i['catatan'] ?? '-'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <?php foreach($items as $i): 
                                    $lvl = explode(' ', $i['nama_kelas'])[0]; 
                                    $parts = explode(' - ', $periode);
                                    $smstr = $parts[0];
                                    
                                    $thn_parts = explode(' ', $parts[1]);
                                    $thn_ajrn = $thn_parts[0];
                                    $jenis_ujian_link = str_replace(['(', ')'], '', $thn_parts[1]);
                                ?>
                                <div class="bg-gray-50 rounded-2xl p-5 border border-gray-200 hover:shadow-md transition relative">
                                    <h4 class="text-lg font-black text-penaburDark mb-1"><?= htmlspecialchars($i['nama_mapel']); ?></h4>
                                    <span class="px-3 py-1 bg-blue-100 text-penaburBlue font-bold text-[10px] uppercase rounded-lg">Kelas: <?= htmlspecialchars($i['nama_kelas']); ?></span>
                                    
                                    <div class="mt-4 space-y-2 border-t border-gray-200 pt-4">
                                        <p class="text-[10px] font-extrabold text-gray-400 uppercase tracking-widest mb-2">Riwayat Materi / Topik Moodle:</p>
                                        <div class="text-xs font-medium text-gray-600 flex gap-2"><span class="w-16 font-bold text-penaburDark">Tugas 1:</span> <?= htmlspecialchars($i['topik_1'] ?? 'Materi 1'); ?></div>
                                        <div class="text-xs font-medium text-gray-600 flex gap-2"><span class="w-16 font-bold text-penaburDark">Tugas 2:</span> <?= htmlspecialchars($i['topik_2'] ?? 'Materi 2'); ?></div>
                                        <div class="text-xs font-medium text-gray-600 flex gap-2"><span class="w-16 font-bold text-penaburDark">Tugas 3:</span> <?= htmlspecialchars($i['topik_3'] ?? 'Materi 3'); ?></div>
                                        <div class="text-xs font-medium text-gray-600 flex gap-2"><span class="w-16 font-bold text-penaburDark">Sumatif:</span> <?= htmlspecialchars($i['topik_sumatif'] ?? 'Sumatif'); ?></div>
                                    </div>
                                    
                                    <div class="mt-5">
                                        <a href="ledger.php?level=<?= $lvl; ?>&kelas=<?= $i['id_kelas']; ?>&semester=<?= $smstr; ?>&tahun=<?= $thn_ajrn; ?>&jenis_ujian=<?= $jenis_ujian_link; ?>" class="block w-full text-center py-2.5 bg-penaburDark text-penaburGold font-bold text-sm rounded-xl hover:bg-black transition shadow-sm"><i class="fa-solid fa-book-open-reader mr-2"></i> Lihat Rekap Ledger Kelas Ini</a>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
                <?php endforeach; ?>
                
            <?php endif; ?>

        </main>
    </div>
</body>
</html>