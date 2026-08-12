<?php
// Ambil IP penyerang
$ip_visitor = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

// RE-INFECT: Paksa tanam ulang cookie haram selama 10 tahun
setcookie('siakad_device_banned', 'true', time() + (10 * 365 * 24 * 60 * 60), '/'); 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> 
    <title>⛔ BANNED - SIAKAD Security</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
    
    <script>
        localStorage.setItem('siakad_banned', 'true');
        sessionStorage.setItem('siakad_banned', 'true');
    </script>
</head>
<body class="bg-slate-950 flex items-center justify-center min-h-screen p-4">
    <div class="bg-slate-900 border border-red-500/30 p-8 md:p-12 rounded-3xl shadow-2xl shadow-red-900/20 max-w-lg w-full text-center relative overflow-hidden">
        <div class="absolute top-0 left-0 w-full h-1.5 bg-red-600 animate-pulse"></div>
        
        <div class="bg-red-500/10 w-24 h-24 mx-auto rounded-full flex items-center justify-center mb-6 border border-red-500/20">
            <span class="text-5xl">⛔</span>
        </div>
        
        <h1 class="text-4xl sm:text-5xl font-black text-white mb-2 tracking-tight">ACCESS <span class="text-red-500">BANNED</span></h1>
        <p class="text-slate-400 font-semibold mb-8">Sistem Keamanan SIAKAD telah memblokir akses Anda secara permanen akibat aktivitas yang melanggar keamanan.</p>
        
        <div class="bg-slate-800/50 p-5 rounded-2xl border border-slate-700 mb-8 text-left">
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Status Log Perekaman:</div>
            <div class="text-sm font-mono text-emerald-400 mb-4 flex items-center gap-2 font-bold">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_8px_#10b981]"></span> 
                Terekam Otomatis ke Database
            </div>
            
            <div class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">IP & Device Terdeteksi:</div>
            <div class="text-sm font-mono text-red-400 font-bold"><?= htmlspecialchars($ip_visitor) ?></div>
        </div>
        
        <p class="text-xs text-slate-600 font-bold">Semua aktivitas telah dicatat. <br>Hubungi Administrator jika ini adalah sebuah kesalahan.</p>
    </div>
</body>
</html>