<?php
date_default_timezone_set('Asia/Jakarta');
$host = "mariadb";
$user = "root";       
$pass = "root";           
$db   = "siakad";

// Membuat koneksi menggunakan MySQLi Object-Oriented (Bawaan Siakad)
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi Database Gagal: " . $conn->connect_error);
}
$conn->set_charset("utf8");

// =========================================================================
// WEB APPLICATION FIREWALL (WAF) - IP & DEVICE BLOCKER + WHITELIST
// =========================================================================
if ($conn) {
    // 1. Auto-create tabel untuk Log Serangan dan Daftar IP Diblokir
    @$conn->query("CREATE TABLE IF NOT EXISTS `siakad_security_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ip_address` varchar(50) NOT NULL,
      `threat_type` varchar(50) NOT NULL,
      `payload` text NOT NULL,
      `waktu` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    )");

    @$conn->query("CREATE TABLE IF NOT EXISTS `siakad_blocked_ips` (
      `ip_address` varchar(50) NOT NULL,
      `waktu_blokir` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`ip_address`)
    )");

    // 2. Ambil IP Pengunjung
    $ip_visitor = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $safe_ip = $conn->real_escape_string($ip_visitor);

    // =====================================================================
    // 👑 WHITELIST DEVELOPER (ANTI SENJATA MAKAN TUAN)
    // =====================================================================
    $whitelist_ips = [
        'mariadb', // Localhost IPv4
        '::1',       // Localhost IPv6
        '2001:448a:114a:112c:186a:930d:8220:91f2' // IP Lu Bro!
    ];

    // JIKA IP ADA DI WHITELIST, LEWATI SEMUA PENGECEKAN KEAMANAN
    if (!in_array($ip_visitor, $whitelist_ips)) {

        // 3. CEK APAKAH PERANGKAT (COOKIE) ATAU IP SUDAH DIBLOKIR?
        $is_banned = false;

        // Cek Cookie (Device Ban)
        if (isset($_COOKIE['siakad_device_banned']) && $_COOKIE['siakad_device_banned'] === 'true') {
            $is_banned = true;
        } else {
            // Cek IP Database
            $cek_blokir = @$conn->query("SELECT ip_address FROM siakad_blocked_ips WHERE ip_address = '$safe_ip'");
            if ($cek_blokir && $cek_blokir->num_rows > 0) {
                $is_banned = true;
                setcookie('siakad_device_banned', 'true', time() + (10 * 365 * 24 * 60 * 60), '/'); 
            }
        }

        // Eksekusi Blokir jika terdeteksi (Lempar ke ban.php)
        if ($is_banned) {
            header("Location: /ban");
            exit;
        }

        // 4. Tangkap payload untuk pengecekan ancaman (Jika belum diblokir)
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $payload_str = urldecode($request_uri . ' | POST: ' . json_encode($_POST));
        
        $is_threat = false;
        $threat_type = "";
        
        // Deteksi Pola SQL Injection
        $sqli_patterns = ['/UNION\s+SELECT/i', '/--/'];
        foreach ($sqli_patterns as $pattern) {
            if (preg_match($pattern, $payload_str)) {
                $is_threat = true;
                $threat_type = "SQL Injection Attempt";
                break;
            }
        }
        
        // Deteksi Pola XSS (Cross Site Scripting)
        if (!$is_threat) {
            $xss_patterns = ['/<script>/i', '/javascript:/i', '/onerror=/i', '/onload=/i'];
            foreach ($xss_patterns as $pattern) {
                if (preg_match($pattern, $payload_str)) {
                    $is_threat = true;
                    $threat_type = "XSS Attack Attempt";
                    break;
                }
            }
        }
        
        // 5. JIKA TERDETEKSI ANCAMAN: CATAT LOG, BLOKIR IP, & TANEM COOKIE BAN!
        if ($is_threat) {
            $safe_payload = $conn->real_escape_string(substr($payload_str, 0, 500));
            $safe_threat = $conn->real_escape_string($threat_type);
            
            // Masukkan data ke log
            @$conn->query("INSERT INTO siakad_security_logs (ip_address, threat_type, payload) VALUES ('$safe_ip', '$safe_threat', '$safe_payload')");
            
            // Masukkan IP ke database daftar hitam
            @$conn->query("INSERT IGNORE INTO siakad_blocked_ips (ip_address) VALUES ('$safe_ip')");
            
            // Tanam Cookie Haram
            setcookie('siakad_device_banned', 'true', time() + (10 * 365 * 24 * 60 * 60), '/'); 
            
            // Langsung lempar ke halaman Banned
            header("Location: /ban");
            exit;
        }
    }
}
?>