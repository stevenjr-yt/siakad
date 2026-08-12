<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../koneksi.php';
$koneksi_db = isset($conn) ? $conn : (isset($koneksi) ? $koneksi : null);

// Proteksi halaman: kalau belum login, tendang balik ke halaman login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    if (!headers_sent()) {
        header("Location: index.php");
    }
    echo "<script>window.location.href='index.php';</script>";
    exit();
}

// Proses Logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    if (!headers_sent()) {
        header("Location: index.php");
    }
    echo "<script>window.location.href='index.php';</script>";
    exit();
}

$user_id = $_SESSION['id_user'];
$nama_lengkap_sesi = strtoupper(trim($_SESSION['nama_lengkap']));
$nama_kelas = 'Belum Ada Kelas';

// Ambil data Kelas dari database
if ($koneksi_db) {
    try {
        $stmt_user = $koneksi_db->prepare("SELECT k.nama_kelas FROM users u LEFT JOIN kelas k ON u.id_kelas = k.id WHERE u.id = ?");
        if ($stmt_user) {
            $stmt_user->bind_param("i", $user_id);
            $stmt_user->execute();
            $user_data = $stmt_user->get_result()->fetch_assoc();
            $nama_kelas = $user_data['nama_kelas'] ? $user_data['nama_kelas'] : 'Belum Ada Kelas';
        }
    } catch (Throwable $e) {
        // Abaikan error jika kolom/tabel berbeda, biarkan nama kelas default
    }
}

// Data Nilai SAS (Hardcoded dari Excel)
$data_sas = [
    "ANABEL FLECIA SIJABAT" => 85,
    "ANGELICA NABILA ZETA" => 77,
    "CHRISTIAN SAMUEL ARDANA" => 83.5,
    "DALVIN WILLIAM" => 91,
    "EFRATA CEN" => 98.5,
    "EUGENIA MICHELLE TJANG" => 89.5,
    "FARIS DESMARYANTO" => 82,
    "Grace Abigail Mawar Tarigan" => 80.5,
    "JAMES IMANUEL GUNAWAN" => 80,
    "JENNIFER ANASTASYA CHANDRA" => 86.5,
    "JESELINE HERVI KEZYANA" => 77.5,
    "JOCELYN ALICIA WIJAYA" => 91,
    "JOVANIC AXELLE ONGGORO" => 89.5,
    "JUSTIN LEONARD CHIN" => 95.5,
    "MICHAEL JUSTIN BOENTORO" => 91,
    "MICHAEL KEN" => 88,
    "NAFAREL KIEMDRA TJONG" => 86.5,
    "NELSON JEFFRANDO" => 89.5,
    "SAMUEL LIM" => 86.5,
    "TAN FEBRIAN CHANDRA" => 86.5,
    "VIRLICIA LIULI" => 76,
    "ANGELICA LYFIE" => 85,
    "AXELL NATHANIEL JO" => 75,
    "CHRISVINE NATHALIE MULYONO" => 75,
    "DERREN VALERIAN" => 88.5,
    "EXCEL BUNTAMA" => 75,
    "FELLYCIA NATHANIA" => 80.5,
    "FENDI CHRISTIAN" => 75,
    "GRACIA VIOLETA CHIN" => 92.5,
    "JOYCELYN ANGELA CHRISTIANI" => 86.5,
    "KEENAN RAFAEL ANTONY" => 75,
    "MARTHA CHAROLINE CHRISTIAN" => 85,
    "MICHAEL TANJAYA" => 95.5,
    "MICHELLE LATHACIA CHRISTIANI" => 83.5,
    "RAYHEN RIANTO" => 82.5,
    "RIVERIOZ RAINERY" => 75,
    "SALOMO SINAGA" => 83.5,
    "SHEREN EFRATA NAOMI CANDRA" => 77.5,
    "STEVEN ANTONIUS BUDIMAN" => 75,
    "VINCENTCIUS CORDY RABET" => 75,
    "ZANATHAN CHRISTANO FABIAN" => 75,
    "CINDI" => 86.5
];

