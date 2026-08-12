<?php
date_default_timezone_set('Asia/Jakarta');
// =========================================================================
// SPEEDTEST API ENDPOINTS (Bypass semua script biar akurat)
// =========================================================================
if (isset($_GET['action'])) {
    header("Access-Control-Allow-Origin: *");
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");
    
    $action = $_GET['action'];
    
    if ($action == 'ping') {
        echo "pong";
        exit;
    }
    if ($action == 'download') {
        header("Content-Type: application/octet-stream");
        // Kirim 5MB data dummy untuk di-download client
        echo str_repeat("0", 5 * 1024 * 1024);
        exit;
    }
    if ($action == 'upload') {
        echo "ok";
        exit;
    }
}

// =========================================================================
// FITUR DIAGNOSTIK SERVER & WEBSITE SIAKAD
// Tidak membutuhkan Login, aman dibuka kapan saja untuk cek health server.
// =========================================================================

$start_time = microtime(true);

// 1. Cek Koneksi Database
$db_status = false;
$db_error = "";
$db_host = "N/A";
$db_version = "N/A";

if (file_exists('koneksi.php')) {
    ob_start();
    include 'koneksi.php';
    ob_end_clean();
    
    if (isset($conn) && $conn instanceof mysqli) {
        $db_status = true;
        $db_host = $conn->host_info;
        $db_version = $conn->server_info;
    } else {
        $db_error = "Koneksi ke database gagal atau variable \$conn tidak ditemukan.";
    }
} else {
    $db_error = "File koneksi.php tidak ditemukan di direktori.";
}

// =========================================================================
// WEB APPLICATION FIREWALL (WAF) - SQLi & XSS MONITORING
// =========================================================================
if ($db_status) {
    // Auto-create table log security
    $conn->query("CREATE TABLE IF NOT EXISTS `siakad_security_logs` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `ip_address` varchar(50) NOT NULL,
      `threat_type` varchar(50) NOT NULL,
      `payload` text NOT NULL,
      `waktu` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    )");

    // Deteksi IP & Payload
    $ip_attacker = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $request_uri = $_SERVER['REQUEST_URI'];
    $payload_str = urldecode($request_uri . ' | POST: ' . json_encode($_POST));
    
    $is_threat = false;
    $threat_type = "";
    
    // Pola ancaman (Termasuk Trigger Rahasia buat ngetes bypass Cloudflare)
    $threat_patterns = [
        '/UNION\s+SELECT/i' => 'SQL Injection (UNION)',
        '/DROP\s+TABLE/i' => 'SQL Injection (DROP)',
        '/OR\s+1=1/i' => 'SQL Injection (Bypass)',
        '/<script>/i' => 'XSS Attack',
        '/siakad_test_waf/i' => 'System Test (Manual Trigger)' // <-- Trigger rahasia buat lu ngetes!
    ];
    
    foreach ($threat_patterns as $pattern => $type) {
        if (preg_match($pattern, $payload_str)) {
            $is_threat = true;
            $threat_type = $type;
            break;
        }
    }
    
    // Jika terdeteksi ancaman, masukkan ke log database
    if ($is_threat) {
        $safe_payload = $conn->real_escape_string(substr($payload_str, 0, 500));
        $safe_threat = $conn->real_escape_string($threat_type);
        $safe_ip = $conn->real_escape_string($ip_attacker);
        $conn->query("INSERT INTO siakad_security_logs (ip_address, threat_type, payload) VALUES ('$safe_ip', '$safe_threat', '$safe_payload')");
    }
}

// 2. Info Server Host & Container
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$php_version = phpversion();
$os_name = php_uname('s') . ' ' . php_uname('r');
$server_ip = $_SERVER['SERVER_ADDR'] ?? 'Unknown';
$server_name = $_SERVER['SERVER_NAME'] ?? 'Unknown';
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown';

