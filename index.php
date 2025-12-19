<?php
session_start();
// Jika sudah login, langsung ke dashboard
if(isset($_SESSION['user_id'])) { 
    header("Location: dashboard.php"); 
    exit; 
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - E-Cuti Disdikpora Denpasar</title>
    <link rel="icon" type="image/x-icon" href="/uploads/logo_dps.png">
    <link rel="shortcut icon" href="/uploads/logo_dps.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center relative py-12 px-4 sm:px-6 lg:px-8">
    
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-[20%] -left-[10%] w-[500px] h-[500px] bg-blue-600 rounded-full blur-[100px] opacity-20 animate-pulse"></div>
        <div class="absolute top-[60%] -right-[10%] w-[400px] h-[400px] bg-purple-600 rounded-full blur-[100px] opacity-20 animate-pulse"></div>
    </div>

    <div class="relative z-10 w-full max-w-md p-8 bg-white/10 backdrop-blur-xl border border-white/20 rounded-3xl shadow-2xl">
        <div class="text-center mb-8">
            <div class="w-20 h-20 bg-white rounded-2xl mx-auto flex items-center justify-center shadow-lg mb-4 p-2">
                <img src="/uploads/logo_dps.png" class="w-full h-full object-contain" alt="Logo Denpasar">
            </div>
            <h1 class="text-2xl font-bold text-white tracking-wide">E-CUTI DISDIKPORA</h1>
            <p class="text-slate-300 text-sm mt-1">Sistem Informasi Manajemen Cuti Guru-KS</p>
        </div>

        <?php if(isset($_GET['error'])): ?>
            <div class="bg-red-500/20 border border-red-500/50 text-red-100 text-sm p-4 rounded-xl mb-6 text-center backdrop-blur-md flex items-center justify-center gap-2 animate-bounce">
                <i class="fa-solid fa-triangle-exclamation"></i> 
                <?php 
                    if($_GET['error'] == 'captcha') echo "Kode Keamanan (Captcha) Salah!";
                    else echo "Username atau Password salah!";
                ?>
            </div>
        <?php endif; ?>

        <form action="auth_handler.php" method="POST" class="space-y-5">
            
            <div>
                <label class="block text-slate-300 text-xs font-bold uppercase mb-2 pl-1 tracking-wider">Username</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-slate-400"><i class="fa-solid fa-user"></i></span>
                    <input type="text" name="username" required placeholder="Masukkan username..." class="w-full bg-slate-800/50 border border-slate-600 rounded-xl pl-10 pr-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition shadow-sm">
                </div>
            </div>

            <div>
                <label class="block text-slate-300 text-xs font-bold uppercase mb-2 pl-1 tracking-wider">Password</label>
                <div class="relative">
                    <span class="absolute left-4 top-3.5 text-slate-400"><i class="fa-solid fa-lock"></i></span>
                    
                    <input type="password" name="password" id="passInput" required placeholder="••••••••" class="w-full bg-slate-800/50 border border-slate-600 rounded-xl pl-10 pr-12 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition shadow-sm">
                    
                    <span class="absolute right-4 top-3.5 text-slate-400 cursor-pointer hover:text-white transition" onclick="togglePassword()">
                        <i class="fa-solid fa-eye" id="eyeIcon"></i>
                    </span>
                </div>
            </div>

            <div>
                <label class="block text-slate-300 text-xs font-bold uppercase mb-2 pl-1 tracking-wider">Kode Keamanan</label>
                <div class="flex gap-3">
                    
                    <div class="bg-white rounded-xl p-1 flex items-center justify-center w-32 shrink-0 shadow-inner relative group cursor-pointer hover:scale-105 transition" title="Klik untuk refresh kode" onclick="refreshCaptcha()">
                        
                        <img src="captcha.php" id="captchaImg" class="h-11 w-full object-fill rounded-lg opacity-90 group-hover:opacity-100 transition">
                        
                        <i class="fa-solid fa-rotate absolute right-1 top-1 text-[10px] text-slate-400 pointer-events-none bg-white/80 rounded-full p-1 shadow-sm"></i>
                    </div>

                    <input type="text" name="captcha" required placeholder="Ketik kode..." autocomplete="off" class="flex-1 min-w-0 bg-slate-800/50 border border-slate-600 rounded-xl px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition font-mono tracking-widest text-center shadow-sm">
                </div>
                <p class="text-[10px] text-slate-500 mt-2 pl-1 flex items-center gap-1">
                    <i class="fa-solid fa-circle-info"></i> Klik gambar jika kode tidak terbaca (Case Sensitive).
                </p>
            </div>

            <button type="submit" name="login" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-blue-500/30 transition transform hover:scale-[1.02] active:scale-95 mt-6">
                MASUK APLIKASI
            </button>
        </form>
        
        <p class="text-center text-slate-500 text-xs mt-8">&copy; 2025 Disdikpora Kota Denpasar</p>
    </div>

    <script>
        // 1. Fungsi Toggle Password (Lihat/Sembunyikan)
        function togglePassword() {
            const passInput = document.getElementById('passInput');
            const eyeIcon = document.getElementById('eyeIcon');
            
            if (passInput.type === 'password') {
                passInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            }
        }

        // 2. Fungsi Refresh Captcha
        function refreshCaptcha() {
            const img = document.getElementById('captchaImg');
            // Menambah timestamp agar browser memuat ulang gambar baru (bypass cache)
            img.src = 'captcha.php?' + new Date().getTime(); 
        }
    </script>
</body>
</html>