// Pencarian nilai dengan logika pencocokan nama yang fleksibel
$nilai_sas = 'Belum Ada Nilai';
foreach ($data_sas as $nama_excel => $nilai) {
    $nama_excel_clean = strtoupper(trim($nama_excel));
    
    if (strcasecmp($nama_excel_clean, $nama_lengkap_sesi) == 0 || 
        stripos($nama_lengkap_sesi, $nama_excel_clean) !== false || 
        stripos($nama_excel_clean, $nama_lengkap_sesi) !== false) {
        $nilai_sas = $nilai;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hasil Ujian | Portal KKA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">

    <div class="w-full max-w-2xl bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-8 text-center">
            <h1 class="text-3xl font-extrabold text-white tracking-tight">Hasil Ujian SAS</h1>
            <p class="text-blue-100 mt-2 font-medium">Mata Pelajaran Koding dan Kecerdasan Artifisial</p>
        </div>
        
        <div class="p-8">
            <div class="flex flex-col sm:flex-row justify-between bg-gray-50 rounded-xl p-6 mb-8 border border-gray-100">
                <div class="mb-4 sm:mb-0">
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Nama Lengkap</p>
                    <p class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars(ucwords(strtolower($_SESSION['nama_lengkap']))); ?></p>
                </div>
                <div class="sm:text-right">
                    <p class="text-xs text-gray-500 uppercase font-bold tracking-wider mb-1">Kelas</p>
                    <p class="text-lg font-bold text-gray-900"><?php echo htmlspecialchars($nama_kelas); ?></p>
                </div>
            </div>

            <div class="text-center py-8">
                <p class="text-gray-500 font-medium mb-3">Nilai Sumatif Akhir Semester (SAS) Anda</p>
                
                <div id="score-circle" class="inline-flex flex-col items-center justify-center w-48 h-48 rounded-full border-8 shadow-sm border-gray-200 bg-gray-50 text-gray-600 mb-6 transition-all duration-700 ease-in-out">
                    <span id="score-value" class="text-5xl font-black">0</span>
                </div>

                <div id="badge-container" class="opacity-0 transition-opacity duration-700 ease-in-out">
                    <?php if(is_numeric($nilai_sas) && $nilai_sas >= 75): ?>
                        <span class="inline-flex items-center bg-green-100 text-green-800 px-4 py-2 rounded-full font-bold shadow-sm">
                            <svg class="w-5 h-5 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                            TUNTAS
                        </span>
                    <?php elseif(is_numeric($nilai_sas) && $nilai_sas < 75): ?>
                        <span class="inline-flex items-center bg-red-100 text-red-800 px-4 py-2 rounded-full font-bold shadow-sm">
                            <svg class="w-5 h-5 mr-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                            BELUM TUNTAS
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-8 pt-6 border-t border-gray-100 text-center">
                <a href="nilai.php?action=logout" class="inline-flex items-center justify-center text-gray-500 hover:text-red-600 font-medium transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Keluar / Logout
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Ambil data asli dari PHP
            const nilaiAsli = "<?php echo htmlspecialchars($nilai_sas); ?>";
            const isNumeric = <?php echo is_numeric($nilai_sas) ? 'true' : 'false'; ?>;
            const nilaiAngka = parseFloat(nilaiAsli);

            // Set delay 5000 milliseconds (5 detik)
            setTimeout(() => {
                const scoreValue = document.getElementById('score-value');
                const scoreCircle = document.getElementById('score-circle');
                const badgeContainer = document.getElementById('badge-container');

                // 1. Ubah teks angka 0 menjadi nilai aslinya
                scoreValue.innerText = nilaiAsli;

                // 2. Ganti warna lingkaran dari abu-abu menjadi merah/hijau
                scoreCircle.classList.remove('border-gray-200', 'bg-gray-50', 'text-gray-600');
                
                if (isNumeric && nilaiAngka >= 75) {
                    // Warna Lulus (Hijau)
                    scoreCircle.classList.add('border-green-100', 'bg-green-50', 'text-green-600');
                } else {
                    // Warna Tidak Lulus / Belum Ada Nilai (Merah)
                    scoreCircle.classList.add('border-red-100', 'bg-red-50', 'text-red-600');
                }

                // 3. Munculkan badge Tuntas/Belum Tuntas dengan efek fade-in
                badgeContainer.classList.remove('opacity-0');
                badgeContainer.classList.add('opacity-100');

            }, 5000);
        });
    </script>
</body>
</html>