// 3. Disk & Memory Info
function formatBytes($bytes) {
    if ($bytes == 0) return "0 B";
    $k = 1024;
    $sizes = array('B', 'KB', 'MB', 'GB', 'TB');
    $i = floor(log($bytes, $k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

$disk_free = @disk_free_space(".");
$disk_total = @disk_total_space(".");
$disk_used = ($disk_total !== false && $disk_free !== false) ? ($disk_total - $disk_free) : false;

$mem_limit = ini_get('memory_limit');
$max_execution = ini_get('max_execution_time');
$upload_max = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');

// 4. Cek Dependensi (Extensions)
$required_extensions = ['mysqli', 'pdo_mysql', 'curl', 'gd', 'mbstring', 'json', 'xml', 'zip'];
$ext_status = [];
foreach ($required_extensions as $ext) {
    $ext_status[$ext] = extension_loaded($ext);
}

// 5. Cek Koneksi & Routing Cloudflare
$cf_active = isset($_SERVER['HTTP_CF_RAY']);
$cf_connecting_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? 'N/A';
$cf_country = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'N/A';
$cf_ray = $_SERVER['HTTP_CF_RAY'] ?? 'N/A';

$load_time = round((microtime(true) - $start_time) * 1000, 2);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Server Status - SIAKAD System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; background-color: #0f172a; color: #e2e8f0; } </style>
</head>
<body class="p-4 sm:p-8">
    <div class="max-w-5xl mx-auto">
        
        <div class="flex flex-col sm:flex-row justify-between items-center mb-8 border-b border-slate-700 pb-4">
            <div>
                <h1 class="text-3xl font-black text-white tracking-tight flex items-center gap-3">
                    <span class="text-blue-500">⚡</span> SIAKAD System Health
                </h1>
                <p class="text-slate-400 mt-1 text-sm">Real-time Diagnostic & Security Report</p>
            </div>
            <div class="text-right mt-4 sm:mt-0">
                <div class="bg-slate-800 px-4 py-2 rounded-xl border border-slate-700 text-xs font-mono inline-block">
                    Ping: <span class="<?= $load_time < 50 ? 'text-emerald-400' : 'text-amber-400' ?> font-bold"><?= $load_time ?> ms</span>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <div class="bg-slate-800 p-5 rounded-2xl border border-slate-700 shadow-lg">
                <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">Web Server</div>
                <div class="text-lg font-black text-white truncate" title="<?= $server_software ?>"><?= explode(' ', $server_software)[0] ?></div>
                <div class="text-xs text-blue-400 mt-2 font-mono"><?= $server_ip ?></div>
            </div>
            <div class="bg-slate-800 p-5 rounded-2xl border border-slate-700 shadow-lg">
                <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">PHP Engine</div>
                <div class="text-lg font-black text-white">v<?= $php_version ?></div>
                <div class="text-xs text-emerald-400 mt-2 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block animate-pulse"></span> Running
                </div>
            </div>
            <div class="bg-slate-800 p-5 rounded-2xl border border-slate-700 shadow-lg">
                <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">MySQL Database</div>
                <div class="text-lg font-black <?= $db_status ? 'text-white' : 'text-rose-500' ?>"><?= $db_status ? 'Connected' : 'Offline' ?></div>
                <div class="text-xs <?= $db_status ? 'text-emerald-400' : 'text-rose-400' ?> mt-2 truncate">
                    <?= $db_status ? explode('-', $db_version)[0] : 'Connection Failed' ?>
                </div>
            </div>
            <div class="bg-slate-800 p-5 rounded-2xl border border-slate-700 shadow-lg">
                <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">Host OS</div>
                <div class="text-lg font-black text-white truncate"><?= explode(' ', $os_name)[0] ?></div>
                <div class="text-xs text-slate-400 mt-2 truncate"><?= php_uname('m') ?> Architecture</div>
            </div>
            <div class="bg-slate-800 p-5 rounded-2xl border border-slate-700 shadow-lg">
                <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-1">CF Routing</div>
                <div class="text-lg font-black <?= $cf_active ? 'text-orange-400' : 'text-slate-500' ?>"><?= $cf_active ? 'Protected' : 'Bypassed' ?></div>
                <div class="text-xs <?= $cf_active ? 'text-orange-300' : 'text-rose-400' ?> mt-2 truncate">
                    <?= $cf_active ? 'Proxied Network' : 'Direct Origin IP' ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            
            <div class="lg:col-span-2 space-y-6">
                
                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden shadow-lg">
                    <div class="bg-slate-900 px-5 py-4 border-b border-slate-700 flex justify-between items-center">
                        <h2 class="font-bold text-sm text-slate-200">🚀 Network Speedtest (Client to Server)</h2>
                        <span class="flex items-center gap-2 text-[10px] font-bold text-blue-400 bg-blue-900/30 px-3 py-1.5 rounded-lg border border-blue-800/50">
                            <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span> AUTO TESTING...
                        </span>
                    </div>
                    <div class="p-5 text-sm">
                        <div class="grid grid-cols-3 gap-3 md:gap-4 text-center mb-6">
                            <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700">
                                <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-2">Ping (ms)</div>
                                <div id="ping-speed" class="text-xl md:text-2xl font-black text-emerald-400">Wait..</div>
                            </div>
                            <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700">
                                <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-2">Download</div>
                                <div id="dl-speed" class="text-xl md:text-2xl font-black text-blue-400">Wait..</div>
                            </div>
                            <div class="bg-slate-900/50 p-4 rounded-xl border border-slate-700">
                                <div class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-2">Upload</div>
                                <div id="ul-speed" class="text-xl md:text-2xl font-black text-purple-400">Wait..</div>
                            </div>
                        </div>

                        <div class="bg-slate-900/40 p-4 rounded-xl border border-slate-700">
                            <h3 class="text-slate-500 text-[10px] font-bold uppercase tracking-widest mb-2 text-center">Live Graphic Monitor</h3>
                            <div class="relative w-full h-32 md:h-40">
                                <canvas id="speedChart"></canvas>
                            </div>
                        </div>

                        <div class="mt-4 text-[10px] text-slate-500 text-center font-mono">
                            *Sistem otomatis mengukur kecepatan koneksi dari perangkat Anda ke Server SIAKAD setiap 15 detik.
                        </div>
                    </div>
                </div>

                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden shadow-lg">
                    <div class="bg-slate-900 px-5 py-4 border-b border-slate-700 flex justify-between items-center">
                        <h2 class="font-bold text-sm text-slate-200">🗄️ Database Connection Info</h2>
                        <?= $db_status ? '<span class="bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 px-2 py-1 rounded text-[10px] font-bold">ONLINE</span>' : '<span class="bg-rose-500/20 text-rose-400 border border-rose-500/30 px-2 py-1 rounded text-[10px] font-bold">OFFLINE</span>' ?>
                    </div>
                    <div class="p-5 text-sm">
                        <?php if($db_status): ?>
                            <div class="grid grid-cols-3 gap-4 mb-3 border-b border-slate-700 pb-3">
                                <div class="text-slate-400">Host Socket</div>
                                <div class="col-span-2 font-mono text-blue-400"><?= $db_host ?></div>
                            </div>
                            <div class="grid grid-cols-3 gap-4 mb-3 border-b border-slate-700 pb-3">
                                <div class="text-slate-400">Server Version</div>
                                <div class="col-span-2 font-mono text-slate-200"><?= $db_version ?></div>
                            </div>
                            <div class="grid grid-cols-3 gap-4">
                                <div class="text-slate-400">Client Library</div>
                                <div class="col-span-2 font-mono text-slate-200"><?= $conn->client_info ?? 'N/A' ?></div>
                            </div>
                        <?php else: ?>
                            <div class="text-rose-400 bg-rose-900/30 p-4 rounded-xl border border-rose-800/50 font-mono text-xs">
                                ERROR: <?= $db_error ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden shadow-lg">
                    <div class="bg-slate-900 px-5 py-4 border-b border-slate-700">
                        <h2 class="font-bold text-sm text-slate-200">🛡️ Network Diagnostics</h2>
                    </div>
                    <div class="p-5 text-sm">
                        <div class="text-slate-400 mb-2 font-bold text-xs uppercase">Cloudflare Status</div>
                        <?php if($cf_active): ?>
                            <div class="flex items-center gap-2 text-orange-400 font-bold mb-1 text-base">
                                <span class="w-2.5 h-2.5 rounded-full bg-orange-500 animate-pulse"></span> Proxied & Protected
                            </div>
                            <div class="text-xs text-slate-300 font-mono mt-3">Ray ID: <span class="text-white font-bold"><?= $cf_ray ?></span></div>
                            <div class="text-xs text-slate-300 font-mono mt-1">Visitor: <span class="text-white font-bold"><?= $cf_connecting_ip ?></span> (<?= $cf_country ?>)</div>
                        <?php else: ?>
                            <div class="text-slate-500 italic mb-2">Not routed through Cloudflare.</div>
                            <div class="text-[10px] text-rose-400 font-mono bg-rose-900/20 p-2 rounded border border-rose-800/30">Warning: Origin IP might be exposed.</div>
                        <?php endif; ?>
                        
                        <div class="text-slate-400 mt-6 mb-2 font-bold text-xs uppercase">Origin Server Time</div>
                        <div class="text-xs text-slate-200 font-mono"><?= date('Y-m-d H:i:s T') ?></div>
                    </div>
                </div>

                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden shadow-lg">
                    <div class="bg-slate-900 px-5 py-4 border-b border-slate-700">
                        <h2 class="font-bold text-sm text-slate-200">💾 Storage & Config</h2>
                    </div>
                    <div class="p-5 text-sm">
                        <div class="text-slate-400 mb-2 font-bold text-xs uppercase">Disk Usage</div>
                        <?php if($disk_total !== false && $disk_total > 0): ?>
                            <?php $pct = round(($disk_used / $disk_total) * 100); ?>
                            <div class="w-full bg-slate-700 rounded-full h-3 mb-2 overflow-hidden border border-slate-600">
                                <div class="h-3 rounded-full <?= $pct > 85 ? 'bg-rose-500' : ($pct > 60 ? 'bg-amber-500' : 'bg-blue-500') ?>" style="width: <?= $pct ?>%"></div>
                            </div>
                            <div class="flex justify-between text-[11px] font-mono">
                                <span class="text-slate-300">Used: <?= formatBytes($disk_used) ?></span>
                                <span class="text-slate-400">Free: <?= formatBytes($disk_free) ?></span>
                            </div>
                        <?php else: ?>
                            <div class="text-slate-500 italic font-mono text-[10px] p-2 bg-slate-700/50 rounded">Permission Denied / Docker restricted.</div>
                        <?php endif; ?>

                        <div class="text-slate-400 mt-6 mb-3 font-bold text-xs uppercase">PHP Limits</div>
                        <div class="space-y-2 text-[11px] font-mono">
                            <div class="flex justify-between border-b border-slate-700 pb-1">
                                <span class="text-slate-400">memory_limit</span>
                                <span class="text-emerald-400"><?= $mem_limit ?></span>
                            </div>
                            <div class="flex justify-between border-b border-slate-700 pb-1">
                                <span class="text-slate-400">upload_max</span>
                                <span class="text-emerald-400"><?= $upload_max ?></span>
                            </div>
                            <div class="flex justify-between border-b border-slate-700 pb-1">
                                <span class="text-slate-400">post_max_size</span>
                                <span class="text-emerald-400"><?= $post_max ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">max_execution</span>
                                <span class="text-emerald-400"><?= $max_execution ?>s</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden shadow-lg h-fit">
                    <div class="bg-slate-900 px-5 py-4 border-b border-slate-700">
                        <h2 class="font-bold text-sm text-slate-200">🧩 Extensions</h2>
                    </div>
                    <div class="p-0">
                        <ul class="divide-y divide-slate-700">
                            <?php foreach($ext_status as $ext => $loaded): ?>
                                <li class="px-5 py-2 flex justify-between items-center hover:bg-slate-750 transition-colors">
                                    <span class="font-mono text-[11px] text-slate-300"><?= $ext ?></span>
                                    <?php if($loaded): ?>
                                        <span class="text-emerald-400 text-[10px] font-bold">OK</span>
                                    <?php else: ?>
                                        <span class="text-rose-400 text-[10px] font-bold">MISSING</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden shadow-lg mb-8">
            <div class="bg-slate-900 px-5 py-4 border-b border-slate-700 flex justify-between items-center">
                <h2 class="font-bold text-sm text-slate-200">🚨 Web Application Firewall (SQLi & Anomaly Monitor)</h2>
                <span class="flex items-center gap-2 text-[10px] font-bold text-emerald-400 bg-emerald-900/30 px-3 py-1.5 rounded-lg border border-emerald-800/50">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> ACTIVE & LISTENING
                </span>
            </div>
            <div class="p-0 overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-slate-900/50 text-slate-400 text-[10px] uppercase tracking-widest">
                        <tr>
                            <th class="p-4 font-bold border-b border-slate-700">Waktu Deteksi</th>
                            <th class="p-4 font-bold border-b border-slate-700">IP Attacker</th>
                            <th class="p-4 font-bold border-b border-slate-700">Tipe Ancaman</th>
                            <th class="p-4 font-bold border-b border-slate-700 w-1/2">Payload (Potongan Request)</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-300 divide-y divide-slate-700/50">
                        <?php
                        $q_waf = @$conn->query("SELECT * FROM siakad_security_logs ORDER BY id DESC LIMIT 5");
                        if($q_waf && $q_waf->num_rows > 0) {
                            while($waf = $q_waf->fetch_assoc()) {
                                echo '<tr class="hover:bg-slate-750 transition-colors">';
                                echo '<td class="p-4 font-mono text-xs text-slate-400">' . $waf['waktu'] . '</td>';
                                echo '<td class="p-4 font-mono text-xs text-rose-400">' . htmlspecialchars($waf['ip_address']) . '</td>';
                                echo '<td class="p-4 font-bold text-[11px]"><span class="bg-rose-500/20 text-rose-400 px-2 py-1 rounded border border-rose-500/30">' . htmlspecialchars($waf['threat_type']) . '</span></td>';
                                echo '<td class="p-4 font-mono text-[11px] text-amber-400 truncate max-w-sm" title="'.htmlspecialchars($waf['payload']).'">' . htmlspecialchars($waf['payload']) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="4" class="p-8 text-center text-slate-500 font-mono text-xs">✅ Tidak ada anomali atau serangan terdeteksi. Sistem Aman.<br><br><span class="text-[10px] text-blue-400">PENTING: Coba ketik parameter <b class="text-white">?siakad_test_waf=1</b> di URL untuk mencoba fitur keamanan ini!</span></td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <div class="bg-slate-900/80 p-3 text-[10px] text-slate-500 text-center font-mono border-t border-slate-700">
                *WAF Filter saat ini memantau pola berbahaya (UNION, SELECT, DROP, OR 1=1, Scripting) pada parameter URL & Form POST.
            </div>
        </div>

        <div class="text-center text-slate-500 text-xs">
            <p>SIAKAD E-Learning System &copy; <?= date('Y') ?></p>
            <p class="mt-1">Generated dynamically on <span class="font-mono"><?= date('d M Y H:i:s T') ?></span></p>
        </div>

    </div>

    <script>
        // Setup Chart.js
        const ctx = document.getElementById('speedChart').getContext('2d');
        const speedChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Ping (ms)', 'Download (Mbps)', 'Upload (Mbps)'],
                datasets: [{
                    label: 'Network Speed',
                    data: [0, 0, 0],
                    backgroundColor: ['#10b981', '#3b82f6', '#a855f7'],
                    borderRadius: 4,
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        grid: { color: '#334155', drawBorder: false },
                        ticks: { color: '#94a3b8', font: { family: 'ui-monospace' } }
                    },
                    x: { 
                        grid: { display: false },
                        ticks: { color: '#94a3b8', font: { size: 10, weight: 'bold' } }
                    }
                },
                plugins: { legend: { display: false } },
                animation: { duration: 500 }
            }
        });

        document.addEventListener('DOMContentLoaded', () => {
            // Langsung jalanin pas halaman beres di-load
            runSpeedtest();
            // Otomatis jalan ngulang setiap 15 detik (AMANN!)
            setInterval(runSpeedtest, 15000); 
        });

        async function runSpeedtest() {
            const pingEl = document.getElementById('ping-speed');
            const dlEl = document.getElementById('dl-speed');
            const ulEl = document.getElementById('ul-speed');
            
            let valPing = 0;
            let valDl = 0;
            let valUl = 0;

            // 1. PING TEST
            try {
                let startTime = performance.now();
                await fetch('?action=ping&r=' + Math.random(), { cache: 'no-store' });
                valPing = Math.round(performance.now() - startTime);
                pingEl.innerText = valPing + ' ms';
                pingEl.className = valPing < 100 ? 'text-xl md:text-2xl font-black text-emerald-400' : 'text-xl md:text-2xl font-black text-amber-400';
            } catch(e) {
                pingEl.innerText = 'Err';
                pingEl.className = 'text-xl md:text-2xl font-black text-rose-500';
            }

            // 2. DOWNLOAD TEST (5MB File)
            try {
                let startTime = performance.now();
                let response = await fetch('?action=download&r=' + Math.random(), { cache: 'no-store' });
                let blob = await response.blob();
                let duration = (performance.now() - startTime) / 1000;
                let bitsLoaded = blob.size * 8;
                let speedBps = bitsLoaded / duration;
                valDl = parseFloat((speedBps / (1024 * 1024)).toFixed(2));
                dlEl.innerText = valDl + ' Mbps';
            } catch(e) {
                dlEl.innerText = 'Err';
                dlEl.className = 'text-xl md:text-2xl font-black text-rose-500';
            }

            // 3. UPLOAD TEST (2MB File)
            try {
                let dummyData = new Blob([new Uint8Array(2 * 1024 * 1024)]); 
                let startTime = performance.now();
                await fetch('?action=upload&r=' + Math.random(), {
                    method: 'POST',
                    body: dummyData,
                    cache: 'no-store'
                });
                let duration = (performance.now() - startTime) / 1000;
                let bitsLoaded = dummyData.size * 8;
                let speedBps = bitsLoaded / duration;
                valUl = parseFloat((speedBps / (1024 * 1024)).toFixed(2));
                ulEl.innerText = valUl + ' Mbps';
            } catch(e) {
                ulEl.innerText = 'Err';
                ulEl.className = 'text-xl md:text-2xl font-black text-rose-500';
            }

            // Update Grafik Bar Real-time
            speedChart.data.datasets[0].data = [valPing, valDl, valUl];
            speedChart.update();
        }
    </script>
</body>
</html>