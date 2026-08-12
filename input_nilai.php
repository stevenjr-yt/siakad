<?php
session_start();
include 'koneksi.php';

if (!isset($_SESSION['role']) || !isset($_GET['kelas'])) { header("Location: dashboard"); exit(); }

// Persiapan tabel dengan pengecekan
$cek_kolom = $conn->query("SHOW COLUMNS FROM nilai LIKE 'jenis_ujian'");
if($cek_kolom->num_rows == 0) {
    $conn->query("ALTER TABLE nilai ADD COLUMN jenis_ujian VARCHAR(10) DEFAULT 'STS'");
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$id_penugasan = (int)$_GET['kelas'];

// Cek Jadwal Penilaian Aktif BERDASARKAN STATUS AKTIF
$q_pengaturan = $conn->query("SELECT * FROM pengaturan_nilai WHERE status='aktif' LIMIT 1");
if($q_pengaturan->num_rows > 0) {
    $pengaturan = $q_pengaturan->fetch_assoc();
} else {
    // Fallback jika kurikulum belum set aktif satupun
    $pengaturan = ['tanggal_mulai'=>'2000-01-01', 'tanggal_selesai'=>'2000-01-01', 'semester'=>'Ganjil', 'tahun_ajaran'=>'2025/2026', 'jenis_ujian'=>'STS'];
}

$tgl_sekarang = date('Y-m-d');
$is_active_period = ($tgl_sekarang >= $pengaturan['tanggal_mulai'] && $tgl_sekarang <= $pengaturan['tanggal_selesai']);

$periode_sem = $_GET['semester'] ?? $pengaturan['semester'];
$periode_thn = $_GET['tahun'] ?? $pengaturan['tahun_ajaran'];
$jenis_ujian_aktif = $pengaturan['jenis_ujian'] ?? 'STS';

$list_tahun = [];
$q_thn = $conn->query("SELECT DISTINCT tahun_ajaran FROM nilai ORDER BY tahun_ajaran DESC");
if($q_thn) { while($t = $q_thn->fetch_assoc()){ $list_tahun[] = $t['tahun_ajaran']; } }
if(!in_array($pengaturan['tahun_ajaran'], $list_tahun)) { $list_tahun[] = $pengaturan['tahun_ajaran']; }

$is_open = ($is_active_period && $periode_sem == $pengaturan['semester'] && $periode_thn == $pengaturan['tahun_ajaran']);

// Ambil info Kelas dan Mapel
$q_info = $conn->query("SELECT p.*, m.nama_mapel, m.kkm_x, m.kkm_xi, m.kkm_xii, k.nama_kelas, k.id as id_kelas 
                        FROM penugasan p 
                        JOIN mapel m ON p.id_mapel = m.id 
                        JOIN kelas k ON p.id_kelas = k.id 
                        WHERE p.id = $id_penugasan");

if ($q_info->num_rows == 0) { die("Data kelas tidak ditemukan."); }
$info = $q_info->fetch_assoc();

// Deteksi KKM Berdasarkan Tingkat Kelas
$nama_kelas_upper = strtoupper($info['nama_kelas']);
$kkm_mapel = 75; 
if (strpos($nama_kelas_upper, 'XII ') === 0) { $kkm_mapel = $info['kkm_xii']; } 
elseif (strpos($nama_kelas_upper, 'XI ') === 0) { $kkm_mapel = $info['kkm_xi']; } 
elseif (strpos($nama_kelas_upper, 'X ') === 0) { $kkm_mapel = $info['kkm_x']; }

$siswa_q = $conn->query("SELECT id, nama_lengkap FROM users WHERE role = 'siswa' AND id_kelas = " . $info['id_kelas'] . " ORDER BY nama_lengkap ASC");

// Simpan Data Nilai (DIPISAH BERDASARKAN jenis_ujian yang sedang aktif)
if (isset($_POST['simpan_nilai']) && $is_open) {
    $tp1 = $conn->real_escape_string($_POST['topik_1']);
    $tp2 = $conn->real_escape_string($_POST['topik_2']);
    $tp3 = $conn->real_escape_string($_POST['topik_3']);
    $tpsum = $conn->real_escape_string($_POST['topik_sumatif']);
    $conn->query("UPDATE penugasan SET topik_1='$tp1', topik_2='$tp2', topik_3='$tp3', topik_sumatif='$tpsum' WHERE id=$id_penugasan");

    foreach ($_POST['siswa'] as $id_siswa => $nilai) {
        $t1 = (float)$nilai['tugas1']; $t2 = (float)$nilai['tugas2']; $t3 = (float)$nilai['tugas3'];
        $sumatif = (float)$nilai['sumatif']; $sas = (float)$nilai['sas'];
        $catatan = $conn->real_escape_string($nilai['catatan'] ?? '');
        
        $rata_tugas = ($t1 + $t2 + $t3) / 3;
        $nilai_akhir = ($rata_tugas * 0.3) + ($sumatif * 0.3) + ($sas * 0.4);

        $cek = $conn->query("SELECT id FROM nilai WHERE id_penugasan = $id_penugasan AND id_siswa = $id_siswa AND semester = '$periode_sem' AND tahun_ajaran = '$periode_thn' AND jenis_ujian = '$jenis_ujian_aktif'");
        if ($cek->num_rows > 0) {
            $conn->query("UPDATE nilai SET tugas_1='$t1', tugas_2='$t2', tugas_3='$t3', sumatif='$sumatif', sas='$sas', nilai_akhir='$nilai_akhir', catatan='$catatan' WHERE id_penugasan=$id_penugasan AND id_siswa=$id_siswa AND semester='$periode_sem' AND tahun_ajaran='$periode_thn' AND jenis_ujian='$jenis_ujian_aktif'");
        } else {
            $conn->query("INSERT INTO nilai (id_penugasan, id_siswa, tugas_1, tugas_2, tugas_3, sumatif, sas, nilai_akhir, catatan, semester, tahun_ajaran, jenis_ujian) VALUES ('$id_penugasan', '$id_siswa', '$t1', '$t2', '$t3', '$sumatif', '$sas', '$nilai_akhir', '$catatan', '$periode_sem', '$periode_thn', '$jenis_ujian_aktif')");
        }
    }
    $info['topik_1'] = $tp1; $info['topik_2'] = $tp2; $info['topik_3'] = $tp3; $info['topik_sumatif'] = $tpsum;
    $pesan_sukses = "Nilai, Topik Indikator, dan Deskripsi berhasil disimpan!";
}

$nilai_exist = [];
$q_exist = $conn->query("SELECT * FROM nilai WHERE id_penugasan = $id_penugasan AND semester = '$periode_sem' AND tahun_ajaran = '$periode_thn' AND jenis_ujian = '$jenis_ujian_aktif'");
while ($r = $q_exist->fetch_assoc()) { $nilai_exist[$r['id_siswa']] = $r; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Input Nilai - <?= htmlspecialchars($info['nama_mapel']); ?></title>
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
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md shadow-sm p-4 sm:px-8 border-b flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center">
                <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue p-2 rounded-lg bg-gray-100 mr-4"><i class="fa-solid fa-bars text-xl"></i></button>
                <h2 class="text-xl font-extrabold text-penaburDark">Input E-Rapor</h2>
            </div>
            
            <form id="formPeriode" method="GET" class="flex gap-2 items-center">
                <input type="hidden" name="kelas" value="<?= $id_penugasan; ?>">
                <span class="text-xs font-bold text-gray-500 uppercase">Periode:</span>
                <select name="tahun" onchange="document.getElementById('formPeriode').submit()" class="px-3 py-2 rounded-xl border text-xs font-bold bg-blue-50 text-penaburBlue outline-none">
                    <?php foreach(array_unique($list_tahun) as $thn): ?> <option value="<?= $thn; ?>" <?= $periode_thn == $thn ? 'selected' : ''; ?>><?= $thn; ?></option> <?php endforeach; ?>
                </select>
                <select name="semester" onchange="document.getElementById('formPeriode').submit()" class="px-3 py-2 rounded-xl border text-xs font-bold bg-blue-50 text-penaburBlue outline-none">
                    <option value="Ganjil" <?= $periode_sem == 'Ganjil' ? 'selected' : ''; ?>>Ganjil</option>
                    <option value="Genap" <?= $periode_sem == 'Genap' ? 'selected' : ''; ?>>Genap</option>
                </select>
                <a href="dashboard" class="ml-2 bg-gray-100 px-4 py-2 rounded-xl font-bold text-sm text-gray-600 hover:bg-gray-200">Kembali</a>
            </form>
        </header>

        <main class="flex-1 p-4 sm:p-8 max-w-full mx-auto w-full">
            <div class="bg-penaburDark rounded-3xl p-6 md:p-8 mb-6 shadow-lg flex flex-col md:flex-row justify-between items-center text-white relative overflow-hidden">
                <div class="absolute right-0 top-0 opacity-10 text-9xl"><i class="fa-solid fa-file-pen"></i></div>
                <div class="relative z-10 w-full mb-4 md:mb-0">
                    <span class="px-3 py-1 bg-penaburGold text-penaburDark text-xs font-black rounded-lg uppercase tracking-wider mb-3 inline-block">Kelas <?= htmlspecialchars($info['nama_kelas']); ?></span>
                    <h1 class="text-3xl font-black mb-1"><?= htmlspecialchars($info['nama_mapel']); ?></h1>
                    <p class="text-blue-200 font-medium">KKM: <span class="font-bold text-white"><?= $kkm_mapel; ?></span> | Periode Aktif: <span class="font-bold text-penaburGold"><?= $periode_sem; ?> <?= $periode_thn; ?> (<?= $jenis_ujian_aktif; ?>)</span></p>
                </div>
                <div class="relative z-10 text-right w-full md:w-auto">
                    <?php if($is_open): ?>
                        <div class="bg-green-500/20 border border-green-400 text-green-300 px-4 py-3 rounded-xl text-sm font-bold shadow-md"><i class="fa-solid fa-lock-open mr-2"></i> Portal Aktif (<?= $pengaturan['jenis_ujian'] ?>) s/d <?= date('d M', strtotime($pengaturan['tanggal_selesai'])); ?></div>
                    <?php else: ?>
                        <div class="bg-red-500/20 border border-red-400 text-red-300 px-4 py-3 rounded-xl text-sm font-bold shadow-md"><i class="fa-solid fa-lock mr-2"></i> Portal Ditutup (Hanya Baca)</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if(isset($pesan_sukses)): ?><div class="bg-green-100 text-green-700 p-4 rounded-xl mb-6 font-bold shadow-sm border border-green-200"><i class="fa-solid fa-check-circle mr-2"></i> <?= $pesan_sukses; ?></div><?php endif; ?>

            <form method="POST" id="formNilai">
                
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 mb-6">
                    <h3 class="font-extrabold text-penaburDark mb-4 border-b pb-2"><i class="fa-solid fa-tags text-penaburGold mr-2"></i> Set Indikator / Topik Materi</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Topik Tugas 1</label><input type="text" name="topik_1" id="tp1" value="<?= htmlspecialchars($info['topik_1']); ?>" class="w-full text-xs px-3 py-2 border rounded-lg focus:ring-2 focus:ring-penaburBlue outline-none" <?= !$is_open ? 'readonly' : ''; ?>></div>
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Topik Tugas 2</label><input type="text" name="topik_2" id="tp2" value="<?= htmlspecialchars($info['topik_2']); ?>" class="w-full text-xs px-3 py-2 border rounded-lg focus:ring-2 focus:ring-penaburBlue outline-none" <?= !$is_open ? 'readonly' : ''; ?>></div>
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Topik Tugas 3</label><input type="text" name="topik_3" id="tp3" value="<?= htmlspecialchars($info['topik_3']); ?>" class="w-full text-xs px-3 py-2 border rounded-lg focus:ring-2 focus:ring-penaburBlue outline-none" <?= !$is_open ? 'readonly' : ''; ?>></div>
                        <div><label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Topik Sumatif</label><input type="text" name="topik_sumatif" id="tpsum" value="<?= htmlspecialchars($info['topik_sumatif']); ?>" class="w-full text-xs px-3 py-2 border rounded-lg focus:ring-2 focus:ring-penaburBlue outline-none" <?= !$is_open ? 'readonly' : ''; ?>></div>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-center">
                            <thead class="bg-gray-50 border-b text-[10px] font-black text-gray-500 uppercase tracking-widest">
                                <tr>
                                    <th class="px-4 py-4 text-left border-r min-w-[200px]">Nama Siswa</th>
                                    <th class="px-2 py-4 border-r">Tugas 1</th>
                                    <th class="px-2 py-4 border-r">Tugas 2</th>
                                    <th class="px-2 py-4 border-r">Tugas 3</th>
                                    <th class="px-2 py-4 border-r bg-blue-50 text-penaburBlue">Sumatif</th>
                                    <th class="px-2 py-4 border-r bg-blue-50 text-penaburBlue">Ujian SAS</th>
                                    <th class="px-4 py-4 border-r bg-yellow-50 text-yellow-700">Nilai Akhir</th>
                                    <th class="px-3 py-4 border-r bg-purple-50 text-purple-700">Grade</th>
                                    <th class="px-4 py-4 border-r min-w-[300px] bg-green-50 text-green-700">Deskripsi (Auto)</th>
                                    <th class="px-4 py-4">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php 
                                $no = 1;
                                while($s = $siswa_q->fetch_assoc()): 
                                    $id_s = $s['id'];
                                    $nama_panggilan = explode(' ', trim($s['nama_lengkap']))[0];
                                    $n = $nilai_exist[$id_s] ?? ['tugas_1'=>0, 'tugas_2'=>0, 'tugas_3'=>0, 'sumatif'=>0, 'sas'=>0, 'nilai_akhir'=>0, 'catatan'=>''];
                                    
                                    $catatan_db = $n['catatan'] ?? '';
                                ?>
                                <tr class="hover:bg-gray-50 transition baris-siswa" data-nama="<?= htmlspecialchars($nama_panggilan); ?>">
                                    <td class="px-4 py-3 text-left font-bold text-penaburDark border-r whitespace-nowrap">
                                        <span class="text-gray-400 mr-2"><?= $no++; ?>.</span> <?= htmlspecialchars($s['nama_lengkap']); ?>
                                    </td>
                                    <td class="px-2 py-2 border-r"><input type="number" step="0.1" name="siswa[<?= $id_s; ?>][tugas1]" value="<?= $n['tugas_1']; ?>" class="w-16 p-1 border rounded text-center focus:ring-1 outline-none calc-input" <?= !$is_open ? 'readonly' : ''; ?>></td>
                                    <td class="px-2 py-2 border-r"><input type="number" step="0.1" name="siswa[<?= $id_s; ?>][tugas2]" value="<?= $n['tugas_2']; ?>" class="w-16 p-1 border rounded text-center focus:ring-1 outline-none calc-input" <?= !$is_open ? 'readonly' : ''; ?>></td>
                                    <td class="px-2 py-2 border-r"><input type="number" step="0.1" name="siswa[<?= $id_s; ?>][tugas3]" value="<?= $n['tugas_3']; ?>" class="w-16 p-1 border rounded text-center focus:ring-1 outline-none calc-input" <?= !$is_open ? 'readonly' : ''; ?>></td>
                                    <td class="px-2 py-2 border-r bg-blue-50/30"><input type="number" step="0.1" name="siswa[<?= $id_s; ?>][sumatif]" value="<?= $n['sumatif']; ?>" class="w-16 p-1 border border-blue-200 rounded text-center focus:ring-1 outline-none calc-input" <?= !$is_open ? 'readonly' : ''; ?>></td>
                                    <td class="px-2 py-2 border-r bg-blue-50/30"><input type="number" step="0.1" name="siswa[<?= $id_s; ?>][sas]" value="<?= $n['sas']; ?>" class="w-16 p-1 border border-blue-200 rounded text-center focus:ring-1 outline-none calc-input" <?= !$is_open ? 'readonly' : ''; ?>></td>
                                    
                                    <td class="px-4 py-3 border-r bg-gray-100 font-black text-lg text-penaburDark result-akhir"><?= round($n['nilai_akhir'], 1); ?></td>
                                    <td class="px-3 py-3 border-r bg-purple-50/30 font-extrabold text-lg text-purple-700 result-grade">-</td>
                                    
                                    <td class="px-2 py-2 border-r bg-green-50/30">
                                        <textarea name="siswa[<?= $id_s; ?>][catatan]" class="input-catatan w-full text-xs p-2 border border-green-200 rounded-lg outline-none focus:ring-1 focus:ring-green-400" rows="3" placeholder="Catatan otomatis muncul..." <?= !$is_open ? 'readonly' : ''; ?>><?= htmlspecialchars($catatan_db); ?></textarea>
                                    </td>

                                    <td class="px-4 py-3 result-status"><span class="text-gray-400 italic">Belum Set</span></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="p-6 bg-gray-50 border-t flex justify-end">
                        <?php if($is_open): ?>
                            <button type="submit" name="simpan_nilai" class="px-8 py-3 bg-penaburBlue text-white font-extrabold rounded-xl shadow-lg hover:bg-penaburDark transition"><i class="fa-solid fa-save mr-2"></i> Simpan Nilai</button>
                        <?php else: ?>
                            <button type="button" disabled class="px-8 py-3 bg-gray-300 text-gray-500 font-extrabold rounded-xl cursor-not-allowed"><i class="fa-solid fa-lock mr-2"></i> Kunci (Bukan Periode Aktif)</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        const kkm_dinamis = <?= $kkm_mapel; ?>;
        
        function hitungNilaiDanDeskripsi(row, forceUpdate = false) {
            const inputs = row.querySelectorAll('.calc-input');
            const catatanInput = row.querySelector('.input-catatan');
            const gradeText = row.querySelector('.result-grade');
            const statusText = row.querySelector('.result-status');
            const nama_siswa = row.getAttribute('data-nama');
            
            const tp1 = document.getElementById('tp1').value || 'Materi Tugas 1';
            const tp2 = document.getElementById('tp2').value || 'Materi Tugas 2';
            const tp3 = document.getElementById('tp3').value || 'Materi Tugas 3';
            const tpsum = document.getElementById('tpsum').value || 'Materi Sumatif';
            
            if(inputs.length > 0) {
                let t1 = parseFloat(inputs[0].value) || 0;
                let t2 = parseFloat(inputs[1].value) || 0;
                let t3 = parseFloat(inputs[2].value) || 0;
                let sum = parseFloat(inputs[3].value) || 0;
                let sas = parseFloat(inputs[4].value) || 0;
                
                let rataTugas = (t1 + t2 + t3) / 3;
                let akhir = (rataTugas * 0.3) + (sum * 0.3) + (sas * 0.4);
                
                row.querySelector('.result-akhir').innerText = akhir.toFixed(1);
                
                if(akhir > 0) {
                    // Update Status
                    if(akhir >= kkm_dinamis) { statusText.innerHTML = '<span class="text-green-600 font-bold">Lulus</span>'; } 
                    else { statusText.innerHTML = '<span class="text-red-500 font-bold">Tidak Lulus</span>'; }

                    // Update Grade (A-E)
                    let grade = 'E';
                    if (akhir >= 90) grade = 'A';
                    else if (akhir >= 80) grade = 'B';
                    else if (akhir >= kkm_dinamis) grade = 'C';
                    else if (akhir >= (kkm_dinamis - 10)) grade = 'D';
                    gradeText.innerText = grade;

                    // Update Deskripsi (Auto)
                    if(catatanInput && (!catatanInput.hasAttribute('data-edited') || forceUpdate)) {
                        let indikator = [ { nama: tp1, nilai: t1 }, { nama: tp2, nilai: t2 }, { nama: tp3, nilai: t3 }, { nama: tpsum, nilai: sum } ];
                        let filled = indikator.filter(i => i.nilai > 0);

                        if(filled.length > 0) {
                            let maxIndikator = filled.reduce((max, obj) => (obj.nilai > max.nilai) ? obj : max);
                            let minIndikator = filled.reduce((min, obj) => (obj.nilai < min.nilai) ? obj : min);

                            let teksDeskripsi = `${nama_siswa} menunjukkan penguasaan yang sangat baik pada materi ${maxIndikator.nama}.`;

                            if (minIndikator.nilai < kkm_dinamis && maxIndikator.nama !== minIndikator.nama) {
                                teksDeskripsi += ` Namun, ${nama_siswa} masih perlu bimbingan lebih lanjut pada materi ${minIndikator.nama}.`;
                            } else if (maxIndikator.nama !== minIndikator.nama) {
                                teksDeskripsi += ` Kemampuan pada materi ${minIndikator.nama} juga sudah baik dan dapat terus ditingkatkan.`;
                            }
                            catatanInput.value = teksDeskripsi;
                        }
                    }
                } else {
                    gradeText.innerText = '-';
                    statusText.innerHTML = '<span class="text-gray-400 italic">Belum Set</span>';
                }
            }
        }

        document.querySelectorAll('tr.baris-siswa').forEach(row => {
            const catatanInput = row.querySelector('.input-catatan');
            if(catatanInput) { catatanInput.addEventListener('input', function() { this.setAttribute('data-edited', 'true'); }); }
            
            row.querySelectorAll('.calc-input').forEach(input => {
                input.addEventListener('input', () => hitungNilaiDanDeskripsi(row, false));
            });

            // Eksekusi langsung pas halaman dibuka
            hitungNilaiDanDeskripsi(row, false);
        });

        document.querySelectorAll('#tp1, #tp2, #tp3, #tpsum').forEach(topikInput => {
            topikInput.addEventListener('input', () => {
                document.querySelectorAll('tr.baris-siswa').forEach(row => {
                    const catatan = row.querySelector('.input-catatan');
                    if(catatan && !catatan.hasAttribute('data-edited')) hitungNilaiDanDeskripsi(row, true);
                });
            });
        });
    </script>
</body>
</html>