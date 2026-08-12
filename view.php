<?php
session_start();
error_reporting(0); 
date_default_timezone_set('Asia/Jakarta'); 
include 'koneksi.php';

$conn->query("SET time_zone = '+07:00'");

if (!isset($_SESSION['role']) || !isset($_GET['id'])) { header("Location: dashboard.php"); exit(); }

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$id_penugasan = (int)$_GET['id'];
$tgl_skrg = date('Y-m-d');

// =========================================================================
// FUNGSI DEWA AUTO-FIX: Kalkulasi Ulang Nilai 0 Akibat POST Hilang
// =========================================================================
if(!function_exists('autoFixNilaiNol')){
    function autoFixNilaiNol($conn, $id_k) {
        $q_zero = $conn->query("SELECT id_siswa FROM kuis_nilai WHERE id_kuis = $id_k AND nilai = 0");
        if ($q_zero && $q_zero->num_rows > 0) {
            $kunci = [];
            $q_kunci = $conn->query("SELECT id, kunci_jawaban FROM kuis_soal WHERE id_kuis = $id_k AND tipe='pg'");
            $total_soal = $q_kunci->num_rows;
            if ($total_soal > 0) {
                while($k = $q_kunci->fetch_assoc()) {
                    $kunci[$k['id']] = strtoupper(trim($k['kunci_jawaban']));
                }
                while($z = $q_zero->fetch_assoc()) {
                    $id_s = $z['id_siswa'];
                    $q_ans = $conn->query("SELECT id_soal, jawaban FROM kuis_jawaban_siswa WHERE id_kuis = $id_k AND id_siswa = $id_s");
                    if ($q_ans && $q_ans->num_rows > 0) {
                        $benar = 0;
                        while($ans = $q_ans->fetch_assoc()) {
                            if (isset($kunci[$ans['id_soal']]) && strtoupper(trim($ans['jawaban'])) == $kunci[$ans['id_soal']]) {
                                $benar++;
                            }
                        }
                        $nilai_baru = ($benar / $total_soal) * 100;
                        if ($nilai_baru > 0) {
                            $conn->query("UPDATE kuis_nilai SET nilai = $nilai_baru WHERE id_kuis = $id_k AND id_siswa = $id_s");
                        }
                    }
                }
            }
        }
    }
}

// =========================================================================
// 1. MINI API (SCOREBOARD, AUTOSAVE, DAN GENERATE BARCODE)
// =========================================================================
if (isset($_GET['api']) && $_GET['api'] == 'scoreboard') {
    while (ob_get_level()) { ob_end_clean(); } 
    header('Content-Type: application/json');
    $id_k = (int)$_GET['id_kuis'];
    
    autoFixNilaiNol($conn, $id_k);
    
    $res = $conn->query("
        SELECT u.id, u.nama_lengkap, MAX(kn.nilai) as nilai, MIN(kn.waktu_selesai) as waktu_selesai,
        GROUP_CONCAT(kn.catatan SEPARATOR ' | ') as catatan
        FROM kuis_nilai kn 
        JOIN users u ON kn.id_siswa = u.id 
        WHERE kn.id_kuis = $id_k 
        GROUP BY u.id, u.nama_lengkap 
        ORDER BY nilai DESC, waktu_selesai ASC
    ");
    
    $data = [];
    if($res) { while($row = $res->fetch_assoc()) $data[] = $row; }
    echo json_encode($data);
    exit;
}

if (isset($_GET['api']) && $_GET['api'] == 'autosave_jawaban') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    $id_k = (int)$_POST['id_kuis'];
    $id_soal = (int)$_POST['id_soal'];
    $jwb = $conn->real_escape_string($_POST['jawaban']);
    
    $conn->query("INSERT INTO kuis_jawaban_siswa (id_kuis, id_siswa, id_soal, jawaban) VALUES ($id_k, $user_id, $id_soal, '$jwb') ON DUPLICATE KEY UPDATE jawaban='$jwb'");
    echo json_encode(['status' => 'ok']);
    exit;
}

if (isset($_GET['api']) && $_GET['api'] == 'generate_qr' && $role != 'siswa') {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    
    $token = bin2hex(random_bytes(6)); 
    $id_sesi = (int)$_GET['id_sesi'];
    
    $conn->query("UPDATE absensi_sesi SET token_sekarang='$token' WHERE id=$id_sesi AND id_penugasan=$id_penugasan");
    
    echo json_encode(['token' => $token]);
    exit;
}

// =========================================================================
// 2. INJEKSI DATABASE SILENT & PATH CONTAINER
// =========================================================================
$upload_dir = __DIR__ . '/siakad/uploads/';
if (!is_dir($upload_dir)) { 
    @mkdir($upload_dir, 0777, true); 
    @chmod($upload_dir, 0777);
}

@$conn->query("CREATE TABLE IF NOT EXISTS materi (id INT AUTO_INCREMENT PRIMARY KEY, id_penugasan INT, judul VARCHAR(255), jenis ENUM('file', 'link'), url_file VARCHAR(500), tanggal DATETIME DEFAULT CURRENT_TIMESTAMP)");
@$conn->query("CREATE TABLE IF NOT EXISTS kuis (id INT AUTO_INCREMENT PRIMARY KEY, id_penugasan INT, judul VARCHAR(255), deskripsi TEXT, status ENUM('aktif', 'tutup') DEFAULT 'aktif', tanggal DATETIME DEFAULT CURRENT_TIMESTAMP)");
@$conn->query("CREATE TABLE IF NOT EXISTS kuis_soal (id INT AUTO_INCREMENT PRIMARY KEY, id_kuis INT, tipe ENUM('pg', 'essay'), pertanyaan TEXT, opsi_a VARCHAR(255), opsi_b VARCHAR(255), opsi_c VARCHAR(255), opsi_d VARCHAR(255), kunci_jawaban VARCHAR(10), poin INT DEFAULT 10)");
@$conn->query("CREATE TABLE IF NOT EXISTS kuis_nilai (id INT AUTO_INCREMENT PRIMARY KEY, id_kuis INT, id_siswa INT, nilai FLOAT, waktu_selesai DATETIME DEFAULT CURRENT_TIMESTAMP)");
@$conn->query("CREATE TABLE IF NOT EXISTS kuis_jawaban_siswa (id INT AUTO_INCREMENT PRIMARY KEY, id_kuis INT, id_siswa INT, id_soal INT, jawaban TEXT, UNIQUE KEY unique_jawaban (id_siswa, id_kuis, id_soal))");
@$conn->query("CREATE TABLE IF NOT EXISTS absensi_sesi (id INT AUTO_INCREMENT PRIMARY KEY, id_penugasan INT, tanggal DATE, token_sekarang VARCHAR(50))");
@$conn->query("CREATE TABLE IF NOT EXISTS absensi_siswa (id INT AUTO_INCREMENT PRIMARY KEY, id_sesi INT, id_siswa INT, status ENUM('H','A','I','S') NULL DEFAULT NULL, waktu DATETIME)");
@$conn->query("CREATE TABLE IF NOT EXISTS forum_kelas (id INT AUTO_INCREMENT PRIMARY KEY, id_penugasan INT, id_user INT, pesan TEXT, file_lampiran VARCHAR(500) NULL, tanggal DATETIME DEFAULT CURRENT_TIMESTAMP)");

$cek_kol_kuis = $conn->query("SHOW COLUMNS FROM kuis LIKE 'waktu_mulai'");
if ($cek_kol_kuis && $cek_kol_kuis->num_rows == 0) {
    $conn->query("ALTER TABLE kuis ADD COLUMN waktu_mulai DATETIME NULL DEFAULT NULL");
    $conn->query("ALTER TABLE kuis ADD COLUMN waktu_selesai DATETIME NULL DEFAULT NULL");
    $conn->query("ALTER TABLE kuis ADD COLUMN batas_percobaan INT DEFAULT 1");
}
$cek_kol_nilai = $conn->query("SHOW COLUMNS FROM kuis_nilai LIKE 'catatan'");
if ($cek_kol_nilai && $cek_kol_nilai->num_rows == 0) {
    $conn->query("ALTER TABLE kuis_nilai ADD COLUMN catatan VARCHAR(255) DEFAULT NULL");
}
$cek_kol_pengawas = $conn->query("SHOW COLUMNS FROM kuis LIKE 'mode_pengawas'");
if ($cek_kol_pengawas && $cek_kol_pengawas->num_rows == 0) {
    $conn->query("ALTER TABLE kuis ADD COLUMN mode_pengawas ENUM('on', 'off') DEFAULT 'off'");
}
$cek_kol_tampil = $conn->query("SHOW COLUMNS FROM kuis LIKE 'tampil_hasil'");
if ($cek_kol_tampil && $cek_kol_tampil->num_rows == 0) {
    $conn->query("ALTER TABLE kuis ADD COLUMN tampil_hasil ENUM('ya', 'tidak') DEFAULT 'tidak'");
}

// =========================================================================
// 3. LOGIKA BACKEND UTAMA
// =========================================================================

// CATCH SILENT ERROR
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
    header("Location: ?id=$id_penugasan&msg=gagal_post&tab=materi");
    exit;
}

if (isset($_GET['hapus_sesi_absen']) && $role != 'siswa') {
    $id_sesi_h = (int)$_GET['hapus_sesi_absen'];
    $conn->query("DELETE FROM absensi_sesi WHERE id = $id_sesi_h");
    $conn->query("DELETE FROM absensi_siswa WHERE id_sesi = $id_sesi_h");
    header("Location: ?id=$id_penugasan&msg=sesi_hapus&tab=absensi");
    exit;
}

if (isset($_POST['buat_sesi_absen']) && $role != 'siswa') {
    $tgl_sesi = $conn->real_escape_string($_POST['tanggal_sesi']);
    $conn->query("INSERT INTO absensi_sesi (id_penugasan, tanggal, token_sekarang) VALUES ($id_penugasan, '$tgl_sesi', '')");
    header("Location: ?id=$id_penugasan&msg=sesi_ok&tab=absensi");
    exit;
}

if (isset($_GET['absen_token']) && $role == 'siswa') {
    $token = $conn->real_escape_string($_GET['absen_token']);
    $cek_sesi = $conn->query("SELECT id FROM absensi_sesi WHERE id_penugasan=$id_penugasan AND token_sekarang='$token'");
    
    if ($cek_sesi->num_rows > 0) {
        $id_sesi = $cek_sesi->fetch_assoc()['id'];
        $waktu = date('Y-m-d H:i:s');
        $cek_absen = $conn->query("SELECT id FROM absensi_siswa WHERE id_sesi=$id_sesi AND id_siswa=$user_id");
        if ($cek_absen->num_rows == 0) {
            $conn->query("INSERT INTO absensi_siswa (id_sesi, id_siswa, status, waktu) VALUES ($id_sesi, $user_id, 'H', '$waktu')");
        } else {
            $conn->query("UPDATE absensi_siswa SET status='H', waktu='$waktu' WHERE id_sesi=$id_sesi AND id_siswa=$user_id");
        }
        header("Location: ?id=$id_penugasan&msg=absen_ok&tab=absensi");
        exit;
    } else {
        header("Location: ?id=$id_penugasan&msg=absen_gagal&tab=absensi");
        exit;
    }
}

if (isset($_POST['simpan_absen_manual']) && $role != 'siswa') {
    $id_sesi = (int)$_POST['id_sesi_absen'];
    if(isset($_POST['status_absen'])) {
        foreach ($_POST['status_absen'] as $id_s => $status) {
            $st = $conn->real_escape_string($status);
            $id_s = (int)$id_s;
            $cek = $conn->query("SELECT id FROM absensi_siswa WHERE id_sesi=$id_sesi AND id_siswa=$id_s");
            if ($cek->num_rows > 0) {
                $conn->query("UPDATE absensi_siswa SET status='$st' WHERE id_sesi=$id_sesi AND id_siswa=$id_s");
            } else {
                $conn->query("INSERT INTO absensi_siswa (id_sesi, id_siswa, status) VALUES ($id_sesi, $id_s, '$st')");
            }
        }
    }
    header("Location: ?id=$id_penugasan&msg=absen_manual&tab=absensi");
    exit;
}

if (isset($_GET['hapus_kuis']) && $role != 'siswa') {
    $id_h = (int)$_GET['hapus_kuis'];
    $conn->query("DELETE FROM kuis WHERE id = $id_h");
    $conn->query("DELETE FROM kuis_soal WHERE id_kuis = $id_h");
    $conn->query("DELETE FROM kuis_nilai WHERE id_kuis = $id_h");
    $conn->query("DELETE FROM kuis_jawaban_siswa WHERE id_kuis = $id_h");
    header("Location: ?id=$id_penugasan&msg=dihapus&tab=tugas");
    exit;
}

