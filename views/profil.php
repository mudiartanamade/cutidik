<?php
// --- 1. LOGIKA BACKEND & KEAMANAN ---
$id_user = $_SESSION['user_id'];

// Ambil Data User
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id_user]);
$me = $stmt->fetch();

// Ambil NPSN / Data Tambahan
$npsn_asli = "-"; 
if (!empty($me['nama_sekolah'])) {
    // Pastikan tabel 'data_sekolah' ada di database Anda
    $stmt_sekolah = $conn->prepare("SELECT npsn FROM data_sekolah WHERE nama_sekolah = ?");
    $stmt_sekolah->execute([$me['nama_sekolah']]);
    $data_sekolah = $stmt_sekolah->fetch();
    if ($data_sekolah) $npsn_asli = $data_sekolah['npsn'];
}

// --- LOGIKA DINAMIS (DINAS vs SEKOLAH) ---
// Kita tentukan status user di sini agar HTML lebih bersih
$is_dinas = (strtoupper($me['jenjang']) == 'DINAS' || stripos($me['nama_sekolah'], 'Dinas') !== false);

// Setup Variabel Tampilan berdasarkan Status
$label_role     = $is_dinas ? 'ADMINISTRATOR DINAS' : 'OPERATOR SEKOLAH';
$label_kantor   = $is_dinas ? 'Unit Kerja / Instansi' : 'Asal Sekolah';
$icon_kantor    = $is_dinas ? 'fa-building-columns' : 'fa-school';
$bg_badge       = $is_dinas ? 'bg-amber-500' : 'bg-indigo-600'; // Dinas warna Emas/Amber, Sekolah Indigo
$text_badge     = $is_dinas ? 'DINAS' : 'SEKOLAH';

// --- PROSES GANTI PASSWORD ---
if(isset($_POST['ganti_password'])) {
    $pass_lama    = $_POST['pass_lama'];
    $pass_baru    = $_POST['pass_baru'];
    $pass_konfirm = $_POST['pass_konfirm'];
    
    if(password_verify($pass_lama, $me['password'])) {
        if($pass_baru === $pass_konfirm) {
            $new_hash = password_hash($pass_baru, PASSWORD_DEFAULT);
            $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upd->execute([$new_hash, $id_user]);
            
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Password Diperbarui',
                        text: 'Akun Anda kini lebih aman.',
                        confirmButtonColor: '#4f46e5',
                        timer: 2000
                    }).then(() => { window.location='?page=profil'; });
                });
            </script>";
        } else {
            echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Gagal', 'Konfirmasi password baru tidak cocok.', 'error'));</script>";
        }
    } else {
        echo "<script>document.addEventListener('DOMContentLoaded', () => Swal.fire('Akses Ditolak', 'Password lama yang Anda masukkan salah.', 'error'));</script>";
    }
}
?>

