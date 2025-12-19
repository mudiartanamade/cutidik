<?php
session_start();
require_once 'config/database.php';

// Cek sesi login
if (!isset($_SESSION['user_id'])) { header("Location: index.php"); exit; }

$page = isset($_GET['page']) ? $_GET['page'] : 'home';
$role = $_SESSION['role'];
$sekolah_user = $_SESSION['nama_sekolah'] ?? '';

// --- LOGIKA NOTIFIKASI (BARU) ---
$notifikasi_list = [];
$jumlah_notif = 0;

if($role == 'admin_dinas') {
    // Admin Dinas: Notifikasi Pengajuan Masuk (Pending)
    // Mengambil 5 pengajuan terbaru yang belum diproses
    $q_notif = $conn->query("SELECT pc.*, p.nama, p.sekolah 
                             FROM pengajuan_cuti pc 
                             JOIN pegawai p ON pc.pegawai_id = p.id 
                             WHERE pc.status='pending' 
                             ORDER BY pc.tgl_pengajuan ASC LIMIT 5");
    $notifikasi_list = $q_notif->fetchAll();
    
    // Hitung total pending untuk badge merah
    $jumlah_notif = $conn->query("SELECT COUNT(*) FROM pengajuan_cuti WHERE status='pending'")->fetchColumn();

} else {
    // Admin Sekolah: Notifikasi Cuti Disetujui (Siap Cetak SK)
    // Mengambil 5 persetujuan terbaru untuk sekolah ini
    $q_notif = $conn->prepare("SELECT pc.*, p.nama 
                               FROM pengajuan_cuti pc 
                               JOIN pegawai p ON pc.pegawai_id = p.id 
                               WHERE p.sekolah = ? AND pc.status='disetujui' 
                               ORDER BY pc.tgl_validasi DESC LIMIT 5");
    $q_notif->execute([$sekolah_user]);
    $notifikasi_list = $q_notif->fetchAll();

    // Hitung jumlah notifikasi (Simulasi count dari list yang diambil)
    $jumlah_notif = count($notifikasi_list);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard E-Cuti Disdikpora</title>
    <link rel="icon" type="image/x-icon" href="/uploads/logo_dps.png">
    <link rel="shortcut icon" href="/uploads/logo_dps.png">
    <script src="https://cdn.tailwindcss.com"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        
        /* Custom Scrollbar Mewah */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #1e293b; }
        ::-webkit-scrollbar-thumb { background: #475569; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #64748b; }

        /* Style Tambahan untuk Dropdown Notifikasi */
        .dropdown-menu { display: none; }
        .group:hover .dropdown-menu { display: block; }
        
        /* Custom Font SweetAlert agar sesuai tema */
        div:where(.swal2-container) {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800">

    <div class="flex h-screen overflow-hidden">
        
        <div id="mobile-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 hidden lg:hidden transition-opacity"></div>

        <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 w-72 bg-gradient-to-b from-[#0f172a] to-[#1e293b] text-white flex flex-col shadow-2xl z-50 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out border-r border-slate-700/50">
            
            <div class="p-6 flex items-center gap-4 border-b border-slate-700/50 bg-[#0f172a]">
                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center shadow-lg shadow-amber-500/20 transform hover:scale-105 transition duration-300 p-1">
    <img src="/uploads/logo_dps.png" alt="Logo Denpasar" class="w-full h-full object-contain">
</div>
                <div>
                    <h1 class="font-bold text-xl tracking-wide leading-tight text-white">E-CUTI</h1>
                    <p class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Kota Denpasar</p>
                </div>
                <button onclick="toggleSidebar()" class="lg:hidden ml-auto text-slate-400 hover:text-white transition">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>

            <nav class="flex-1 py-6 px-4 space-y-2 overflow-y-auto">
                
                <p class="text-[10px] font-bold text-slate-500 uppercase px-4 mb-2 tracking-widest">Menu Utama</p>
                
                <a href="?page=home" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='home' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-chart-pie w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='home' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Dashboard</span>
                    <?php if($page=='home'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>
                
                <?php if($role == 'admin_sekolah'): ?>
                <a href="?page=pegawai" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='pegawai' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-users w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='pegawai' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Data Guru-KS</span>
                    <?php if($page=='pegawai'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>
                <?php endif; ?>

                <?php if($role == 'admin_sekolah'): ?>
                <div class="mt-6 mb-2 px-4 border-t border-slate-700/50 pt-4">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Administrasi</p>
                </div>
                <a href="?page=pengajuan" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='pengajuan' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-paper-plane w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='pengajuan' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Ajukan Cuti</span>
                    <?php if($page=='pengajuan'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>
                <?php endif; ?>

                <?php if($role == 'admin_dinas'): ?>
                <div class="mt-6 mb-2 px-4 border-t border-slate-700/50 pt-4">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Verifikasi & Data</p>
                </div>
                
                <a href="?page=data_sekolah" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='data_sekolah' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-school w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='data_sekolah' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Data Sekolah</span>
                    <?php if($page=='data_sekolah'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>

                <a href="?page=pegawai" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='pegawai' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-users w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='pegawai' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Data Guru-KS</span>
                    <?php if($page=='pegawai'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>

                <a href="?page=persetujuan" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='persetujuan' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-check-double w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='persetujuan' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm flex-1">Persetujuan</span>
                    <?php 
                    $cek = $conn->query("SELECT COUNT(*) FROM pengajuan_cuti WHERE status='pending'")->fetchColumn();
                    if($cek > 0) echo "<span class='bg-red-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-lg shadow-red-500/50 animate-pulse'>$cek</span>";
                    ?>
                </a>
                
                <a href="?page=laporan" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='laporan' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-file-pdf w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='laporan' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Laporan Rekap</span>
                    <?php if($page=='laporan'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>

                <div class="mt-6 mb-2 px-4 border-t border-slate-700/50 pt-4">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Pengaturan</p>
                </div>
                
                <a href="?page=pengaturan_cuti" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='pengaturan_cuti' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-calendar-check w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='pengaturan_cuti' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Pengaturan Cuti</span>
                    <?php if($page=='pengaturan_cuti'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>

                <a href="?page=users" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='users' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-user-shield w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='users' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Manajemen User</span>
                    <?php if($page=='users'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>
                <?php endif; ?>

                <?php if($role != 'admin_dinas'): ?>
                <div class="mt-6 mb-2 px-4 border-t border-slate-700/50 pt-4">
                    <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Akun Saya</p>
                </div>
                <?php endif; ?>
                
                <a href="?page=profil" class="flex items-center gap-3 px-4 py-3.5 rounded-xl transition-all group <?= $page=='profil' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-lg shadow-blue-500/30 ring-1 ring-white/10' : 'text-slate-400 hover:bg-white/5 hover:text-white' ?>">
                    <i class="fa-solid fa-id-card w-5 text-center group-hover:scale-110 transition duration-300 <?= $page=='profil' ? 'text-white' : 'text-slate-500 group-hover:text-blue-400' ?>"></i> 
                    <span class="font-medium text-sm">Profil Saya</span>
                    <?php if($page=='profil'): ?><i class="fa-solid fa-chevron-right ml-auto text-xs opacity-50"></i><?php endif; ?>
                </a>

            </nav>

            <div class="p-4 border-t border-slate-700/50 bg-[#0f172a]">
                <div class="flex items-center gap-3 mb-4 px-2">
                    <div class="w-10 h-10 rounded-full bg-slate-800 border border-slate-600 flex items-center justify-center text-slate-300 overflow-hidden shadow-inner">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="overflow-hidden">
                        <p class="text-sm font-semibold text-white truncate w-32"><?= $_SESSION['username'] ?></p>
                        <p class="text-[10px] text-slate-400 truncate"><?= $role == 'admin_sekolah' ? 'Operator Sekolah' : 'Administrator Dinas' ?></p>
                    </div>
                </div>
                <a href="logout.php" onclick="konfirmasiLogout(event, this.href)" class="flex items-center justify-center gap-2 w-full bg-red-600 hover:bg-red-500 text-white py-2.5 rounded-lg text-sm font-medium transition shadow-lg shadow-red-900/20 border border-red-500/20 group">
                    <i class="fa-solid fa-right-from-bracket group-hover:scale-110 transition"></i> Logout
                </a>
            </div>
        </aside>

        <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 relative">
            
            <header class="bg-white/80 backdrop-blur-md border-b border-slate-200 h-20 flex items-center justify-between px-4 lg:px-8 shadow-sm z-30 sticky top-0">
                <div class="flex items-center gap-4">
                    <button onclick="toggleSidebar()" class="lg:hidden text-slate-500 hover:text-blue-600 transition p-2 rounded-lg hover:bg-slate-100">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>

                    <div>
                        <h2 class="text-xl font-bold text-slate-800 capitalize tracking-tight line-clamp-1">
                            <?= 
                                $page == 'data_sekolah' ? 'Data Sekolah & Unit Kerja' : 
                                ($page == 'pengaturan_cuti' ? 'Pengaturan Cuti Bersama' : 
                                ($page == 'users' ? 'Manajemen Pengguna' : 
                                ($page == 'profil' ? 'Profil Saya' : 
                                ($page == 'home' ? 'Dashboard Overview' : str_replace('_', ' ', $page))))) 
                            ?>
                        </h2>
                        <p class="text-xs text-slate-500 mt-1 hidden sm:block">Sistem Informasi Manajemen Cuti Terpadu</p>
                    </div>
                </div>
                
                <div class="flex items-center gap-6">
                    <div class="hidden md:block text-right">
                        <p class="text-sm font-bold text-slate-700"><?= tgl_indo(date('Y-m-d')) ?></p>
                        <p class="text-[10px] text-slate-400 uppercase tracking-widest font-semibold">Denpasar, Bali</p>
                    </div>
                    
                    <div class="relative group">
                        <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-400 border border-slate-200 shadow-sm hover:bg-blue-50 hover:text-blue-600 transition cursor-pointer relative">
                            <i class="fa-regular fa-bell"></i>
                            <?php if($jumlah_notif > 0): ?>
                                <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center animate-pulse shadow-md border-2 border-white"><?= $jumlah_notif ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="dropdown-menu absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-2xl border border-slate-100 overflow-hidden z-[100] transform origin-top-right transition-all">
                            <div class="p-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
                                <span class="text-xs font-bold uppercase text-slate-500">Notifikasi Terbaru</span>
                                <span class="text-[10px] bg-blue-100 text-blue-600 px-2 py-0.5 rounded-full font-bold"><?= $jumlah_notif ?> Baru</span>
                            </div>
                            <div class="max-h-64 overflow-y-auto">
                                <?php if(empty($notifikasi_list)): ?>
                                    <div class="p-6 text-center text-slate-400 text-xs italic">
                                        <i class="fa-regular fa-bell-slash text-2xl mb-2 opacity-50"></i><br>Tidak ada notifikasi baru.
                                    </div>
                                <?php else: ?>
                                    <?php foreach($notifikasi_list as $notif): ?>
                                        <a href="<?= $role == 'admin_dinas' ? '?page=persetujuan' : '?page=pengajuan&tab=riwayat' ?>" class="block p-3 border-b border-slate-50 hover:bg-blue-50 transition">
                                            <div class="flex items-start gap-3">
                                                <div class="w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-xs font-bold <?= $role == 'admin_dinas' ? 'bg-yellow-100 text-yellow-600' : 'bg-green-100 text-green-600' ?>">
                                                    <i class="fa-solid <?= $role == 'admin_dinas' ? 'fa-hourglass-start' : 'fa-check' ?>"></i>
                                                </div>
                                                <div>
                                                    <p class="text-sm font-bold text-slate-700 line-clamp-1"><?= $notif['nama'] ?></p>
                                                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-1">
                                                        <?= $role == 'admin_dinas' ? 'Pengajuan Cuti Baru: ' . $notif['sekolah'] : 'Disetujui! Silahkan cetak Surat.' ?>
                                                    </p>
                                                    <div class="text-[10px] mt-2 flex justify-between items-center border-t border-slate-50 pt-1">
    <span class="text-blue-600 font-bold">
        <i class="fa-regular fa-clock mr-1"></i><?= isset($notif['tgl_validasi']) ? substr($notif['tgl_validasi'], 11, 5) : substr($notif['tgl_pengajuan'], 11, 5) ?>
    </span>
    <span class="text-slate-400">
        <?= tgl_indo(isset($notif['tgl_validasi']) ? substr($notif['tgl_validasi'], 0, 10) : substr($notif['tgl_pengajuan'], 0, 10)) ?>
    </span>
</div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <?php if(!empty($notifikasi_list)): ?>
                            <div class="p-2 bg-slate-50 border-t border-slate-100 text-center">
                                <a href="<?= $role == 'admin_dinas' ? '?page=persetujuan' : '?page=pengajuan&tab=riwayat' ?>" class="text-xs font-bold text-blue-600 hover:text-blue-800">Lihat Semua</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </header>

            <div class="flex-1 overflow-auto p-4 lg:p-8 scroll-smooth">
                <div class="max-w-7xl mx-auto pb-10">
                    <?php
                    $filename = 'views/'.$page.'.php';
                    if(file_exists($filename)){
                        include $filename;
                    } else {
                        echo "
                        <div class='flex flex-col items-center justify-center py-24 text-slate-400'>
                            <div class='w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mb-4 text-3xl'>
                                <i class='fa-regular fa-file-circle-question text-slate-300'></i>
                            </div>
                            <h3 class='text-xl font-bold text-slate-600'>Halaman Tidak Ditemukan</h3>
                            <p class='text-sm mt-1'>File view tidak tersedia di server.</p>
                        </div>";
                    }
                    ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                overlay.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                overlay.classList.add('hidden');
            }
        }

        // --- FUNGSI SWEETALERT GLOBAL ---

        // 1. Konfirmasi Logout
        function konfirmasiLogout(e, url) {
            e.preventDefault(); 
            Swal.fire({
                title: 'Yakin ingin keluar?',
                text: "Sesi Anda akan diakhiri.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, Keluar!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        // 2. Konfirmasi Hapus (Umum)
        function konfirmasiHapus(e, url, teks = "Data yang dihapus tidak dapat dikembalikan!") {
            e.preventDefault();
            Swal.fire({
                title: 'Hapus Data?',
                text: teks,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        // 3. Konfirmasi Aksi Umum (Misal: Toggle Status)
        function konfirmasiAksi(e, url, judul, teks, warnaBtn = '#3085d6') {
            e.preventDefault();
            Swal.fire({
                title: judul,
                text: teks,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: warnaBtn,
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Ya, Lanjutkan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }
    </script>
</body>
</html>