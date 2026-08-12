<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Akses Ditolak - SIAKAD</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        html, body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .hero-bg { background-color: #0d3b66; background-size: cover; background-position: center; }
        .glass-card { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.2); box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5); }
        
        /* Animasi Segitiga Getar */
        @keyframes getar {
            0% { transform: translate(1px, 1px) rotate(0deg); }
            10% { transform: translate(-1px, -2px) rotate(-1deg); }
            20% { transform: translate(-3px, 0px) rotate(1deg); }
            30% { transform: translate(3px, 2px) rotate(0deg); }
            40% { transform: translate(1px, -1px) rotate(1deg); }
            50% { transform: translate(-1px, 2px) rotate(-1deg); }
            60% { transform: translate(-3px, 1px) rotate(0deg); }
            70% { transform: translate(3px, 1px) rotate(-1deg); }
            80% { transform: translate(-1px, -1px) rotate(1deg); }
            90% { transform: translate(1px, 2px) rotate(0deg); }
            100% { transform: translate(1px, -2px) rotate(-1deg); }
        }
        .animasi-getar { animation: getar 0.4s infinite; }
    </style>
</head>
<body class="antialiased m-0 p-0 flex flex-col min-h-screen hero-bg justify-center items-center px-4">

    <div class="glass-card rounded-[2rem] p-8 md:p-16 max-w-2xl w-full text-center border-t-4 border-t-red-500 relative overflow-hidden">
        <div class="flex flex-col items-center justify-center mb-8 gap-4">
            <div class="animasi-getar text-red-500 bg-red-500/10 p-4 rounded-full border border-red-500/30">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-24 w-24 drop-shadow-[0_0_20px_rgba(239,68,68,0.8)]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <img src="/assets/logo-penabur.png" alt="SIAKAD Logo" class="h-10 w-auto opacity-50 drop-shadow-lg" onerror="this.style.display='none'">
        </div>
        
        <div class="inline-block bg-red-500 text-white px-4 py-1.5 rounded-full text-xs font-black uppercase tracking-widest mb-6 shadow-lg">
            Error 403
        </div>
         
        <h1 class="text-3xl md:text-5xl font-black text-white mb-6 tracking-tighter drop-shadow-lg">
            Aktivitas Mencurigakan!
        </h1>
        
        <p class="text-lg text-slate-300 font-light leading-relaxed mb-8">
            Maaf, Anda tidak memiliki izin untuk melihat halaman atau mencoba menyuntikkan instruksi berbahaya ke server. Permintaan ditolak.
        </p>
        
        <div class="flex justify-center">
            <a href="/" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-full font-bold transition shadow-md transform hover:-translate-y-1">
                Kembali ke Beranda
            </a>
        </div>
    </div>
</body>
</html>