if (isset($_POST['simpan_materi'])) {
    $judul = $conn->real_escape_string($_POST['judul_materi']);
    $jenis = $_POST['jenis_materi'];
    $url_file = '';
    $waktu_jkt = date('Y-m-d H:i:s'); 
    
    if ($jenis == 'link') {
        $url_file = $conn->real_escape_string($_POST['link_url']);
        $conn->query("INSERT INTO materi (id_penugasan, judul, jenis, url_file, tanggal) VALUES ($id_penugasan, '$judul', '$jenis', '$url_file', '$waktu_jkt')");
        header("Location: ?id=$id_penugasan&msg=materi&tab=materi");
        exit;
    } else {
        if(isset($_FILES['file_materi']) && $_FILES['file_materi']['error'] == 0){
            $ext = pathinfo($_FILES['file_materi']['name'], PATHINFO_EXTENSION);
            $nama_bersih = preg_replace('/[^A-Za-z0-9\-]/', '_', pathinfo($_FILES['file_materi']['name'], PATHINFO_FILENAME));
            $filename = time() . '_' . $nama_bersih . '.' . $ext;
            $target_file = rtrim($upload_dir, '/') . '/' . $filename;
            
            if(move_uploaded_file($_FILES['file_materi']['tmp_name'], $target_file)){
                $url_file = $filename;
                $conn->query("INSERT INTO materi (id_penugasan, judul, jenis, url_file, tanggal) VALUES ($id_penugasan, '$judul', '$jenis', '$url_file', '$waktu_jkt')");
                header("Location: ?id=$id_penugasan&msg=materi&tab=materi");
                exit;
            } else {
                header("Location: ?id=$id_penugasan&msg=gagal_pindah&tab=materi");
                exit;
            }
        } else {
            header("Location: ?id=$id_penugasan&msg=gagal_materi&tab=materi");
            exit;
        }
    }
}

if (isset($_POST['simpan_kuis_baru'])) {
    $judul = $conn->real_escape_string($_POST['judul_kuis']);
    $deskripsi = $conn->real_escape_string($_POST['deskripsi_kuis']);
    $mode_pengawas = $conn->real_escape_string($_POST['mode_pengawas'] ?? 'off');
    $tampil_hasil = $conn->real_escape_string($_POST['tampil_hasil'] ?? 'tidak');
    
    $w_m_raw = $_POST['waktu_mulai'] ?? '';
    $w_mulai = !empty($w_m_raw) ? "'" . date('Y-m-d H:i:s', strtotime($w_m_raw)) . "'" : "NULL";
    
    $w_s_raw = $_POST['waktu_selesai'] ?? '';
    $w_selesai = !empty($w_s_raw) ? "'" . date('Y-m-d H:i:s', strtotime($w_s_raw)) . "'" : "NULL";
    
    $batas_coba = (int)($_POST['batas_percobaan'] ?? 1);
    $waktu_jkt = date('Y-m-d H:i:s');
    $id_edit = $_POST['id_edit_kuis'] ?? '';

    if ($id_edit != '') {
        $id_k = (int)$id_edit;
        $conn->query("UPDATE kuis SET judul='$judul', deskripsi='$deskripsi', waktu_mulai=$w_mulai, waktu_selesai=$w_selesai, batas_percobaan=$batas_coba, mode_pengawas='$mode_pengawas', tampil_hasil='$tampil_hasil' WHERE id=$id_k");
        $conn->query("DELETE FROM kuis_soal WHERE id_kuis=$id_k"); 
    } else {
        $conn->query("INSERT INTO kuis (id_penugasan, judul, deskripsi, waktu_mulai, waktu_selesai, batas_percobaan, mode_pengawas, tampil_hasil, tanggal) VALUES ($id_penugasan, '$judul', '$deskripsi', $w_mulai, $w_selesai, $batas_coba, '$mode_pengawas', '$tampil_hasil', '$waktu_jkt')");
        $id_k = $conn->insert_id;
    }
    
    $soal_data = json_decode($_POST['data_soal_json'], true);
    if($soal_data && is_array($soal_data)) {
        foreach($soal_data as $s) {
            $t = $conn->real_escape_string($s['tipe']);
            $p = $conn->real_escape_string($s['pertanyaan']);
            $oa = $conn->real_escape_string($s['opsi_a'] ?? '');
            $ob = $conn->real_escape_string($s['opsi_b'] ?? '');
            $oc = $conn->real_escape_string($s['opsi_c'] ?? '');
            $od = $conn->real_escape_string($s['opsi_d'] ?? '');
            $kj = $conn->real_escape_string($s['kunci'] ?? '');
            $conn->query("INSERT INTO kuis_soal (id_kuis, tipe, pertanyaan, opsi_a, opsi_b, opsi_c, opsi_d, kunci_jawaban, poin) VALUES ($id_k, '$t', '$p', '$oa', '$ob', '$oc', '$od', '$kj', 10)");
        }
    }
    header("Location: ?id=$id_penugasan&msg=" . ($id_edit != '' ? 'diedit' : 'dibuat') . "&tab=tugas");
    exit;
}

if (isset($_POST['submit_jawaban_kuis'])) {
    $id_k = (int)$_POST['id_kuis_dikerjakan'];
    $catatan_ujian = $conn->real_escape_string($_POST['catatan_cheat_input'] ?? ''); 
    
    $k_info = $conn->query("SELECT batas_percobaan FROM kuis WHERE id=$id_k")->fetch_assoc();
    $batas_maksimal = (int)$k_info['batas_percobaan'];
    $cek_coba = $conn->query("SELECT COUNT(id) as jml FROM kuis_nilai WHERE id_kuis=$id_k AND id_siswa=$user_id")->fetch_assoc();
    
    if ($cek_coba['jml'] >= $batas_maksimal) {
        header("Location: ?id=$id_penugasan&msg=habis&tab=tugas");
        exit;
    }

    $draft_backup = [];
    $q_db_draft = $conn->query("SELECT id_soal, jawaban FROM kuis_jawaban_siswa WHERE id_kuis=$id_k AND id_siswa=$user_id");
    if($q_db_draft){ 
        while($d = $q_db_draft->fetch_assoc()){ 
            $draft_backup[$d['id_soal']] = $d['jawaban']; 
        } 
    }

    $total_soal = 0;
    $jawaban_benar = 0;

    $q_soal = $conn->query("SELECT id, tipe, kunci_jawaban FROM kuis_soal WHERE id_kuis=$id_k");
    while($s = $q_soal->fetch_assoc()) {
        $total_soal++;
        
        $j_post = $_POST['jawaban'][$s['id']] ?? '';
        $jawaban_siswa = ($j_post !== '') ? $j_post : ($draft_backup[$s['id']] ?? '');
        
        if ($s['tipe'] == 'pg') {
            if(strtoupper(trim($jawaban_siswa)) == strtoupper(trim($s['kunci_jawaban']))) {
                $jawaban_benar++;
            }
        } 
        
        if($jawaban_siswa !== '') {
            $jwb_db = $conn->real_escape_string($jawaban_siswa);
            $conn->query("INSERT INTO kuis_jawaban_siswa (id_kuis, id_siswa, id_soal, jawaban) VALUES ($id_k, $user_id, {$s['id']}, '$jwb_db') ON DUPLICATE KEY UPDATE jawaban='$jwb_db'");
        }
    }
    
    $nilai_akhir = ($total_soal > 0) ? ($jawaban_benar / $total_soal) * 100 : 0;
    $waktu_submit = date('Y-m-d H:i:s');
    
    $conn->query("INSERT INTO kuis_nilai (id_kuis, id_siswa, nilai, catatan, waktu_selesai) VALUES ($id_k, $user_id, $nilai_akhir, '$catatan_ujian', '$waktu_submit')");

    header("Location: ?id=$id_penugasan&msg=nilai&skor=" . round($nilai_akhir, 1) . "&tab=tugas");
    exit;
}

if (isset($_GET['hapus_materi']) && $role != 'siswa') {
    $id_h = (int)$_GET['hapus_materi'];
    $conn->query("DELETE FROM materi WHERE id = $id_h");
    header("Location: ?id=$id_penugasan&msg=dihapus_materi&tab=materi");
    exit;
}

if (isset($_POST['kirim_forum'])) {
    $pesan = $conn->real_escape_string($_POST['pesan_forum']);
    $file_name = '';
    if(isset($_FILES['file_forum']) && $_FILES['file_forum']['error'] == 0){
        $ext = pathinfo($_FILES['file_forum']['name'], PATHINFO_EXTENSION);
        $nama_bersih = preg_replace('/[^A-Za-z0-9\-]/', '_', pathinfo($_FILES['file_forum']['name'], PATHINFO_FILENAME));
        $filename = time() . '_forum_' . $nama_bersih . '.' . $ext;
        $target_file = rtrim($upload_dir, '/') . '/' . $filename;
        if(move_uploaded_file($_FILES['file_forum']['tmp_name'], $target_file)){
            $file_name = $filename;
        }
    }
    
    if(!empty(trim($pesan)) || !empty($file_name)) {
        $conn->query("INSERT INTO forum_kelas (id_penugasan, id_user, pesan, file_lampiran, tanggal) VALUES ($id_penugasan, $user_id, '$pesan', '$file_name', NOW())");
    }
    header("Location: ?id=$id_penugasan&msg=forum_ok&tab=forum");
    exit;
}

$pesan_sukses = "";
$pesan_error = "";
if(isset($_GET['msg'])){
   if($_GET['msg'] == 'dibuat') $pesan_sukses = "Kuis berhasil dibuat & diterbitkan!";
   if($_GET['msg'] == 'diedit') $pesan_sukses = "Kuis berhasil diperbarui!";
   if($_GET['msg'] == 'dihapus') $pesan_sukses = "Kuis berhasil dihapus!";
   if($_GET['msg'] == 'dihapus_materi') $pesan_sukses = "Materi berhasil dihapus!";
   if($_GET['msg'] == 'materi') $pesan_sukses = "Materi berhasil diunggah!";
   if($_GET['msg'] == 'forum_ok') $pesan_sukses = "Pesan forum berhasil diposting!";
   if($_GET['msg'] == 'gagal_materi') $pesan_error = "Gagal mengunggah materi! Folder uploads mungkin tidak merespon.";
   if($_GET['msg'] == 'gagal_pindah') $pesan_error = "Gagal memindahkan file ke folder uploads! Cek permission container.";
   if($_GET['msg'] == 'gagal_ukuran') $pesan_error = "Ukuran file terlalu besar! Cek batas upload di php.ini.";
   if($_GET['msg'] == 'gagal_post') $pesan_error = "Gagal! File melebihi batas maksimal post_max_size di server PHP.";
   if($_GET['msg'] == 'nilai') $pesan_sukses = "Kuis berhasil dikumpulkan! Nilai kamu: " . htmlspecialchars($_GET['skor'] ?? '');
   if($_GET['msg'] == 'habis') $pesan_error = "Batas percobaan kuis kamu sudah habis!";
   if($_GET['msg'] == 'sesi_ok') $pesan_sukses = "Sesi absensi baru berhasil dibuat!";
   if($_GET['msg'] == 'sesi_hapus') $pesan_sukses = "Sesi absensi berhasil dihapus!";
   if($_GET['msg'] == 'absen_gagal') $pesan_error = "Barcode kadaluarsa atau tidak valid! Silakan scan ulang.";
   if($_GET['msg'] == 'absen_manual') $pesan_sukses = "Data absensi kelas berhasil disimpan/diupdate!";
}

$init_kuis_json = 'null';
$open_modal_kuis = 'false';
$default_tab = isset($_GET['tab']) ? $_GET['tab'] : 'materi';

if (isset($_GET['edit_kuis']) && $role != 'siswa') {
    $id_e = (int)$_GET['edit_kuis'];
    $q_k = $conn->query("SELECT * FROM kuis WHERE id = $id_e");
    if ($q_k->num_rows > 0) {
        $k_data = $q_k->fetch_assoc();
        $q_s = $conn->query("SELECT * FROM kuis_soal WHERE id_kuis = $id_e ORDER BY id ASC");
        $soals = [];
        while($s = $q_s->fetch_assoc()) {
            $soals[] = [
                'tipe' => $s['tipe'], 'pertanyaan' => $s['pertanyaan'],
                'opsi_a' => $s['opsi_a'], 'opsi_b' => $s['opsi_b'], 'opsi_c' => $s['opsi_c'], 'opsi_d' => $s['opsi_d'],
                'kunci' => strtolower($s['kunci_jawaban'])
            ];
        }
        $k_data['waktu_mulai'] = $k_data['waktu_mulai'] ? date('Y-m-d\TH:i', strtotime($k_data['waktu_mulai'])) : '';
        $k_data['waktu_selesai'] = $k_data['waktu_selesai'] ? date('Y-m-d\TH:i', strtotime($k_data['waktu_selesai'])) : '';
        $k_data['batas_percobaan'] = (int)$k_data['batas_percobaan'];
        $k_data['mode_pengawas'] = $k_data['mode_pengawas'] ?? 'off';
        $k_data['tampil_hasil'] = $k_data['tampil_hasil'] ?? 'tidak';
        $k_data['soals'] = $soals;
        
        $init_kuis_json = json_encode($k_data);
        $open_modal_kuis = 'true';
        $default_tab = 'tugas';
    }
}