<div class="max-w-7xl mx-auto space-y-8 animate-fade-in-up">
    
    <div class="flex flex-col md:flex-row justify-between items-end gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-3xl font-extrabold text-slate-800 tracking-tight">Profil Pengguna</h1>
            <p class="text-slate-500 mt-1">Kelola informasi akun dan preferensi keamanan Anda.</p>
        </div>
        <div class="hidden md:block">
            <span class="px-3 py-1 rounded-full bg-slate-100 text-slate-500 text-xs font-bold border border-slate-200">
                <i class="fa-solid fa-server mr-1"></i> Data Terenkripsi
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        
        <div class="lg:col-span-4 space-y-6">
            <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 overflow-hidden border border-slate-100 group hover:shadow-2xl transition-all duration-300">
                
                <div class="h-40 bg-gradient-to-br from-slate-800 to-slate-900 relative">
                    <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(#ffffff 1px, transparent 1px); background-size: 20px 20px;"></div>
                    
                    <button class="absolute top-4 right-4 bg-white/10 hover:bg-white/20 text-white p-2 rounded-full backdrop-blur-sm transition">
                        <i class="fa-solid fa-gear"></i>
                    </button>
                </div>

                <div class="px-6 relative">
                    <div class="-mt-16 mb-4 flex justify-center">
                        <div class="relative">
                            <div class="w-32 h-32 rounded-full bg-white p-1.5 shadow-xl ring-4 ring-slate-50">
                                <div class="w-full h-full rounded-full bg-slate-100 flex items-center justify-center text-4xl font-bold text-slate-700 overflow-hidden relative">
                                    <?= strtoupper(substr($me['username'], 0, 1)) ?>
                                    
                                    <div class="absolute inset-0 bg-gradient-to-tr from-white/40 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                                </div>
                            </div>
                            
                            <div class="absolute bottom-2 right-0 transform translate-x-2">
                                <span class="<?= $bg_badge ?> text-white text-[10px] font-extrabold px-3 py-1 rounded-full border-4 border-white shadow-md uppercase tracking-wider">
                                    <?= $text_badge ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mb-8">
                        <h2 class="text-2xl font-bold text-slate-800"><?= $me['username'] ?></h2>
                        <p class="text-sm font-medium text-slate-500 uppercase tracking-wide mt-1"><?= $label_role ?></p>
                    </div>

                    <div class="space-y-4 pb-8">
                        
                        <div class="flex items-center p-3 rounded-2xl bg-slate-50 border border-slate-100 transition hover:bg-blue-50 hover:border-blue-100 group/item">
                            <div class="w-10 h-10 rounded-xl bg-white text-blue-600 shadow-sm flex items-center justify-center text-lg mr-4 group-hover/item:scale-110 transition-transform">
                                <i class="fa-solid <?= $icon_kantor ?>"></i>
                            </div>
                            <div class="overflow-hidden">
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5"><?= $label_kantor ?></p>
                                <p class="text-sm font-bold text-slate-700 truncate" title="<?= $me['nama_sekolah'] ?>">
                                    <?= $me['nama_sekolah'] ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center p-3 rounded-2xl bg-slate-50 border border-slate-100 transition hover:bg-emerald-50 hover:border-emerald-100 group/item">
                            <div class="w-10 h-10 rounded-xl bg-white text-emerald-600 shadow-sm flex items-center justify-center text-lg mr-4 group-hover/item:scale-110 transition-transform">
                                <i class="fa-solid fa-fingerprint"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Kode Identitas / NPSN</p>
                                <p class="text-sm font-bold text-slate-700 font-mono"><?= $npsn_asli ?></p>
                            </div>
                        </div>

                        <div class="flex items-center p-3 rounded-2xl bg-slate-50 border border-slate-100 transition hover:bg-purple-50 hover:border-purple-100 group/item">
                            <div class="w-10 h-10 rounded-xl bg-white text-purple-600 shadow-sm flex items-center justify-center text-lg mr-4 group-hover/item:scale-110 transition-transform">
                                <i class="fa-solid fa-layer-group"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Tingkat / Jenjang</p>
                                <p class="text-sm font-bold text-slate-700"><?= $me['jenjang'] ?></p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            
            <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl p-5 text-white shadow-lg relative overflow-hidden">
                <i class="fa-solid fa-shield-cat absolute -bottom-4 -right-4 text-8xl text-white opacity-10 rotate-12"></i>
                <h4 class="font-bold text-lg mb-1 relative z-10">Keamanan Akun</h4>
                <p class="text-xs text-blue-100 relative z-10 opacity-90 leading-relaxed">
                    Terakhir login pada: <br><b><?= date('d M Y, H:i') ?> WITA</b>
                </p>
            </div>
        </div>

        <div class="lg:col-span-8">
            <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 h-full relative overflow-hidden">
                
                <div class="p-8 border-b border-slate-100 flex justify-between items-center bg-slate-50/50">
                    <div>
                        <h3 class="text-xl font-bold text-slate-800 flex items-center gap-2">
                            <i class="fa-solid fa-lock text-indigo-500"></i> Ubah Password
                        </h3>
                        <p class="text-sm text-slate-500 mt-1">Pastikan menggunakan password yang kuat (Kombinasi huruf & angka).</p>
                    </div>
                </div>

                <div class="p-8">
                    <form method="POST" class="space-y-6">
                        
                        <div class="relative group">
                            <label class="block text-sm font-bold text-slate-700 mb-2 ml-1">Password Saat Ini</label>
                            <div class="relative transition-all duration-300 focus-within:scale-[1.01]">
                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-indigo-500 transition-colors">
                                    <i class="fa-solid fa-key"></i>
                                </div>
                                <input type="password" name="pass_lama" required 
                                    class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-4 focus:ring-indigo-100 focus:border-indigo-500 outline-none transition-all placeholder:text-slate-300 font-medium" 
                                    placeholder="Masukkan password lama untuk verifikasi">
                            </div>
                        </div>

                        <div class="border-t border-slate-100 my-6"></div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="group">
                                <label class="block text-sm font-bold text-slate-700 mb-2 ml-1">Password Baru</label>
                                <div class="relative transition-all duration-300 focus-within:scale-[1.01]">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                        <i class="fa-solid fa-lock"></i>
                                    </div>
                                    <input type="password" name="pass_baru" required minlength="6"
                                        class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-4 focus:ring-emerald-100 focus:border-emerald-500 outline-none transition-all placeholder:text-slate-300 font-medium" 
                                        placeholder="Min. 6 Karakter">
                                </div>
                            </div>
                            
                            <div class="group">
                                <label class="block text-sm font-bold text-slate-700 mb-2 ml-1">Ulangi Password Baru</label>
                                <div class="relative transition-all duration-300 focus-within:scale-[1.01]">
                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 group-focus-within:text-emerald-500 transition-colors">
                                        <i class="fa-solid fa-circle-check"></i>
                                    </div>
                                    <input type="password" name="pass_konfirm" required minlength="6"
                                        class="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:bg-white focus:ring-4 focus:ring-emerald-100 focus:border-emerald-500 outline-none transition-all placeholder:text-slate-300 font-medium" 
                                        placeholder="Ketik ulang password baru">
                                </div>
                            </div>
                        </div>

                        <div class="bg-blue-50 text-blue-700 px-4 py-3 rounded-xl text-xs flex items-start gap-3 border border-blue-100">
                            <i class="fa-solid fa-circle-info mt-0.5"></i>
                            <p>Disarankan menggunakan password dengan kombinasi huruf besar, huruf kecil, dan angka agar akun Anda tetap aman dari peretasan.</p>
                        </div>

                        <div class="pt-4 flex items-center justify-end gap-4">
                            <button type="reset" class="px-6 py-3 rounded-xl text-slate-500 font-bold hover:bg-slate-100 transition text-sm">
                                Reset Form
                            </button>
                            <button type="submit" name="ganti_password" class="px-8 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl shadow-lg shadow-indigo-500/30 font-bold text-sm flex items-center gap-2 transform hover:-translate-y-1 transition-all duration-200">
                                <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in-up {
        animation: fadeInUp 0.6s ease-out forwards;
    }
</style>