$detail_q = $conn->query("SELECT p.*, m.nama_mapel, k.nama_kelas, u.nama_lengkap as pengajar, u.foto_profil as foto_pengajar, u.username as username_pengajar 
                          FROM penugasan p JOIN mapel m ON p.id_mapel = m.id 
                          JOIN kelas k ON p.id_kelas = k.id JOIN users u ON p.id_guru = u.id 
                          WHERE p.id = $id_penugasan");

if($detail_q->num_rows == 0) { echo "Kelas tidak ditemukan!"; exit(); }
$detail = $detail_q->fetch_assoc();

$list_materi = $conn->query("SELECT * FROM materi WHERE id_penugasan = $id_penugasan ORDER BY id DESC");
$array_kuis = [];
$list_kuis_q = $conn->query("SELECT * FROM kuis WHERE id_penugasan = $id_penugasan ORDER BY id ASC");
if($list_kuis_q) { while($k = $list_kuis_q->fetch_assoc()) { $array_kuis[] = $k; } }

$q_sesi_absen = $conn->query("SELECT id, tanggal FROM absensi_sesi WHERE id_penugasan=$id_penugasan ORDER BY tanggal DESC");
$semua_sesi = [];
if ($q_sesi_absen && $q_sesi_absen->num_rows > 0) { while($s = $q_sesi_absen->fetch_assoc()) { $semua_sesi[] = $s; } }

$daftar_siswa = [];
$siswa_q = $conn->query("SELECT id, nama_lengkap, username, foto_profil FROM users WHERE role = 'siswa' AND id_kelas = " . $detail['id_kelas'] . " ORDER BY nama_lengkap ASC");
if($siswa_q) { while($sw = $siswa_q->fetch_assoc()){ $daftar_siswa[] = $sw; } }

$data_absen_all = [];
$q_all_absen = $conn->query("SELECT id_sesi, id_siswa, status FROM absensi_siswa WHERE id_sesi IN (SELECT id FROM absensi_sesi WHERE id_penugasan=$id_penugasan)");
if($q_all_absen) { while($row = $q_all_absen->fetch_assoc()){ $data_absen_all[$row['id_sesi']][$row['id_siswa']] = $row['status']; } }

$rekap_nilai_siswa = [];
$q_rn = $conn->query("SELECT id_siswa, id_kuis, MAX(nilai) as max_nilai FROM kuis_nilai WHERE id_kuis IN (SELECT id FROM kuis WHERE id_penugasan=$id_penugasan) GROUP BY id_siswa, id_kuis");
if($q_rn) { while($rn = $q_rn->fetch_assoc()) { $rekap_nilai_siswa[$rn['id_siswa']][$rn['id_kuis']] = $rn['max_nilai']; } }

$forum_posts = $conn->query("SELECT f.*, u.nama_lengkap, u.foto_profil, u.role FROM forum_kelas f JOIN users u ON f.id_user = u.id WHERE f.id_penugasan = $id_penugasan ORDER BY f.id ASC");

$mode_halaman = 'utama'; 
$kuis_aktif = null;

if (isset($_GET['kerjakan']) && $role == 'siswa') {
    $mode_halaman = 'kerjakan';
    $id_k = (int)$_GET['kerjakan'];
    $kuis_aktif = $conn->query("SELECT * FROM kuis WHERE id = $id_k")->fetch_assoc();
    $soal_kuis = $conn->query("SELECT * FROM kuis_soal WHERE id_kuis = $id_k ORDER BY id ASC");
    $jawaban_draft = [];
    $q_draft = $conn->query("SELECT id_soal, jawaban FROM kuis_jawaban_siswa WHERE id_kuis=$id_k AND id_siswa=$user_id");
    if($q_draft){ while($d = $q_draft->fetch_assoc()){ $jawaban_draft[$d['id_soal']] = $d['jawaban']; } }
}

if (isset($_GET['review']) && $role == 'siswa') {
    $mode_halaman = 'review';
    $id_k = (int)$_GET['review'];
    $kuis_aktif = $conn->query("SELECT * FROM kuis WHERE id = $id_k")->fetch_assoc();
    if($kuis_aktif['tampil_hasil'] != 'ya') { die("Guru tidak mengizinkan melihat hasil kuis ini."); }
    $soal_kuis = $conn->query("SELECT * FROM kuis_soal WHERE id_kuis = $id_k ORDER BY id ASC");
    $jawaban_draft = [];
    $q_draft = $conn->query("SELECT id_soal, jawaban FROM kuis_jawaban_siswa WHERE id_kuis=$id_k AND id_siswa=$user_id");
    if($q_draft){ while($d = $q_draft->fetch_assoc()){ $jawaban_draft[$d['id_soal']] = $d['jawaban']; } }
}

$json_sb_awal = '[]';
if (isset($_GET['scoreboard']) && $role != 'siswa') {
    $mode_halaman = 'scoreboard';
    $id_k = (int)$_GET['scoreboard'];
    $kuis_aktif = $conn->query("SELECT * FROM kuis WHERE id = $id_k")->fetch_assoc();

    autoFixNilaiNol($conn, $id_k);

    $res_sb = $conn->query("
        SELECT u.id, u.nama_lengkap, MAX(kn.nilai) as nilai, MIN(kn.waktu_selesai) as waktu_selesai,
        GROUP_CONCAT(kn.catatan SEPARATOR ' | ') as catatan
        FROM kuis_nilai kn 
        JOIN users u ON kn.id_siswa = u.id 
        WHERE kn.id_kuis = $id_k 
        GROUP BY u.id, u.nama_lengkap 
        ORDER BY nilai DESC, waktu_selesai ASC
    ");
    $data_scoreboard_awal = [];
    if($res_sb) { while($row = $res_sb->fetch_assoc()) $data_scoreboard_awal[] = $row; }
    $json_sb_awal = json_encode($data_scoreboard_awal);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($detail['nama_mapel']); ?> - E-Learning</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>tailwind.config = { theme: { extend: { colors: { penaburBlue: '#0d3b66', penaburGold: '#f4d35e', penaburDark: '#07223b' } } } }</script>
    <style>[x-cloak] { display: none !important; } .scroll-smooth::-webkit-scrollbar { width: 6px; } .scroll-smooth::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }</style>
</head>
<body class="bg-gray-50 font-sans text-gray-800" x-data="{ sidebarOpen: false, tab: '<?= htmlspecialchars($default_tab); ?>', modalMateri: false, modalKuis: <?= $open_modal_kuis; ?>, modalBuatSesi: false, modalAbsenQR: false, activeSesiQR: 0 }">

    <?php if(isset($_GET['msg']) && $_GET['msg'] == 'absen_ok'): ?>
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3500)" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-md" x-transition.opacity>
        <div class="bg-white p-12 rounded-[2rem] text-center shadow-2xl transform scale-100 transition-transform duration-500 animate-bounce">
            <i class="fa-solid fa-circle-check text-[100px] text-green-500 mb-6 drop-shadow-md"></i>
            <h2 class="text-4xl font-black text-penaburDark mb-2 uppercase tracking-widest">Berhasil!</h2>
            <p class="text-gray-500 font-bold text-lg">Absensi HADIR kamu telah tercatat di sistem.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="fixed inset-0 z-40 bg-black/50" x-show="sidebarOpen" @click="sidebarOpen = false" x-transition.opacity style="display: none;"></div>

    <?php include 'sidebar.php'; ?>

    <div class="min-h-screen flex flex-col transition-all duration-300" :class="sidebarOpen ? 'lg:ml-64' : 'ml-0'">
        <header class="sticky top-0 z-30 bg-white/80 backdrop-blur-md shadow-sm p-4 sm:px-8 border-b flex justify-between items-center">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = !sidebarOpen" class="text-penaburDark hover:text-penaburBlue transition p-2 rounded-lg bg-gray-100">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <h2 class="text-lg font-extrabold text-penaburDark truncate"><?= htmlspecialchars($detail['nama_mapel']); ?></h2>
            </div>
            <a href="<?= $mode_halaman == 'utama' ? 'dashboard.php' : '?id='.$id_penugasan ?>" class="px-4 py-2 bg-gray-100 rounded-lg text-sm font-bold text-gray-600 hover:bg-gray-200 transition">Kembali</a>
        </header>

        <main class="flex-1 p-4 sm:p-8 max-w-7xl mx-auto w-full relative">
          <?php if($pesan_sukses): ?>
                <div class="bg-green-100 text-green-700 p-4 rounded-xl mb-6 font-bold border border-green-200 shadow-sm"><i class="fa-solid fa-check-circle mr-2"></i> <?= $pesan_sukses; ?></div>
            <?php endif; ?>
            
            <?php if($pesan_error): ?>
                <div class="bg-red-100 text-red-700 p-4 rounded-xl mb-6 font-bold border border-red-200 shadow-sm"><i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= $pesan_error; ?></div>
            <?php endif; ?>

            <?php if($mode_halaman == 'utama'): ?>
            <div class="bg-penaburDark rounded-3xl p-8 mb-8 relative overflow-hidden shadow-xl">
                <div class="absolute right-0 top-0 opacity-10 text-9xl"><i class="fa-solid fa-book-open"></i></div>
                <h1 class="text-3xl font-black text-white relative z-10 mb-2"><?= htmlspecialchars($detail['nama_mapel']); ?></h1>
                <p class="text-penaburGold font-bold tracking-widest uppercase text-sm relative z-10">Kelas <?= htmlspecialchars($detail['nama_kelas']); ?> | Pengajar: <?= htmlspecialchars($detail['pengajar']); ?></p>
            </div>

            <div class="flex border-b border-gray-200 mb-6 gap-6 px-2 overflow-x-auto scroll-smooth">
                <button @click="tab = 'materi'" :class="tab == 'materi' ? 'border-penaburBlue text-penaburBlue' : 'border-transparent text-gray-500'" class="pb-3 border-b-4 font-bold text-sm transition whitespace-nowrap"><i class="fa-solid fa-folder-open mr-2"></i> Materi</button>
                <button @click="tab = 'tugas'" :class="tab == 'tugas' ? 'border-penaburBlue text-penaburBlue' : 'border-transparent text-gray-500'" class="pb-3 border-b-4 font-bold text-sm transition whitespace-nowrap"><i class="fa-solid fa-clipboard-list mr-2"></i> Tugas & Kuis</button>
                <button @click="tab = 'forum'" :class="tab == 'forum' ? 'border-penaburBlue text-penaburBlue' : 'border-transparent text-gray-500'" class="pb-3 border-b-4 font-bold text-sm transition whitespace-nowrap"><i class="fa-solid fa-comments mr-2"></i> Forum Diskusi</button>
                <button @click="tab = 'absensi'" :class="tab == 'absensi' ? 'border-penaburBlue text-penaburBlue' : 'border-transparent text-gray-500'" class="pb-3 border-b-4 font-bold text-sm transition whitespace-nowrap"><i class="fa-solid fa-qrcode mr-2"></i> Absensi</button>
                <button @click="tab = 'partisipan'" :class="tab == 'partisipan' ? 'border-penaburBlue text-penaburBlue' : 'border-transparent text-gray-500'" class="pb-3 border-b-4 font-bold text-sm transition whitespace-nowrap"><i class="fa-solid fa-users mr-2"></i> Partisipan</button>
                
                <?php if($role != 'siswa'): ?>
                <button @click="tab = 'nilai'" :class="tab == 'nilai' ? 'border-penaburBlue text-penaburBlue' : 'border-transparent text-gray-500'" class="pb-3 border-b-4 font-bold text-sm transition whitespace-nowrap"><i class="fa-solid fa-star mr-2"></i> Rekap Nilai</button>
                <?php endif; ?>
            </div>

            <div class="bg-white rounded-3xl shadow-sm border p-6 min-h-[400px]">
                
                <div x-show="tab == 'materi'">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-lg text-penaburDark">Daftar Materi</h3>
                        <?php if($role != 'siswa'): ?>
                            <button @click="modalMateri = true" class="bg-penaburBlue text-white px-4 py-2 rounded-xl text-sm font-bold shadow hover:bg-penaburDark transition"><i class="fa-solid fa-upload mr-2"></i> Upload Materi</button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($list_materi->num_rows > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 w-full">
                            <?php while($m = $list_materi->fetch_assoc()): 
                                $ikon = $m['jenis'] == 'link' ? 'fa-link text-blue-500' : 'fa-file-word text-red-500';
                                $url = $m['jenis'] == 'link' ? $m['url_file'] : 'siakad/uploads/'.$m['url_file'];
                                
                                $is_youtube = false;
                                $yt_id = '';
                                if ($m['jenis'] == 'link') {
                                    if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $m['url_file'], $match)) {
                                        $is_youtube = true;
                                        $yt_id = $match[1];
                                    }
                                }
                            ?>
                                <?php if($is_youtube): ?>
                                    <div class="relative group flex flex-col border border-gray-100 rounded-2xl hover:shadow-lg transition bg-gray-50 overflow-hidden w-full">
                                        <iframe class="w-full aspect-video" src="https://www.youtube.com/embed/<?= $yt_id; ?>" title="<?= htmlspecialchars($m['judul']); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
                                        <div class="p-4 flex-1 flex flex-col justify-between">
                                            <div>
                                                <h4 class="font-bold text-penaburDark text-sm line-clamp-2 leading-tight"><?= htmlspecialchars($m['judul']); ?></h4>
                                                <p class="text-[10px] text-gray-400 mt-1 truncate"><i class="fa-regular fa-clock"></i> <?= date('d M Y, H:i', strtotime($m['tanggal'])); ?> WIB</p>
                                            </div>
                                        </div>
                                        <?php if($role != 'siswa'): ?>
                                        <div class="absolute top-3 right-3 flex gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity z-10">
                                            <a href="?id=<?= $id_penugasan ?>&hapus_materi=<?= $m['id'] ?>" onclick="return confirm('Yakin ingin menghapus materi ini?')" class="w-8 h-8 flex items-center justify-center bg-red-500 text-white rounded-lg shadow-md hover:bg-red-600"><i class="fa-solid fa-trash text-xs"></i></a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="relative group">
                                        <a href="<?= htmlspecialchars($url); ?>" target="_blank" class="flex items-start gap-4 p-4 border border-gray-100 rounded-2xl hover:shadow-lg transition bg-gray-50 break-words min-w-0 w-full overflow-hidden h-full">
                                            <div class="p-3 bg-white rounded-xl shadow-sm shrink-0 group-hover:scale-110 transition"><i class="fa-solid <?= $ikon; ?> text-2xl"></i></div>
                                            <div class="min-w-0 flex-1 pr-16">
                                                <h4 class="font-bold text-penaburDark text-sm line-clamp-2 leading-tight"><?= htmlspecialchars($m['judul']); ?></h4>
                                                <p class="text-[10px] text-gray-400 mt-1 truncate"><i class="fa-regular fa-clock"></i> <?= date('d M Y, H:i', strtotime($m['tanggal'])); ?> WIB</p>
                                            </div>
                                        </a>
                                        <?php if($role != 'siswa'): ?>
                                        <div class="absolute top-3 right-3 flex gap-2 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity">
                                            <a href="?id=<?= $id_penugasan ?>&hapus_materi=<?= $m['id'] ?>" onclick="return confirm('Yakin ingin menghapus materi ini?')" class="w-8 h-8 flex items-center justify-center bg-red-500 text-white rounded-lg shadow-md hover:bg-red-600"><i class="fa-solid fa-trash text-xs"></i></a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center border-2 border-dashed rounded-xl border-gray-300 text-gray-400 font-bold">Belum ada materi yang diunggah.</div>
                    <?php endif; ?>
                </div>

                <div x-show="tab == 'tugas'" style="display: none;">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-lg text-penaburDark">Tugas & Kuis Aktif</h3>
                        <?php if($role != 'siswa'): ?>
                            <button @click.prevent="modalKuis = true" class="bg-penaburGold text-penaburDark px-4 py-2 rounded-xl text-sm font-bold shadow hover:bg-yellow-500 transition cursor-pointer"><i class="fa-solid fa-plus mr-2"></i> Buat Kuis Baru</button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(count($array_kuis) > 0): ?>
                        <div class="space-y-4">
                            <?php foreach($array_kuis as $k): 
                                $sekarang = date('Y-m-d H:i:s');
                                $is_started = empty($k['waktu_mulai']) || $sekarang >= $k['waktu_mulai'];
                                $is_ended = !empty($k['waktu_selesai']) && $sekarang > $k['waktu_selesai'];
                                
                                $sdh_ngerjain = false;
                                $jml_coba = 0;
                                $max_nilai = 0;
                                $batas_coba = (int)$k['batas_percobaan'];
                                
                                if($role == 'siswa') {
                                    $cek_n = $conn->query("SELECT COUNT(id) as jml, MAX(nilai) as max_n FROM kuis_nilai WHERE id_kuis = {$k['id']} AND id_siswa = $user_id")->fetch_assoc();
                                    if($cek_n['jml'] > 0) {
                                        $sdh_ngerjain = true;
                                        $jml_coba = (int)$cek_n['jml'];
                                        $max_nilai = (float)$cek_n['max_n'];
                                    }
                                }
                            ?>
                            <div class="flex flex-col md:flex-row justify-between items-center p-5 border border-gray-100 rounded-2xl bg-gray-50 hover:border-penaburBlue transition gap-4">
                                <div class="flex items-center gap-4 w-full">
                                    <div class="p-4 bg-purple-100 text-purple-600 rounded-2xl"><i class="fa-solid fa-gamepad text-2xl"></i></div>
                                    <div>
                                        <h4 class="font-black text-penaburDark text-lg"><?= htmlspecialchars($k['judul']); ?></h4>
                                        <p class="text-xs text-gray-500 mb-1"><?= htmlspecialchars($k['deskripsi']); ?></p>
                                        <div class="flex flex-wrap gap-2 mt-2">
                                            <p class="text-[10px] text-gray-500 font-bold uppercase mb-1 bg-white px-2 py-1 rounded border shadow-sm">
                                                <i class="fa-solid fa-clock mr-1 text-penaburBlue"></i> 
                                                <?= $k['waktu_mulai'] ? date('d M Y, H:i', strtotime($k['waktu_mulai'])) : 'Kapan saja'; ?> 
                                                - 
                                                <?= $k['waktu_selesai'] ? date('d M Y, H:i', strtotime($k['waktu_selesai'])) : 'Tidak Dibatasi'; ?>
                                            </p>
                                            <?php if($k['mode_pengawas'] == 'on'): ?>
                                                <p class="text-[10px] text-red-700 bg-red-100 px-2 py-1 border border-red-200 rounded font-black uppercase mb-1 shadow-sm"><i class="fa-solid fa-video mr-1"></i> Mode Pengawas Aktif</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-2 w-full md:w-auto shrink-0 mt-2 md:mt-0">
                                    <?php if($role == 'siswa'): ?>
                                        <?php if($jml_coba >= $batas_coba): ?>
                                            <div class="px-6 py-2 bg-green-100 text-green-700 font-black rounded-xl text-center w-full whitespace-nowrap border border-green-200">
                                                Skor Max: <?= round($max_nilai, 1); ?> <span class="text-[9px] block">Percobaan Habis (<?= $jml_coba; ?>/<?= $batas_coba; ?>)</span>
                                            </div>
                                            <?php if($k['tampil_hasil'] == 'ya'): ?>
                                                <a href="?id=<?= $id_penugasan; ?>&review=<?= $k['id']; ?>" class="px-4 py-2 bg-blue-100 text-penaburBlue font-bold rounded-xl text-center w-full hover:bg-blue-200 transition text-xs"><i class="fa-solid fa-eye mr-1"></i> Review Jawaban</a>
                                            <?php endif; ?>
                                        <?php elseif($is_ended): ?>
                                            <div class="px-6 py-2 bg-gray-200 text-gray-500 font-bold rounded-xl text-center w-full whitespace-nowrap"><i class="fa-solid fa-lock mr-2"></i> Sudah Ditutup</div>
                                        <?php elseif(!$is_started): ?>
                                            <div class="px-6 py-2 bg-gray-200 text-gray-500 font-bold rounded-xl text-center w-full whitespace-nowrap"><i class="fa-solid fa-clock mr-2"></i> Belum Dibuka</div>
                                        <?php else: ?>
                                            <div class="flex flex-col gap-1 w-full text-center">
                                                <a href="?id=<?= $id_penugasan; ?>&kerjakan=<?= $k['id']; ?>" class="px-6 py-2 bg-penaburBlue text-white font-bold rounded-xl shadow hover:bg-penaburDark transition whitespace-nowrap"><i class="fa-solid fa-play mr-2"></i> Kerjakan Kuis</a>
                                                <span class="text-[9px] font-extrabold text-gray-400">Sisa <?= ($batas_coba - $jml_coba); ?>x percobaan lagi</span>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="flex gap-2 justify-end">
                                            <a href="?id=<?= $id_penugasan; ?>&edit_kuis=<?= $k['id']; ?>&tab=tugas" class="p-2 bg-yellow-400 text-penaburDark rounded-xl shadow hover:bg-yellow-500 transition tooltip" title="Edit Kuis"><i class="fa-solid fa-pen"></i></a>
                                            <a href="?id=<?= $id_penugasan; ?>&hapus_kuis=<?= $k['id']; ?>" onclick="return confirm('Kamu yakin ingin menghapus kuis ini secara permanen beserta nilai anak-anak?')" class="p-2 bg-red-500 text-white rounded-xl shadow hover:bg-red-600 transition tooltip" title="Hapus Kuis"><i class="fa-solid fa-trash"></i></a>
                                            <a href="?id=<?= $id_penugasan; ?>&scoreboard=<?= $k['id']; ?>" class="px-4 py-2 bg-purple-600 text-white font-bold rounded-xl shadow hover:bg-purple-800 transition whitespace-nowrap"><i class="fa-solid fa-trophy mr-2"></i> Scoreboard</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="p-8 text-center border-2 border-dashed rounded-xl border-gray-300 text-gray-400 font-bold">Belum ada kuis/tugas aktif.</div>
                    <?php endif; ?>
                </div>

                <div x-show="tab == 'forum'" style="display: none;" class="flex flex-col h-[500px]">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="font-bold text-lg text-penaburDark"><i class="fa-solid fa-comments text-penaburBlue mr-2"></i> Forum Kelas</h3>
                    </div>

                    <div class="flex-1 bg-gray-50 border rounded-2xl p-4 overflow-y-auto mb-4 space-y-4 scroll-smooth flex flex-col" id="forumContainer">
                        <?php if($forum_posts->num_rows > 0): ?>
                            <?php while($fp = $forum_posts->fetch_assoc()): 
                                $is_me = ($fp['id_user'] == $user_id);
                                $is_guru = ($fp['role'] != 'siswa');
                                $foto = $fp['foto_profil'] ?? '';
                            ?>
                                <div class="flex gap-3 <?= $is_me ? 'flex-row-reverse' : '' ?>">
                                    <div class="shrink-0 mt-1">
                                        <?php if(!empty($foto) && $foto != 'default.png' && file_exists('assets/'.$foto)): ?>
                                            <img src="assets/<?= htmlspecialchars($foto); ?>" class="w-8 h-8 rounded-full object-cover border border-gray-300 bg-white shadow-sm">
                                        <?php else: ?>
                                            <div class="w-8 h-8 <?= $is_guru ? 'bg-penaburBlue text-white' : 'bg-gray-200 text-gray-600' ?> rounded-full flex items-center justify-center font-bold text-xs shadow-sm"><?= substr($fp['nama_lengkap'], 0, 1); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="max-w-[80%] <?= $is_me ? 'text-right' : 'text-left' ?>">
                                        <p class="text-[10px] text-gray-500 font-bold mb-1">
                                            <?= htmlspecialchars($fp['nama_lengkap']); ?> 
                                            <?php if($is_guru): ?> <span class="bg-penaburGold text-penaburDark px-1.5 py-0.5 rounded text-[8px] ml-1 uppercase">Guru</span> <?php endif; ?>
                                            <span class="font-normal opacity-70 ml-2"><?= date('d M, H:i', strtotime($fp['tanggal'])); ?></span>
                                        </p>
                                        <div class="inline-block p-3 rounded-2xl shadow-sm text-sm <?= $is_me ? 'bg-penaburBlue text-white rounded-tr-none' : 'bg-white border text-gray-800 rounded-tl-none' ?>">
                                            <?php if(!empty($fp['pesan'])): ?>
                                                <p class="whitespace-pre-wrap break-words leading-relaxed"><?= htmlspecialchars($fp['pesan']); ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if(!empty($fp['file_lampiran'])): ?>
                                                <a href="siakad/uploads/<?= htmlspecialchars($fp['file_lampiran']); ?>" target="_blank" class="mt-2 flex items-center gap-2 p-2 <?= $is_me ? 'bg-white/20 hover:bg-white/30' : 'bg-gray-50 border hover:bg-gray-100' ?> rounded-xl transition text-xs font-bold w-fit">
                                                    <i class="fa-solid fa-file-arrow-down text-lg"></i>
                                                    <span class="truncate max-w-[150px]"><?= htmlspecialchars($fp['file_lampiran']); ?></span>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <script>
                                document.addEventListener("DOMContentLoaded", function() {
                                    const fc = document.getElementById("forumContainer");
                                    fc.scrollTop = fc.scrollHeight;
                                });
                            </script>
                        <?php else: ?>
                            <div class="m-auto text-center text-gray-400 font-bold p-8 border-2 border-dashed rounded-2xl w-full">Belum ada diskusi di forum ini. Jadilah yang pertama menyapa!</div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" enctype="multipart/form-data" action="?id=<?= $id_penugasan; ?>" class="flex gap-2 items-end bg-white p-2 border rounded-2xl shadow-sm focus-within:border-penaburBlue transition">
                        <label class="shrink-0 p-3 text-gray-400 hover:text-penaburBlue transition cursor-pointer tooltip" title="Lampirkan File (PDF, DOCX, dll)">
                            <i class="fa-solid fa-paperclip text-lg"></i>
                            <input type="file" name="file_forum" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar,.jpg,.png" class="hidden" onchange="document.getElementById('fileNameLabel').innerText = this.files[0] ? this.files[0].name : '';">
                        </label>
                        <div class="flex-1 flex flex-col">
                            <span id="fileNameLabel" class="text-[9px] font-bold text-penaburBlue px-2 truncate max-w-[200px]"></span>
                            <textarea name="pesan_forum" rows="1" class="w-full px-2 py-3 outline-none resize-none text-sm bg-transparent" placeholder="Ketik pesan diskusi di sini..." oninput="this.style.height = ''; this.style.height = Math.min(this.scrollHeight, 100) + 'px'"></textarea>
                        </div>
                        <button type="submit" name="kirim_forum" class="shrink-0 w-10 h-10 bg-penaburBlue text-white rounded-xl shadow hover:bg-penaburDark transition flex items-center justify-center mb-1 mr-1"><i class="fa-solid fa-paper-plane"></i></button>
                    </form>
                </div>

                <div x-show="tab == 'absensi'" style="display: none;">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4 border-b pb-4">
                        <div>
                            <h3 class="font-bold text-lg text-penaburDark">Rekap Kehadiran Kelas</h3>
                            <p class="text-xs text-gray-500">Total <?= count($semua_sesi); ?> Sesi Tercatat.</p>
                        </div>
                        <?php if($role != 'siswa'): ?>
                            <button @click.prevent="modalBuatSesi = true" class="bg-penaburGold text-penaburDark px-4 py-2 rounded-xl text-sm font-bold shadow hover:bg-yellow-500 transition cursor-pointer"><i class="fa-solid fa-plus mr-2"></i> Buat Sesi Absensi Baru</button>
                        <?php endif; ?>
                    </div>

                    <?php if($role != 'siswa'): ?>
                        <?php if(count($semua_sesi) == 0): ?>
                            <div class="p-8 text-center border-2 border-dashed rounded-xl border-gray-300 text-gray-400 font-bold">Belum ada sesi absensi. Silakan buat sesi baru.</div>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach($semua_sesi as $idx => $sesi): 
                                    $id_s_absen = $sesi['id'];
                                    $is_open = ($idx == 0) ? 'true' : 'false';
                                ?>
                                <div class="border rounded-2xl overflow-hidden bg-white shadow-sm" x-data="{ open: <?= $is_open ?> }">
                                    <button @click="open = !open" class="w-full flex justify-between items-center p-4 bg-gray-50 hover:bg-gray-100 transition border-b focus:outline-none">
                                        <div class="flex items-center gap-4">
                                            <div class="text-left">
                                                <h4 class="font-bold text-penaburDark text-md">
                                                    Sesi: <?= date('d F Y', strtotime($sesi['tanggal'])); ?>
                                                </h4>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <div @click.stop="modalAbsenQR = true; activeSesiQR = <?= $id_s_absen; ?>; $dispatch('start-qr', {id: <?= $id_s_absen; ?>})" class="bg-penaburBlue text-white px-3 py-1 rounded-lg text-xs font-bold shadow hover:bg-penaburDark transition cursor-pointer">
                                                    <i class="fa-solid fa-qrcode mr-1"></i> Buka Barcode
                                                </div>
                                                <a href="?id=<?= $id_penugasan; ?>&hapus_sesi_absen=<?= $id_s_absen; ?>" @click.stop="return confirm('Yakin ingin menghapus sesi absensi ini beserta seluruh data kehadiran siswa di dalamnya?')" class="bg-red-100 text-red-600 px-3 py-1 rounded-lg text-xs font-bold shadow-sm hover:bg-red-500 hover:text-white transition cursor-pointer">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <div class="text-gray-400 transform transition-transform duration-300" :class="open ? 'rotate-180' : ''">
                                            <i class="fa-solid fa-chevron-down"></i>
                                        </div>
                                    </button>
                                    
                                    <div x-show="open" x-transition class="p-4">
                                        <form method="POST" action="?id=<?= $id_penugasan; ?>">
                                            <input type="hidden" name="id_sesi_absen" value="<?= $id_s_absen; ?>">
                                            <div class="overflow-x-hidden sm:overflow-x-auto mt-2">
                                                <table class="w-full text-left text-sm border-collapse block sm:table">
                                                    <thead class="hidden sm:table-header-group bg-gray-100 border-y text-[10px] font-black text-gray-500 uppercase tracking-widest">
                                                        <tr>
                                                            <th class="px-4 py-3 w-10">No</th>
                                                            <th class="px-4 py-3">Nama Siswa</th>
                                                            <th class="px-4 py-3 text-center">Status Kehadiran</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100 block sm:table-row-group">
                                                        <?php 
                                                        $no = 1;
                                                        foreach($daftar_siswa as $sw):
                                                            $id_murid = $sw['id'];
                                                            $status = $data_absen_all[$id_s_absen][$id_murid] ?? null; 
                                                        ?>
                                                        <tr class="hover:bg-blue-50/50 transition flex flex-col sm:table-row border-b sm:border-0 p-3 sm:p-0">
                                                            <td class="px-2 sm:px-4 py-1 sm:py-3 font-bold text-gray-400 block sm:table-cell">
                                                                <span class="inline-block sm:hidden text-xs text-gray-500 mr-1 uppercase">No:</span><?= $no++; ?>
                                                            </td>
                                                            <td class="px-2 sm:px-4 py-1 sm:py-3 font-bold text-penaburDark block sm:table-cell text-lg sm:text-sm">
                                                                <?= htmlspecialchars($sw['nama_lengkap']); ?>
                                                                <?php if(is_null($status)): ?>
                                                                    <span class="ml-2 px-2 py-0.5 bg-gray-100 text-gray-400 font-bold text-[9px] rounded uppercase border">Belum Absen</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="px-2 sm:px-4 pb-2 sm:py-3 pt-2 sm:pt-3 block sm:table-cell">
                                                                <div class="flex flex-wrap items-center justify-center gap-2 sm:gap-6 bg-gray-50 p-3 sm:p-2 rounded-xl border w-full">
                                                                    <label class="cursor-pointer flex items-center gap-1 hover:opacity-70 transition tooltip" title="Hadir"><input type="radio" name="status_absen[<?= $id_murid; ?>]" value="H" class="text-green-500 w-6 h-6 sm:w-5 sm:h-5 focus:ring-0" <?= $status=='H'?'checked':''; ?>><span class="font-black text-sm sm:text-xs text-green-600">H</span></label>
                                                                    <label class="cursor-pointer flex items-center gap-1 hover:opacity-70 transition tooltip" title="Alpha"><input type="radio" name="status_absen[<?= $id_murid; ?>]" value="A" class="text-red-500 w-6 h-6 sm:w-5 sm:h-5 focus:ring-0" <?= $status=='A'?'checked':''; ?>><span class="font-black text-sm sm:text-xs text-red-600">A</span></label>
                                                                    <label class="cursor-pointer flex items-center gap-1 hover:opacity-70 transition tooltip" title="Izin"><input type="radio" name="status_absen[<?= $id_murid; ?>]" value="I" class="text-yellow-500 w-6 h-6 sm:w-5 sm:h-5 focus:ring-0" <?= $status=='I'?'checked':''; ?>><span class="font-black text-sm sm:text-xs text-yellow-600">I</span></label>
                                                                    <label class="cursor-pointer flex items-center gap-1 hover:opacity-70 transition tooltip" title="Sakit"><input type="radio" name="status_absen[<?= $id_murid; ?>]" value="S" class="text-blue-500 w-6 h-6 sm:w-5 sm:h-5 focus:ring-0" <?= $status=='S'?'checked':''; ?>><span class="font-black text-sm sm:text-xs text-blue-600">S</span></label>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="mt-4 text-right">
                                                <button type="submit" name="simpan_absen_manual" class="px-6 py-3 bg-penaburDark text-penaburGold font-black uppercase text-xs rounded-xl shadow hover:bg-black transition"><i class="fa-solid fa-save mr-2"></i> Simpan / Update Sesi Ini</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <?php 
                            $id_sesi_hari_ini = 0;
                            foreach($semua_sesi as $s) {
                                if($s['tanggal'] == $tgl_skrg) {
                                    $id_sesi_hari_ini = $s['id'];
                                    break;
                                }
                            }
                            $status_siswa_hr_ini = $data_absen_all[$id_sesi_hari_ini][$user_id] ?? null;
                        ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <div class="text-center p-8 bg-gray-50 rounded-2xl border border-gray-200 shadow-inner mb-6 relative overflow-hidden">
                                    <i class="fa-solid fa-user-check text-5xl text-gray-200 mb-4 relative z-10"></i>
                                    <h3 class="text-lg font-black text-penaburDark relative z-10">Kehadiran Hari Ini</h3>
                                    
                                    <?php if($id_sesi_hari_ini == 0): ?>
                                        <div class="mt-4 inline-block px-8 py-3 bg-gray-100 text-gray-500 font-black text-xl rounded-2xl shadow-sm border border-gray-200 relative z-10"><i class="fa-solid fa-clock mr-2"></i> BELUM ADA SESI</div>
                                    <?php else: ?>
                                        <?php 
                                        if($status_siswa_hr_ini == 'H') echo '<div class="mt-4 inline-block px-8 py-3 bg-green-500 text-white font-black text-xl rounded-2xl shadow-lg border border-green-600 relative z-10 animate-pulse"><i class="fa-solid fa-check mr-2"></i> HADIR</div>';
                                        elseif($status_siswa_hr_ini == 'S') echo '<div class="mt-4 inline-block px-8 py-3 bg-blue-500 text-white font-black text-xl rounded-2xl shadow-lg border border-blue-600 relative z-10"><i class="fa-solid fa-bed mr-2"></i> SAKIT</div>';
                                        elseif($status_siswa_hr_ini == 'I') echo '<div class="mt-4 inline-block px-8 py-3 bg-yellow-500 text-white font-black text-xl rounded-2xl shadow-lg border border-yellow-600 relative z-10"><i class="fa-solid fa-envelope-open-text mr-2"></i> IZIN</div>';
                                        elseif($status_siswa_hr_ini == 'A') echo '<div class="mt-4 inline-block px-8 py-3 bg-red-500 text-white font-black text-xl rounded-2xl shadow-lg border border-red-600 relative z-10"><i class="fa-solid fa-xmark mr-2"></i> ALPHA</div>';
                                        else echo '<div class="mt-4 inline-block px-8 py-3 bg-gray-500 text-white font-black text-xl rounded-2xl shadow-lg border border-gray-600 relative z-10"><i class="fa-solid fa-clock-rotate-left mr-2"></i> BELUM ABSEN</div>';
                                        ?>
                                    <?php endif; ?>
                                </div>

                                <?php if($id_sesi_hari_ini != 0 && is_null($status_siswa_hr_ini)): ?>
                                <div class="bg-white rounded-3xl shadow-sm border border-gray-200 p-6 text-center" x-data="qrScannerApp()">
                                    <h4 class="font-black text-penaburDark text-xl mb-2"><i class="fa-solid fa-camera text-penaburBlue mr-2"></i> Scanner Absensi</h4>
                                    <p class="text-xs text-gray-500 mb-6 font-bold">Arahkan kamera ke Barcode yang ditampilkan Guru.</p>
                                    
                                    <div id="reader" class="mx-auto w-full overflow-hidden rounded-2xl border-4 border-dashed border-gray-200 bg-gray-100" style="display: none; min-height: 250px;"></div>
                                    
                                    <div x-show="isScanning" style="display: none;" class="mt-4 mb-4">
                                        <label for="zoomSlider" class="block text-xs font-bold text-gray-600 mb-1"><i class="fa-solid fa-magnifying-glass-plus mr-1"></i> Zoom Kamera</label>
                                        <input type="range" id="zoomSlider" min="1" max="5" step="0.1" value="1" class="w-full accent-penaburBlue" @input="updateZoom($event.target.value)">
                                    </div>
                                    <div class="mt-2">
                                        <button x-show="!isScanning" @click="startScanner" type="button" class="w-full py-4 bg-penaburBlue text-white font-extrabold rounded-2xl shadow-lg hover:bg-penaburDark transition"><i class="fa-solid fa-qrcode mr-2"></i> Buka Kamera & Scan</button>
                                        <button x-show="isScanning" @click="stopScanner" type="button" class="w-full py-4 bg-red-500 text-white font-extrabold rounded-2xl shadow-lg hover:bg-red-600 transition" style="display: none;"><i class="fa-solid fa-xmark mr-2"></i> Tutup Kamera</button>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="bg-white rounded-3xl border p-6 shadow-sm h-fit">
                                <h4 class="font-black text-penaburDark text-lg mb-4 border-b pb-2"><i class="fa-solid fa-clock-rotate-left text-penaburGold mr-2"></i> Riwayat Kehadiranmu</h4>
                                
                                <?php if(count($semua_sesi) == 0): ?>
                                    <div class="p-6 text-center text-xs font-bold text-gray-400 italic bg-gray-50 rounded-xl border border-dashed">Belum ada sesi pertemuan.</div>
                                <?php else: ?>
                                    <div class="space-y-3 overflow-y-auto max-h-[400px] pr-2">
                                        <?php foreach($semua_sesi as $sesi): 
                                            if($sesi['tanggal'] == $tgl_skrg) continue; 
                                            $st_history = $data_absen_all[$sesi['id']][$user_id] ?? null;
                                        ?>
                                        <div class="flex justify-between items-center p-4 border rounded-xl bg-gray-50 hover:bg-white hover:shadow-md transition">
                                            <div>
                                                <p class="font-black text-penaburDark text-sm"><?= date('d M Y', strtotime($sesi['tanggal'])); ?></p>
                                                <p class="text-[9px] text-gray-400 uppercase font-bold">Sesi Pertemuan</p>
                                            </div>
                                            
                                            <?php 
                                            if($st_history == 'H') echo '<span class="px-3 py-1 bg-green-100 text-green-700 font-black text-[10px] rounded-lg border border-green-200 shadow-sm"><i class="fa-solid fa-check mr-1"></i> HADIR</span>';
                                            elseif($st_history == 'S') echo '<span class="px-3 py-1 bg-blue-100 text-blue-700 font-black text-[10px] rounded-lg border border-blue-200 shadow-sm"><i class="fa-solid fa-bed mr-1"></i> SAKIT</span>';
                                            elseif($st_history == 'I') echo '<span class="px-3 py-1 bg-yellow-100 text-yellow-700 font-black text-[10px] rounded-lg border border-yellow-200 shadow-sm"><i class="fa-solid fa-envelope mr-1"></i> IZIN</span>';
                                            elseif($st_history == 'A') echo '<span class="px-3 py-1 bg-red-100 text-red-700 font-black text-[10px] rounded-lg border border-red-200 shadow-sm"><i class="fa-solid fa-xmark mr-1"></i> ALPHA</span>';
                                            else echo '<span class="px-3 py-1 bg-gray-100 text-gray-500 font-black text-[10px] rounded-lg border border-gray-300 shadow-sm"><i class="fa-solid fa-minus mr-1"></i> BELUM ABSEN</span>';
                                            ?>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                        </div>

                        <script>
                            document.addEventListener('alpine:init', () => {
                                Alpine.data('qrScannerApp', () => ({
                                    isScanning: false,
                                    html5QrCode: null,
                                    startScanner() {
                                        this.isScanning = true;
                                        document.getElementById('reader').style.display = 'block';
                                        
                                        this.html5QrCode = new Html5Qrcode("reader");
                                        const config = { fps: 10, qrbox: { width: 250, height: 250 } };
                                        
                                        this.html5QrCode.start({ facingMode: "environment" }, config, (decodedText) => {
                                            if(decodedText.includes('absen_token=')) {
                                                this.html5QrCode.stop().then(() => {
                                                    this.isScanning = false;
                                                    document.getElementById('reader').style.display = 'none';
                                                    window.location.href = decodedText;
                                                });
                                            } else {
                                                alert("Bukan Barcode Absensi yang valid!");
                                                this.stopScanner();
                                            }
                                        }, (errorMessage) => { }).catch(err => {
                                            alert("Gagal mengakses kamera! Pastikan kamu memberikan izin akses kamera pada browser.");
                                            this.isScanning = false;
                                            document.getElementById('reader').style.display = 'none';
                                        });
                                    },
                                    stopScanner() {
                                        if (this.html5QrCode) {
                                            this.html5QrCode.stop().then(() => {
                                                this.isScanning = false;
                                                document.getElementById('reader').style.display = 'none';
                                            }).catch(err => { console.log("Gagal mematikan kamera", err); });
                                        }
                                    },
                                    updateZoom(val) {
                                        const video = document.querySelector('#reader video');
                                        if (video && video.srcObject) {
                                            const track = video.srcObject.getVideoTracks()[0];
                                            if (track && track.getCapabilities) {
                                                const capabilities = track.getCapabilities();
                                                if (capabilities.zoom) {
                                                    track.applyConstraints({ advanced: [{ zoom: parseFloat(val) }] }).catch(e => console.log('Zoom not supported'));
                                                }
                                            }
                                        }
                                    }
                                }))
                            })
                        </script>
                    <?php endif; ?>
                </div>

                <div x-show="tab == 'partisipan'" style="display: none;">
                    <h3 class="font-bold text-lg text-penaburDark mb-4">Pengajar</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 w-full mb-6">
                        <div class="flex items-center gap-3 p-3 border-2 border-penaburBlue rounded-xl hover:shadow-md transition w-full bg-blue-50 overflow-hidden relative">
                            <div class="absolute top-0 right-0 bg-penaburBlue text-white text-[9px] font-black px-2 py-1 rounded-bl-lg">GURU</div>
                            <?php 
                            $foto_guru = $detail['foto_pengajar'] ?? '';
                            if(!empty($foto_guru) && $foto_guru != 'default.png' && file_exists('assets/'.$foto_guru)): ?>
                                <img src="assets/<?= htmlspecialchars($foto_guru); ?>" alt="Foto" class="w-10 h-10 rounded-full object-cover border-2 border-penaburBlue shrink-0 shadow-sm bg-white">
                            <?php else: ?>
                                <div class="w-10 h-10 bg-penaburBlue text-white rounded-full flex items-center justify-center font-bold shrink-0 shadow-sm"><?= substr($detail['pengajar'], 0, 1); ?></div>
                            <?php endif; ?>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-penaburDark truncate"><?= htmlspecialchars($detail['pengajar']); ?></p>
                                <p class="text-[10px] text-penaburBlue uppercase truncate"><?= htmlspecialchars($detail['username_pengajar'] ?? 'GURU'); ?></p>
                            </div>
                        </div>
                    </div>

                    <h3 class="font-bold text-lg text-penaburDark mb-4">Siswa Terdaftar</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 w-full">
                        <?php 
                        foreach($daftar_siswa as $sw):
                            $foto_murid = $sw['foto_profil'] ?? ''; 
                        ?>
                            <div class="flex items-center gap-3 p-3 border rounded-xl hover:shadow-md transition w-full bg-white overflow-hidden">
                                <?php if(!empty($foto_murid) && $foto_murid != 'default.png' && file_exists('assets/'.$foto_murid)): ?>
                                    <img src="assets/<?= htmlspecialchars($foto_murid); ?>" alt="Foto" class="w-10 h-10 rounded-full object-cover border-2 border-penaburGold shrink-0 shadow-sm bg-gray-100">
                                <?php else: ?>
                                    <div class="w-10 h-10 bg-blue-100 text-penaburBlue rounded-full flex items-center justify-center font-bold shrink-0 shadow-sm"><?= substr($sw['nama_lengkap'], 0, 1); ?></div>
                                <?php endif; ?>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-gray-800 truncate"><?= htmlspecialchars($sw['nama_lengkap']); ?></p>
                                    <p class="text-[10px] text-gray-500 uppercase truncate"><?= htmlspecialchars($sw['username']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if($role != 'siswa'): ?>
                <div x-show="tab == 'nilai'" style="display: none;">
                    <h3 class="font-bold text-lg text-penaburDark mb-4"><i class="fa-solid fa-star text-penaburGold mr-2"></i> Rekap Nilai Kuis & Tugas</h3>
                    
                    <?php if(count($array_kuis) == 0): ?>
                        <div class="p-8 text-center border-2 border-dashed rounded-xl border-gray-300 text-gray-400 font-bold">Belum ada tugas/kuis yang dibuat di kelas ini.</div>
                    <?php else: ?>
                        <div class="overflow-x-auto rounded-2xl border border-gray-200 shadow-sm">
                            <table class="w-full text-left text-sm border-collapse bg-white whitespace-nowrap">
                                <thead class="bg-gray-100 border-b text-[10px] font-black text-gray-500 uppercase tracking-widest">
                                    <tr>
                                        <th class="px-4 py-3 w-10 text-center border-r">No</th>
                                        <th class="px-4 py-3 border-r sticky left-0 bg-gray-100 z-10">Nama Siswa</th>
                                        <?php foreach($array_kuis as $k): ?>
                                            <th class="px-4 py-3 text-center border-r" title="<?= htmlspecialchars($k['judul']); ?>">
                                                <div class="max-w-[120px] truncate mx-auto"><?= htmlspecialchars($k['judul']); ?></div>
                                            </th>
                                        <?php endforeach; ?>
                                        <th class="px-4 py-3 text-center bg-blue-50 text-penaburBlue">Rata-Rata</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <?php 
                                    $no = 1;
                                    foreach($daftar_siswa as $sw):
                                        $id_murid = $sw['id'];
                                        $total_nilai = 0;
                                        $jumlah_kuis = count($array_kuis);
                                    ?>
                                    <tr class="hover:bg-blue-50/50 transition">
                                        <td class="px-4 py-3 font-bold text-gray-400 text-center border-r"><?= $no++; ?></td>
                                        <td class="px-4 py-3 font-bold text-penaburDark border-r sticky left-0 bg-white z-10"><?= htmlspecialchars($sw['nama_lengkap']); ?></td>
                                        <?php foreach($array_kuis as $k): 
                                            $nilai = $rekap_nilai_siswa[$id_murid][$k['id']] ?? null;
                                            if(!is_null($nilai)) $total_nilai += $nilai;
                                        ?>
                                            <td class="px-4 py-3 text-center border-r font-bold <?= is_null($nilai) ? 'text-gray-300' : ($nilai < 75 ? 'text-red-500' : 'text-green-600') ?>">
                                                <?= is_null($nilai) ? '-' : round($nilai, 1); ?>
                                            </td>
                                        <?php endforeach; ?>
                                        <td class="px-4 py-3 text-center font-black bg-blue-50 text-penaburBlue border-l">
                                            <?= $jumlah_kuis > 0 ? round($total_nilai / $jumlah_kuis, 1) : 0; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <?php endif; ?>


            <?php if($mode_halaman == 'kerjakan' && $kuis_aktif): ?>
                
                <div id="toastAutosave" class="fixed bottom-6 right-6 bg-green-500 text-white px-6 py-4 rounded-2xl font-bold shadow-2xl transition-opacity duration-500 opacity-0 pointer-events-none z-50 flex items-center gap-3 border-2 border-green-400">
                    <i class="fa-solid fa-cloud-arrow-up text-xl"></i>
                    <div>
                        <p class="text-sm">Tersimpan ke Database</p>
                        <p class="text-[10px] font-medium opacity-80">Jawaban aman meskipun kamu refresh layar.</p>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-lg border p-8 max-w-4xl mx-auto border-t-8 border-t-penaburBlue relative">
                    
                    <?php if($kuis_aktif['mode_pengawas'] == 'on'): ?>
                    <div class="absolute top-4 right-4 text-red-500 text-xs font-bold animate-pulse flex items-center gap-2">
                        <i class="fa-solid fa-video"></i> Mode Pengawasan Aktif
                    </div>
                    <?php endif; ?>

                    <div class="text-center mb-8 border-b pb-6">
                        <span class="px-4 py-1 bg-penaburGold text-penaburDark rounded-full text-xs font-black uppercase tracking-widest mb-4 inline-block">Sesi Ujian / Kuis Aktif</span>
                        <h1 class="text-3xl font-black text-penaburDark"><?= htmlspecialchars($kuis_aktif['judul']); ?></h1>
                        <p class="text-gray-500 mt-2 font-medium"><?= htmlspecialchars($kuis_aktif['deskripsi']); ?></p>
                        
                        <?php if($kuis_aktif['mode_pengawas'] == 'on'): ?>
                        <div class="mt-6 p-4 bg-red-50 border border-red-200 rounded-xl text-left">
                            <h4 class="text-red-700 font-bold text-sm mb-2"><i class="fa-solid fa-triangle-exclamation mr-2"></i> PERINGATAN KERAS!</h4>
                            <ul class="list-disc pl-5 text-xs text-red-600 font-medium space-y-1">
                                <li>Jangan berpindah Tab atau Window aplikasi selama ujian berlangsung.</li>
                                <li>Jika dilanggar, sistem akan mencatat tindakan Anda, menahan layar dengan peringatan, dan melaporkannya sebagai <b class="font-black">Tindakan Mencontek</b> saat Anda mengumpulkan tugas.</li>
                            </ul>
                        </div>
                        <?php else: ?>
                        <div class="mt-6 p-3 bg-blue-50 border border-blue-200 rounded-xl text-xs font-bold text-penaburBlue">
                            <i class="fa-solid fa-mug-hot mr-2"></i> Kuis ini dalam Mode Bebas (Tidak ada pengawasan larangan pindah tab). Kerjakan dengan santai.
                        </div>
                        <?php endif; ?>
                    </div>

                    <form method="POST" id="formKuisAksi" action="?id=<?= $id_penugasan; ?>">
                        <input type="hidden" name="id_kuis_dikerjakan" value="<?= $kuis_aktif['id']; ?>">
                        <input type="hidden" name="catatan_cheat_input" id="catatan_cheat_input" value="">
                        <input type="hidden" name="is_cheat" id="is_cheat" value="0">
                        
                        <div class="space-y-8">
                            <?php $no = 1; while($soal = $soal_kuis->fetch_assoc()): 
                                $jwb_tersimpan = $jawaban_draft[$soal['id']] ?? '';
                            ?>
                                <div class="p-6 bg-gray-50 border rounded-2xl transition-all focus-within:ring-2 focus-within:ring-penaburBlue">
                                    <div class="flex justify-between items-start mb-4">
                                        <h4 class="font-bold text-gray-800 text-lg"><span class="text-penaburBlue mr-2"><?= $no++; ?>.</span> <?= nl2br(htmlspecialchars($soal['pertanyaan'])); ?></h4>
                                    </div>
                                    
                                    <?php if($soal['tipe'] == 'pg'): ?>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                                            <?php foreach(['a', 'b', 'c', 'd'] as $opt): 
                                                $is_checked = ($jwb_tersimpan == $opt) ? 'checked' : '';
                                            ?>
                                                <?php if(!empty(trim($soal['opsi_'.$opt]))): ?>
                                                <label class="flex items-center p-4 border rounded-xl cursor-pointer hover:bg-blue-50 transition bg-white">
                                                    <input type="radio" name="jawaban[<?= $soal['id']; ?>]" value="<?= $opt; ?>" data-soal="<?= $soal['id']; ?>" class="input-jawaban-pg w-5 h-5 text-penaburBlue" required <?= $is_checked; ?>>
                                                    <span class="ml-3 text-sm font-medium text-gray-700 uppercase mr-2 font-bold"><?= $opt; ?>.</span>
                                                    <span class="text-sm text-gray-600"><?= htmlspecialchars($soal['opsi_'.$opt]); ?></span>
                                                </label>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <textarea name="jawaban[<?= $soal['id']; ?>]" data-soal="<?= $soal['id']; ?>" rows="4" class="input-jawaban-essay w-full p-4 border rounded-xl outline-none focus:ring-2 focus:ring-penaburBlue text-sm bg-white" placeholder="Ketik jawaban essay kamu di sini..." required><?= htmlspecialchars($jwb_tersimpan); ?></textarea>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>

                        <div class="mt-8 text-center">
                            <button type="submit" name="submit_jawaban_kuis" id="btnSubmitAman" class="px-10 py-4 bg-penaburBlue text-white font-black rounded-2xl shadow-xl hover:bg-penaburDark transition text-lg w-full md:w-auto"><i class="fa-solid fa-paper-plane mr-2"></i> Kumpulkan Jawaban (Selesai)</button>
                        </div>
                    </form>
                </div>

                <script>
                    const idKuis = <?= $kuis_aktif['id']; ?>;
                    const modePengawas = '<?= $kuis_aktif['mode_pengawas']; ?>';
                    const formKuis = document.getElementById('formKuisAksi');
                    let isSubmitting = false; 

                    let apiSaveUrl = window.location.pathname + '?id=<?= $id_penugasan ?>&api=autosave_jawaban';

                    function saveJawabanKeDB(idSoal, jawaban) {
                        if(isSubmitting) return;
                        const formData = new FormData();
                        formData.append('id_kuis', idKuis);
                        formData.append('id_soal', idSoal);
                        formData.append('jawaban', jawaban);

                        fetch(apiSaveUrl, {
                            method: 'POST',
                            body: formData
                        }).then(res => res.json()).then(data => {
                            const toast = document.getElementById('toastAutosave');
                            toast.classList.remove('opacity-0');
                            setTimeout(() => { toast.classList.add('opacity-0'); }, 2000);
                        }).catch(err => console.error('Gagal autosave', err));
                    }

                    document.querySelectorAll('.input-jawaban-pg').forEach(el => {
                        el.addEventListener('change', function() {
                            if(this.checked) saveJawabanKeDB(this.dataset.soal, this.value);
                        });
                    });

                    let essayTimeout = null;
                    document.querySelectorAll('.input-jawaban-essay').forEach(el => {
                        el.addEventListener('input', function() {
                            clearTimeout(essayTimeout);
                            essayTimeout = setTimeout(() => {
                                saveJawabanKeDB(this.dataset.soal, this.value);
                            }, 1000);
                        });
                    });

                    let logPelanggaran = [];
                    
                    if (modePengawas === 'on') {
                        function catatPelanggaran(jenis) {
                            if (isSubmitting) return;
                            
                            const jamCatat = new Date().toLocaleTimeString('id-ID');
                            const teksLog = jenis + ' pd ' + jamCatat;
                            
                            if(logPelanggaran.length === 0 || logPelanggaran[logPelanggaran.length - 1] !== teksLog) {
                                logPelanggaran.push(teksLog);
                                document.getElementById('is_cheat').value = '1';
                                document.getElementById('catatan_cheat_input').value = logPelanggaran.join(' | ');
                                
                                alert('⚠️ PERINGATAN KERAS!\n\nSistem mendeteksi kamu: ' + jenis + '.\n\nPelanggaran ini telah DICATAT ke dalam riwayat ujianmu. Terus kerjakan, pelanggaranmu akan dilaporkan ke guru saat kamu mengumpulkan jawaban.');
                            }
                        }

                        document.addEventListener('visibilitychange', () => {
                            if (document.hidden) catatPelanggaran('Pindah Tab / Minimize Layar');
                        });

                        window.addEventListener('blur', () => {
                            catatPelanggaran('Buka Aplikasi Lain (Keluar Fokus)');
                        });
                    }

                    document.getElementById('btnSubmitAman').addEventListener('click', (e) => {
                        if(!confirm('Yakin sudah selesai? Jawaban tidak bisa diubah.')) {
                            e.preventDefault();
                            return;
                        }
                        isSubmitting = true;
                        window.onblur = null; 
                        document.onvisibilitychange = null;
                    });
                </script>

            <?php endif; ?>
            
            <?php if($mode_halaman == 'review' && $kuis_aktif): ?>
                <div class="bg-white rounded-3xl shadow-lg border p-8 max-w-4xl mx-auto border-t-8 border-t-green-500 relative">
                    <div class="text-center mb-8 border-b pb-6">
                        <span class="px-4 py-1 bg-green-100 text-green-700 rounded-full text-xs font-black uppercase tracking-widest mb-4 inline-block"><i class="fa-solid fa-eye mr-2"></i> Review Hasil & Kunci Jawaban</span>
                        <h1 class="text-3xl font-black text-penaburDark"><?= htmlspecialchars($kuis_aktif['judul']); ?></h1>
                        <p class="text-gray-500 mt-2 font-medium">Ini adalah ulasan dari jawaban yang telah kamu kirimkan.</p>
                        
                        <div class="mt-4 flex justify-center gap-4 text-xs font-bold">
                            <span class="flex items-center gap-1 text-green-600"><div class="w-3 h-3 bg-green-100 border border-green-500 rounded"></div> Jawaban Benar</span>
                            <span class="flex items-center gap-1 text-red-500"><div class="w-3 h-3 bg-red-100 border border-red-500 rounded"></div> Jawaban Salah</span>
                            <span class="flex items-center gap-1 text-gray-500"><div class="w-3 h-3 bg-green-50 border border-green-300 border-dashed rounded"></div> Kunci Jawaban (Seharusnya)</span>
                        </div>
                    </div>

                    <div class="space-y-8">
                        <?php $no = 1; while($soal = $soal_kuis->fetch_assoc()): 
                            $jwb_tersimpan = strtolower(trim($jawaban_draft[$soal['id']] ?? ''));
                            $kunci_asli = strtolower(trim($soal['kunci_jawaban']));
                            $benar = ($jwb_tersimpan == $kunci_asli);
                        ?>
                            <div class="p-6 bg-gray-50 border rounded-2xl">
                                <div class="flex justify-between items-start mb-4">
                                    <h4 class="font-bold text-gray-800 text-lg"><span class="text-penaburBlue mr-2"><?= $no++; ?>.</span> <?= nl2br(htmlspecialchars($soal['pertanyaan'])); ?></h4>
                                    <?php if($soal['tipe'] == 'pg'): ?>
                                        <div class="shrink-0 text-2xl">
                                            <?php if(empty($jwb_tersimpan)): ?> <i class="fa-solid fa-minus text-gray-400" title="Kosong"></i>
                                            <?php elseif($benar): ?> <i class="fa-solid fa-check-circle text-green-500"></i>
                                            <?php else: ?> <i class="fa-solid fa-xmark-circle text-red-500"></i> <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if($soal['tipe'] == 'pg'): ?>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                                        <?php foreach(['a', 'b', 'c', 'd'] as $opt): 
                                            $is_selected = ($jwb_tersimpan == $opt);
                                            $is_kunci = ($kunci_asli == $opt);
                                            $bg_class = "bg-white border-gray-200";
                                            $text_class = "text-gray-600";
                                            
                                            if($is_selected && $benar) { $bg_class = "bg-green-100 border-green-500 shadow-sm"; $text_class = "text-green-800 font-bold"; }
                                            elseif($is_selected && !$benar) { $bg_class = "bg-red-100 border-red-500 shadow-sm"; $text_class = "text-red-800 font-bold"; }
                                            elseif(!$is_selected && $is_kunci) { $bg_class = "bg-green-50 border-green-300 border-dashed"; $text_class = "text-green-700 font-bold"; }
                                        ?>
                                            <?php if(!empty(trim($soal['opsi_'.$opt]))): ?>
                                            <div class="flex items-center p-4 border rounded-xl <?= $bg_class ?>">
                                                <span class="ml-3 text-sm font-medium uppercase mr-2 <?= $text_class ?>"><?= $opt; ?>.</span>
                                                <span class="text-sm <?= $text_class ?>"><?= htmlspecialchars($soal['opsi_'.$opt]); ?></span>
                                                <?php if($is_selected && $benar): ?> <i class="fa-solid fa-check text-green-600 ml-auto text-lg"></i> <?php endif; ?>
                                                <?php if($is_selected && !$benar): ?> <i class="fa-solid fa-xmark text-red-600 ml-auto text-lg"></i> <?php endif; ?>
                                                <?php if(!$is_selected && $is_kunci): ?> <i class="fa-solid fa-check text-green-400 ml-auto text-lg opacity-50"></i> <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-4">
                                        <p class="text-xs font-bold text-gray-500 uppercase mb-2">Jawaban Kamu:</p>
                                        <div class="w-full p-4 border rounded-xl bg-white text-sm text-gray-700 whitespace-pre-wrap"><?= empty($jwb_tersimpan) ? '<span class="italic text-gray-400">Tidak ada jawaban.</span>' : htmlspecialchars($jawaban_draft[$soal['id']] ?? ''); ?></div>
                                        <div class="mt-3 p-3 bg-blue-50 border border-blue-200 rounded-lg text-xs font-bold text-penaburBlue">
                                            <i class="fa-solid fa-info-circle mr-1"></i> Soal Essay dinilai manual oleh Guru.
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if($mode_halaman == 'scoreboard' && $kuis_aktif): ?>
                
                <div class="max-w-5xl mx-auto" x-data="liveScoreboard(<?= htmlspecialchars($json_sb_awal) ?>, window.location.pathname + '?id=<?= $id_penugasan ?>&api=scoreboard&id_kuis=<?= $kuis_aktif['id']; ?>')">
                    <div class="bg-gradient-to-r from-purple-800 to-indigo-900 rounded-3xl p-8 mb-8 text-center text-white relative shadow-2xl overflow-hidden">
                        <div class="absolute inset-0 opacity-20 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')]"></div>
                        <div class="relative z-10">
                            <i class="fa-solid fa-trophy text-5xl text-yellow-400 mb-4 animate-bounce"></i>
                            <h1 class="text-4xl font-black mb-2 uppercase tracking-widest">Live Scoreboard</h1>
                            <p class="text-purple-200 text-lg font-bold">Kuis: <?= htmlspecialchars($kuis_aktif['judul']); ?></p>
                            <div class="mt-4 inline-block px-4 py-2 bg-black/30 rounded-full text-xs font-bold text-green-300 backdrop-blur-sm"><i class="fa-solid fa-satellite-dish animate-pulse mr-2"></i> Sistem Auto-Refresh Aktif (Live)</div>
                        </div>
                    </div>

                    <div class="bg-white rounded-3xl shadow-sm border overflow-hidden">
                        <table class="w-full text-left">
                            <thead class="bg-gray-50 border-b text-xs font-black text-gray-500 uppercase tracking-widest">
                                <tr>
                                    <th class="px-6 py-4 text-center w-20">Rank</th>
                                    <th class="px-6 py-4">Nama Partisipan</th>
                                    <th class="px-6 py-4 text-center">Waktu Submit (Terbaik)</th>
                                    <th class="px-6 py-4 text-right">Skor Tertinggi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 transition-all duration-500">
                                <template x-for="(s, index) in peserta" :key="index">
                                    <tr class="hover:bg-gray-50 transition duration-300">
                                        <td class="px-6 py-4 text-center font-black text-xl text-gray-400">
                                            <span x-show="index == 0" class="text-yellow-400 text-2xl"><i class="fa-solid fa-crown"></i></span>
                                            <span x-show="index == 1" class="text-gray-400"><i class="fa-solid fa-medal"></i></span>
                                            <span x-show="index == 2" class="text-amber-600"><i class="fa-solid fa-medal"></i></span>
                                            <span x-show="index > 2" x-text="index + 1"></span>
                                        </td>
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-penaburDark text-lg" x-text="s.nama_lengkap"></div>
                                            <template x-if="s.catatan">
                                                <div class="bg-red-50 text-red-700 px-3 py-2 rounded-lg text-[10px] font-bold mt-2 border border-red-200">
                                                    <p class="font-black mb-1 uppercase"><i class="fa-solid fa-triangle-exclamation"></i> Riwayat Pelanggaran Tab:</p>
                                                    <span x-text="s.catatan" class="leading-relaxed"></span>
                                                </div>
                                            </template>
                                        </td>
                                        <td class="px-6 py-4 text-center text-xs text-gray-500" x-text="s.waktu_selesai"></td>
                                        <td class="px-6 py-4 text-right font-black text-2xl text-penaburBlue" x-text="Math.round(s.nilai)"></td>
                                    </tr>
                                </template>
                                <tr x-cloak x-show="peserta.length === 0">
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-400 font-bold italic">Belum ada siswa yang menyelesaikan kuis ini. Menunggu data live...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <script>
                    document.addEventListener('alpine:init', () => {
                        Alpine.data('liveScoreboard', (initData, apiUrl) => ({
                            peserta: initData,
                            init() {
                                setInterval(() => { this.fetchData(); }, 3000); 
                            },
                            fetchData() {
                                fetch(apiUrl)
                                    .then(res => res.json())
                                    .then(data => { 
                                        if(Array.isArray(data)){ this.peserta = data; }
                                    })
                                    .catch(err => console.log('API terblokir atau server sibuk:', err));
                            }
                        }))
                    })
                </script>
            <?php endif; ?>

        </main>
    </div>

    <?php if($role != 'siswa' && $mode_halaman == 'utama'): ?>

    <div x-show="modalBuatSesi" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" style="display: none;" x-transition>
        <div @click.away="modalBuatSesi = false" class="bg-white rounded-3xl w-full max-w-sm p-8 shadow-2xl relative text-center">
            <button @click="modalBuatSesi = false" class="absolute top-4 right-4 w-8 h-8 bg-gray-100 rounded-full text-gray-500 hover:bg-red-500 hover:text-white transition"><i class="fa-solid fa-xmark"></i></button>
            <h3 class="text-2xl font-black text-penaburDark mb-2"><i class="fa-solid fa-calendar-plus text-penaburGold mr-2"></i> Sesi Absen</h3>
            <p class="text-xs text-gray-500 mb-6 font-bold">Pilih tanggal untuk membuat sesi absensi baru.</p>
            
            <form method="POST" action="?id=<?= $id_penugasan; ?>">
                <input type="date" name="tanggal_sesi" value="<?= date('Y-m-d'); ?>" class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-penaburBlue outline-none text-center font-bold mb-4" required>
                <button type="submit" name="buat_sesi_absen" class="w-full py-3 bg-penaburBlue text-white font-extrabold rounded-xl shadow-lg hover:bg-penaburDark transition">Buat Sesi Sekarang</button>
            </form>
        </div>
    </div>

    <div x-show="modalAbsenQR" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" style="display: none;" x-transition>
        <div @click.away="modalAbsenQR = false; $dispatch('close-qr')" class="bg-white rounded-3xl w-full max-w-md p-8 shadow-2xl relative text-center">
            <button @click="modalAbsenQR = false; $dispatch('close-qr')" class="absolute top-4 right-4 w-8 h-8 bg-gray-100 rounded-full text-gray-500 hover:bg-red-500 hover:text-white transition"><i class="fa-solid fa-xmark"></i></button>
            <h3 class="text-2xl font-black text-penaburDark mb-2"><i class="fa-solid fa-qrcode text-penaburBlue mr-2"></i> Scan Absensi</h3>
            <p class="text-xs text-gray-500 mb-6 font-bold">Barcode ini berubah otomatis setiap 5 detik untuk mencegah kecurangan absen jarak jauh.</p>
            
            <div class="flex justify-center items-center bg-gray-50 p-6 rounded-2xl border-2 border-dashed border-gray-300 min-h-[300px]">
                <div x-data="qrHandler()" @start-qr.window="startLoop($event.detail.id)" @close-qr.window="stopLoop()">
                    <img x-show="qrImg" :src="qrImg" class="w-64 h-64 object-contain shadow-sm border p-2 bg-white" alt="QR Code" style="display:none;">
                    <div x-show="!qrImg" class="text-gray-400 font-bold animate-pulse"><i class="fa-solid fa-spinner fa-spin text-3xl mb-2"></i><br>Generating...</div>
                    <div class="mt-6 p-3 bg-blue-50 text-penaburBlue rounded-xl text-xs font-bold border border-blue-200 shadow-sm w-full">
                        <i class="fa-solid fa-satellite-dish animate-pulse mr-2"></i> Token: <span x-text="token" class="font-black tracking-widest text-penaburDark"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function qrHandler() {
            return {
                token: '', qrImg: '', interval: null, sessionId: 0,
                startLoop(id) {
                    this.sessionId = id;
                    this.updateQR();
                    this.interval = setInterval(() => this.updateQR(), 5000); 
                },
                stopLoop() {
                    clearInterval(this.interval);
                    this.token = ''; this.qrImg = '';
                },
                updateQR() {
                    fetch(window.location.pathname + '?id=<?= $id_penugasan ?>&api=generate_qr&id_sesi=' + this.sessionId)
                    .then(r => r.json())
                    .then(d => {
                        if(d.token) {
                            this.token = d.token;
                            let fullUrl = window.location.href.split('?')[0] + '?id=<?= $id_penugasan ?>&absen_token=' + this.token;
                            this.qrImg = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(fullUrl);
                        }
                    }).catch(e => console.log('QR API error: ', e));
                }
            }
        }
    </script>
    
    <div x-show="modalMateri" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm" style="display: none;" x-transition>
        <div @click.away="modalMateri = false" class="bg-white rounded-3xl w-full max-w-lg p-8 shadow-2xl relative">
            <button @click="modalMateri = false" class="absolute top-4 right-4 w-8 h-8 bg-gray-100 rounded-full text-gray-500 hover:bg-red-500 hover:text-white transition"><i class="fa-solid fa-xmark"></i></button>
            <h3 class="text-2xl font-black text-penaburDark mb-6"><i class="fa-solid fa-cloud-arrow-up text-penaburBlue mr-2"></i> Upload Materi</h3>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4" action="?id=<?= $id_penugasan; ?>" x-data="{ jenis: 'file' }">
                <div><label class="block text-xs font-bold text-gray-500 uppercase mb-2">Judul Materi</label><input type="text" name="judul_materi" class="w-full px-4 py-3 rounded-xl border focus:ring-2 focus:ring-penaburBlue outline-none" required></div>
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Jenis Upload</label>
                    <select name="jenis_materi" x-model="jenis" class="w-full px-4 py-3 rounded-xl border font-bold text-penaburDark outline-none bg-gray-50">
                        <option value="file">Dokumen (PDF, PPTX, DOCX, dll)</option>
                        <option value="link">Tautan Eksternal (Link YouTube, Drive)</option>
                    </select>
                </div>

                <div x-show="jenis == 'file'">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Pilih File</label>
                    <input type="file" name="file_materi" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip,.rar" class="w-full text-sm border p-2 rounded-xl bg-gray-50">
                </div>

                <div x-show="jenis == 'link'" style="display: none;">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">URL Tautan</label>
                    <input type="url" name="link_url" class="w-full px-4 py-3 rounded-xl border outline-none bg-gray-50" placeholder="https://...">
                </div>

                <button type="submit" name="simpan_materi" class="w-full py-3 bg-penaburBlue text-white font-extrabold rounded-xl shadow-lg hover:bg-penaburDark transition mt-4">Simpan Materi</button>
            </form>
        </div>
    </div>

    <div x-show="modalKuis" class="fixed inset-0 z-50 flex items-start justify-center p-4 pt-10 pb-24 bg-black/60 backdrop-blur-sm overflow-y-auto" style="display: none;" x-transition>
        <div class="bg-white rounded-3xl w-full max-w-4xl p-6 sm:p-8 shadow-2xl relative" x-data="kuisEngine(<?= htmlspecialchars($init_kuis_json); ?>)">
            <a href="?id=<?= $id_penugasan; ?>&tab=tugas" class="absolute top-4 right-4 w-8 h-8 flex justify-center items-center bg-gray-100 rounded-full text-gray-500 hover:bg-red-500 hover:text-white transition cursor-pointer"><i class="fa-solid fa-xmark"></i></a>
            
            <h3 class="text-2xl font-black text-penaburDark mb-6">
                <i class="fa-solid fa-wand-magic-sparkles text-penaburGold mr-2"></i> 
                <span x-text="id_edit ? 'Edit Kuis & Soal' : 'Engine Pembuat Kuis'"></span>
            </h3>
            
            <form method="POST" id="formBuatKuis" action="?id=<?= $id_penugasan; ?>">
                <input type="hidden" name="data_soal_json" :value="JSON.stringify(soals)">
                <input type="hidden" name="id_edit_kuis" :value="id_edit">
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 p-4 border rounded-2xl bg-gray-50">
                    <div class="md:col-span-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Judul Kuis</label>
                        <input type="text" name="judul_kuis" x-model="judul" class="w-full px-4 py-2 rounded-xl border outline-none font-bold text-penaburDark" required>
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Deskripsi / Petunjuk</label>
                        <textarea name="deskripsi_kuis" x-model="deskripsi" class="w-full px-4 py-2 rounded-xl border outline-none text-sm" rows="2" required></textarea>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Mulai Ujian</label>
                        <input type="datetime-local" name="waktu_mulai" x-model="mulai" class="w-full px-3 py-2 rounded-xl border outline-none text-sm font-bold text-penaburBlue">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Tutup Ujian</label>
                        <input type="datetime-local" name="waktu_selesai" x-model="selesai" class="w-full px-3 py-2 rounded-xl border outline-none text-sm font-bold text-red-500">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Batas Percobaan</label>
                        <input type="number" name="batas_percobaan" x-model="batas" min="1" class="w-full px-3 py-2 rounded-xl border outline-none text-sm font-bold text-penaburDark bg-white shadow-inner">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Mode Anti-Cheat</label>
                        <select name="mode_pengawas" x-model="pengawas" class="w-full px-3 py-2 rounded-xl border outline-none text-xs font-bold text-penaburDark bg-white shadow-inner">
                            <option value="off">Off (Bebas)</option>
                            <option value="on">On (Anti Tab)</option>
                        </select>
                    </div>
                    <div class="md:col-span-4 pt-2 border-t border-gray-200 mt-2">
                        <label class="flex items-center gap-2 cursor-pointer w-fit tooltip" title="Siswa dapat melihat mana jawaban yang benar dan salah setelah mengerjakan seperti Google Forms.">
                            <input type="checkbox" class="w-5 h-5 text-penaburBlue focus:ring-0 rounded" x-on:change="tampil_hasil = $event.target.checked ? 'ya' : 'tidak'" :checked="tampil_hasil == 'ya'">
                            <span class="text-xs font-bold text-gray-700">Tampilkan Hasil & Kunci Jawaban Setelah Selesai <span class="text-[9px] text-gray-400 font-normal ml-1">(Mode Review)</span></span>
                        </label>
                        <input type="hidden" name="tampil_hasil" x-model="tampil_hasil">
                    </div>
                </div>

                <div class="bg-blue-50 text-penaburBlue px-4 py-3 rounded-xl text-xs font-bold mb-4 shadow-sm">
                    <i class="fa-solid fa-robot mr-2"></i> Sistem Penilaian Auto-Kalkulasi diaktifkan. Setiap soal akan dihitung rata secara otomatis hingga total mencapai nilai maksimal 100.
                </div>

                <div class="space-y-6 max-h-[50vh] overflow-y-auto p-2 border-t border-b py-4">
                    <template x-for="(soal, index) in soals" :key="index">
                        <div class="p-4 border border-gray-200 rounded-2xl relative shadow-sm hover:border-penaburBlue transition bg-white">
                            <button type="button" @click="hapusSoal(index)" class="absolute top-4 right-4 text-red-500 hover:text-red-700 font-bold text-xs"><i class="fa-solid fa-trash"></i> Hapus</button>
                            
                            <div class="w-1/3 mb-4">
                                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Tipe Soal</label>
                                <select x-model="soal.tipe" class="w-full p-2 border rounded-lg text-xs font-bold outline-none bg-blue-50 text-penaburBlue">
                                    <option value="pg">Pilihan Ganda</option>
                                    <option value="essay">Essay / Uraian</option>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-[10px] font-bold text-gray-500 uppercase mb-1">Pertanyaan <span x-text="index+1"></span></label>
                                <textarea x-model="soal.pertanyaan" class="w-full p-3 border rounded-xl outline-none text-sm focus:ring-1 focus:ring-penaburBlue" rows="2" placeholder="Ketik pertanyaan..."></textarea>
                            </div>

                            <div x-show="soal.tipe == 'pg'" class="grid grid-cols-1 md:grid-cols-2 gap-3 bg-gray-50 p-4 rounded-xl border">
                                <div class="flex items-center gap-2"><span class="font-bold text-xs">A.</span><input type="text" x-model="soal.opsi_a" class="w-full p-2 text-xs border rounded outline-none" placeholder="Opsi A"></div>
                                <div class="flex items-center gap-2"><span class="font-bold text-xs">B.</span><input type="text" x-model="soal.opsi_b" class="w-full p-2 text-xs border rounded outline-none" placeholder="Opsi B"></div>
                                <div class="flex items-center gap-2"><span class="font-bold text-xs">C.</span><input type="text" x-model="soal.opsi_c" class="w-full p-2 text-xs border rounded outline-none" placeholder="Opsi C"></div>
                                <div class="flex items-center gap-2"><span class="font-bold text-xs">D.</span><input type="text" x-model="soal.opsi_d" class="w-full p-2 text-xs border rounded outline-none" placeholder="Opsi D"></div>
                                
                                <div class="md:col-span-2 mt-2 pt-2 border-t">
                                    <label class="text-[10px] font-bold text-gray-500 uppercase mr-2">Kunci Jawaban Benar:</label>
                                    <select x-model="soal.kunci" class="p-2 border rounded font-bold text-xs outline-none bg-green-50 text-green-700">
                                        <option value="a">Opsi A</option><option value="b">Opsi B</option><option value="c">Opsi C</option><option value="d">Opsi D</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="mt-6 flex justify-between items-center">
                    <button type="button" @click="tambahSoal()" class="px-4 py-2 bg-gray-100 text-penaburDark font-bold rounded-xl hover:bg-gray-200 transition text-sm"><i class="fa-solid fa-plus mr-1"></i> Tambah Pertanyaan</button>
                    <button type="submit" name="simpan_kuis_baru" class="px-8 py-3 bg-penaburGold text-penaburDark font-black rounded-xl shadow-lg hover:bg-yellow-500 transition">
                        <i class="fa-solid fa-save mr-2"></i> <span x-text="id_edit ? 'Simpan Perubahan' : 'Terbitkan Kuis'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function kuisEngine(initData) {
            return {
                id_edit: initData ? initData.id : '',
                judul: initData ? initData.judul : '',
                deskripsi: initData ? initData.deskripsi : '',
                mulai: initData ? initData.waktu_mulai : '',
                selesai: initData ? initData.waktu_selesai : '',
                batas: (initData && initData.batas_percobaan) ? initData.batas_percobaan : 1,
                pengawas: (initData && initData.mode_pengawas) ? initData.mode_pengawas : 'off',
                tampil_hasil: (initData && initData.tampil_hasil) ? initData.tampil_hasil : 'tidak',
                soals: (initData && initData.soals && initData.soals.length > 0) ? initData.soals : [ { tipe: 'pg', pertanyaan: '', opsi_a: '', opsi_b: '', opsi_c: '', opsi_d: '', kunci: 'a' } ],
                tambahSoal() { this.soals.push({ tipe: 'pg', pertanyaan: '', opsi_a: '', opsi_b: '', opsi_c: '', opsi_d: '', kunci: 'a' }); },
                hapusSoal(index) { if(this.soals.length > 1) this.soals.splice(index, 1); else alert('Minimal harus ada 1 pertanyaan!'); }
            }
        }
    </script>

    <?php endif; ?>
</body>
</html>
